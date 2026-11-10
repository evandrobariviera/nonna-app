<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RememberCampaignClient;
use App\Models\AdAccountConnection;
use App\Models\ClientAdAccount;
use App\Models\MetaCampaignCreation;
use App\Models\Task;
use App\Services\AdSync\CampaignControls;
use App\Services\AdSync\MetaCampaignCreator;
use App\Services\Billing\AccountStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Central de Campanhas → Nova campanha (Meta). A lógica fica em
 * MetaCampaignCreator; tudo nasce pausado.
 */
class MetaCampaignCreationController extends Controller
{
    public function __construct(private MetaCampaignCreator $creator) {}

    public function create(Request $request, CampaignControls $controls): View
    {
        $clientId = $request->get('client_id') ?: RememberCampaignClient::current();
        $clients = AccountStatement::clientsWithAccounts();
        $accounts = $clientId ? ClientAdAccount::where('client_id', $clientId)->where('platform', 'meta_ads')->get() : collect();
        $connections = AdAccountConnection::whereIn('client_ad_account_id', $accounts->pluck('id'))->get()->keyBy('client_ad_account_id');

        // Artes aprovadas recentes do cliente (para o anúncio já nascer junto).
        $arts = $clientId ? Task::with('latestApprovalRound')->where('client_id', $clientId)->where('destination', 'campanhas_patrocinadas')
            ->whereHas('latestApprovalRound', fn ($q) => $q->where('status', 'approved'))
            ->whereHas('attachments', fn ($q) => $q->where('is_deliverable', true)->where(fn ($m) => $m->where('mime_type', 'like', 'image/%')->orWhere('mime_type', 'like', 'video/%')))
            ->where('updated_at', '>=', now()->subDays(60))->latest('updated_at')->limit(30)->get()
            ->map(fn ($t) => ['task' => $t, 'media' => MetaAdCreationController::approvedImages($t, true)]) : collect();

        return view('campaign-creations.create', [
            'clients'     => $clients,
            'clientId'    => $clientId,
            'client'      => $clients->firstWhere('id', $clientId),
            'accounts'    => $accounts,
            'connections' => $connections,
            'room'        => $clientId ? $controls->budgetRoom($clientId) : null,
            'arts'        => $arts,
            'kinds'       => MetaCampaignCreation::$kinds,
            'creations'   => MetaCampaignCreation::with(['createdBy:id,name', 'adCreation', 'client'])->when($clientId, fn ($q) => $q->where('client_id', $clientId))->latest()->limit(15)->get(),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));

        return response()->json(['cities' => mb_strlen($q) >= 3 ? $this->creator->searchCities($q) : []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = Validator::make($request->all(), [
            'client_ad_account_id' => 'required|uuid',
            'kind'                 => 'required|in:' . implode(',', array_keys(MetaCampaignCreation::$kinds)),
            'campaign_name'        => 'required|string|max:250',
            'adset_name'           => 'required|string|max:250',
            'daily_budget'         => 'required|numeric|min:1|max:100000',
            'housing'              => 'nullable|boolean',
            'start'                => 'nullable|date|after_or_equal:today',
            'cities'               => 'required|array|min:1|max:10',
            'cities.*.key'         => 'required|string|max:30',
            'cities.*.name'        => 'required|string|max:120',
            'radius'               => 'nullable|integer|min:0|max:80',
            'age_min'              => 'nullable|integer|min:13|max:65',
            'age_max'              => 'nullable|integer|min:13|max:65',
            'gender'               => 'nullable|in:todos,homens,mulheres',
            'link'                 => 'nullable|url|max:1000',
            'task_id'              => 'nullable|uuid',
            'attachment_id'        => 'nullable|uuid',
            'message'              => 'nullable|string|max:5000',
            'headline'             => 'nullable|string|max:255',
        ], ['cities.required' => 'Escolha pelo menos uma cidade.', 'start.after_or_equal' => 'A data de início não pode ser no passado.']);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $account = ClientAdAccount::where('platform', 'meta_ads')->findOrFail($request->input('client_ad_account_id'));
        $housing = $request->boolean('housing');
        $input = [
            'kind'          => $request->input('kind'),
            'campaign_name' => trim($request->input('campaign_name')),
            'adset_name'    => trim($request->input('adset_name')),
            'daily_budget'  => (float) $request->input('daily_budget'),
            'housing'       => $housing,
            'start'         => $request->input('start'),
            'audience'      => [
                'cities'  => array_values(array_map(fn ($c) => ['key' => $c['key'], 'name' => $c['name']], $request->input('cities'))),
                'radius'  => (int) $request->input('radius', 0),
                'age_min' => $housing ? 18 : (int) $request->input('age_min', 18),
                'age_max' => $housing ? 65 : (int) $request->input('age_max', 65),
                'gender'  => $housing ? 'todos' : $request->input('gender', 'todos'),
            ],
            'link'          => $request->input('link'),
            'task_id'       => $request->input('task_id'),
            'attachment_id' => $request->input('attachment_id'),
            'message'       => $request->input('message'),
            'headline'      => $request->input('headline'),
        ];

        if ($problems = MetaCampaignCreator::problems($input)) {
            return back()->withErrors(['meta' => implode(' ', $problems)])->withInput();
        }

        try {
            $creation = $this->creator->create($account, $input, Auth::id());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['meta' => 'Não foi possível criar: ' . $e->getMessage()])->withInput();
        }

        if ($creation->status === 'erro') {
            return back()->withErrors(['meta' => $creation->error])->withInput();
        }

        return redirect()->route('campaign-creations.create', ['client_id' => $account->client_id])
            ->with('success', 'Campanha criada PAUSADA na Meta' . ($creation->meta_ad_creation_id ? ', com o anúncio' : '') . '. Confira e clique em "Ativar" quando quiser que comece a rodar.'
                . ($creation->ad_error ? ' Atenção: ' . $creation->ad_error : ''));
    }

    public function activate(MetaCampaignCreation $creation): RedirectResponse
    {
        abort_unless($creation->status === 'pausado' && $creation->campaign_id && $creation->adset_id, 422);

        if ($error = $this->creator->activate($creation, Auth::id())) {
            return back()->withErrors(['meta' => 'Não ativou: ' . $error]);
        }

        return back()->with('success', 'Campanha ativada. A Meta revisa o anúncio antes de começar a entregar.');
    }
}
