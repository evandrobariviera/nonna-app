<?php

namespace App\Http\Controllers;

use App\Models\AdAccountConnection;
use App\Models\AdCampaign;
use App\Models\GoogleAdCreation;
use App\Services\AdSync\GoogleSearchAdCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Central de Campanhas, etapa 5: anúncio de Pesquisa no Google num grupo que
 * já existe. A lógica fica em GoogleSearchAdCreator.
 */
class GoogleSearchAdController extends Controller
{
    public function __construct(private GoogleSearchAdCreator $creator) {}

    public function create(AdCampaign $campaign): View
    {
        abort_unless(GoogleSearchAdCreator::isSearch($campaign), 404);
        $campaign->load('adAccount.client');

        return view('google-search-ads.create', [
            'campaign'   => $campaign,
            'connection' => AdAccountConnection::where('client_ad_account_id', $campaign->client_ad_account_id)->first(),
            'creations'  => GoogleAdCreation::with('createdBy:id,name')->where('ad_campaign_id', $campaign->id)->latest()->get(),
        ]);
    }

    public function groups(AdCampaign $campaign): JsonResponse
    {
        abort_unless(GoogleSearchAdCreator::isSearch($campaign), 404);

        try {
            return response()->json(['groups' => $this->creator->adGroups($campaign->load('adAccount'))]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function suggest(Request $request, AdCampaign $campaign): JsonResponse
    {
        abort_unless(GoogleSearchAdCreator::isSearch($campaign), 404);
        $v = Validator::make($request->all(), ['ad_group_id' => 'required|string|max:30', 'pedido' => 'nullable|string|max:1000']);
        if ($v->fails()) {
            return response()->json(['message' => 'Escolha o grupo de anúncios.'], 422);
        }

        try {
            $group = $this->group($campaign, $request->input('ad_group_id'));
            $suggestion = $this->creator->suggest($campaign, $group, (string) $request->input('pedido', ''), Auth::id());
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'A IA não conseguiu sugerir agora: ' . $e->getMessage()], 422);
        }

        return response()->json($suggestion);
    }

    public function store(Request $request, AdCampaign $campaign): RedirectResponse
    {
        abort_unless(GoogleSearchAdCreator::isSearch($campaign), 404);

        $v = Validator::make($request->all(), [
            'ad_group_id'    => 'required|string|max:30',
            'headlines'      => 'required|array',
            'headlines.*'    => 'nullable|string|max:100',
            'descriptions'   => 'required|array',
            'descriptions.*' => 'nullable|string|max:200',
            'path1'          => 'nullable|string|max:40',
            'path2'          => 'nullable|string|max:40',
            'final_url'      => 'required|url|max:1000',
            'ai_suggested'   => 'nullable|boolean',
        ], ['final_url.*' => 'Informe o endereço de destino (com https://).']);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $trim = fn ($list) => array_values(array_filter(array_map(fn ($t) => trim(preg_replace('/\s+/', ' ', (string) $t)), $list ?? []), fn ($t) => $t !== ''));
        $input = [
            'headlines'    => $trim($request->input('headlines')),
            'descriptions' => $trim($request->input('descriptions')),
            'path1'        => GoogleSearchAdCreator::slugPath((string) $request->input('path1')),
            'path2'        => GoogleSearchAdCreator::slugPath((string) $request->input('path2')),
            'final_url'    => $request->input('final_url'),
        ];

        if ($problems = GoogleSearchAdCreator::problems($input['headlines'], $input['descriptions'], $input['path1'], $input['path2'])) {
            return back()->withErrors(['google' => implode(' ', $problems)])->withInput();
        }

        try {
            $group = $this->group($campaign->load('adAccount'), $request->input('ad_group_id'));
            $creation = $this->creator->create($campaign, $group, $input, $request->boolean('ai_suggested'), Auth::id());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['google' => 'Não foi possível criar: ' . $e->getMessage()])->withInput();
        }

        if ($creation->status === 'erro') {
            return back()->withErrors(['google' => 'O Google recusou o anúncio: ' . $creation->error])->withInput();
        }

        return redirect()->route('google-search-ads.create', $campaign)
            ->with('success', 'Anúncio de Pesquisa criado PAUSADO no Google. Confira e clique em "Ativar" quando quiser que comece a rodar.');
    }

    public function activate(GoogleAdCreation $creation): RedirectResponse
    {
        abort_unless($creation->status === 'pausado' && $creation->ad_resource, 422);

        if ($error = $this->creator->activate($creation, Auth::id())) {
            return back()->withErrors(['google' => 'O Google não ativou: ' . $error]);
        }

        return back()->with('success', 'Anúncio ativado. Ele roda se o grupo e a campanha também estiverem ativos e o Google aprovar os textos.');
    }

    private function group(AdCampaign $campaign, string $groupId): array
    {
        return collect($this->creator->adGroups($campaign))->firstWhere('id', $groupId)
            ?? throw new \RuntimeException('Grupo de anúncios não encontrado nesta campanha.');
    }
}
