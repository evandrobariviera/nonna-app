<?php

namespace App\Http\Controllers;

use App\Models\AdAccountConnection;
use App\Models\ClientAdAccount;
use App\Models\MetaAdCreation;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\AdSync\MetaAdCreator;
use App\Services\Billing\AccountStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Criativos (Central de Campanhas, etapa 3): artes aprovadas para campanha
 * patrocinada e o "Criar anúncio" que leva a arte para a Meta, pausado.
 */
class MetaAdCreationController extends Controller
{
    private const WINDOW_DAYS = 45;

    public function index(Request $request): View
    {
        $clientId = $request->get('client_id');
        $filter = $request->get('situacao', 'subir');

        $tasks = Task::with(['client', 'latestApprovalRound'])
            ->where('destination', 'campanhas_patrocinadas')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->whereHas('latestApprovalRound', fn ($q) => $q->where('status', 'approved')
                ->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'aviso'))
                ->whereRaw('COALESCE(resolved_at, updated_at) >= ?', [now()->subDays(self::WINDOW_DAYS)]))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $creations = MetaAdCreation::whereIn('task_id', $tasks->pluck('id'))->where('status', '!=', 'erro')->latest()->get()->groupBy('task_id');
        $images = TaskAttachment::whereIn('task_id', $tasks->pluck('id'))->where('is_deliverable', true)
            ->where('mime_type', 'like', 'image/%')->orderBy('created_at')->get()->groupBy('task_id');

        $situation = fn (Task $t) => ($c = $creations->get($t->id)) ? ($c->contains('status', 'ativo') ? 'ar' : 'criado') : 'subir';
        $counts = $tasks->countBy($situation);
        $tasks = $filter === 'todos' ? $tasks : $tasks->filter(fn ($t) => $situation($t) === $filter)->values();

        // Google Pesquisa: anúncios de texto não vêm de arte; atalho para as campanhas ativas.
        $searchCampaigns = \App\Models\AdCampaign::with('adAccount.client')
            ->where('platform', 'like', 'google%')->whereRaw('UPPER(objective) = ?', ['SEARCH'])->where('status', 'active')
            ->when($clientId, fn ($q) => $q->whereHas('adAccount', fn ($a) => $a->where('client_id', $clientId)))
            ->orderBy('name')->get();

        return view('ad-creations.index', [
            'searchCampaigns' => $searchCampaigns,
            'tasks'     => $tasks,
            'creations' => $creations,
            'images'    => $images,
            'counts'    => $counts,
            'filter'    => $filter,
            'clients'   => AccountStatement::clientsWithAccounts(),
            'clientId'  => $clientId,
        ]);
    }

    public function create(Task $task): View
    {
        abort_unless($task->client_id, 404);

        $accounts = ClientAdAccount::where('client_id', $task->client_id)->where('platform', 'meta_ads')->get();
        $connections = AdAccountConnection::whereIn('client_ad_account_id', $accounts->pluck('id'))->get()->keyBy('client_ad_account_id');

        return view('ad-creations.create', [
            'task'        => $task->load(['client', 'latestApprovalRound']),
            'accounts'    => $accounts,
            'connections' => $connections,
            'images'      => $this->approvedImages($task),
            'caption'     => $task->latestApprovalRound?->caption ?? $task->caption ?? '',
            'ctaOptions'  => MetaAdCreator::CTA_OPTIONS,
            'creations'   => MetaAdCreation::with('createdBy:id,name')->where('task_id', $task->id)->latest()->get(),
        ]);
    }

    public function adsets(ClientAdAccount $adAccount, MetaAdCreator $creator): JsonResponse
    {
        abort_unless($adAccount->platform === 'meta_ads', 404);

        return response()->json(['adsets' => $creator->adsets($adAccount)]);
    }

    public function preview(Request $request, Task $task, MetaAdCreator $creator): JsonResponse
    {
        [$data, $error] = $this->validated($request, $task);
        if ($error) {
            return $error;
        }

        return response()->json($creator->preview($data['account'], $data['input'], $data['image']));
    }

    public function store(Request $request, Task $task, MetaAdCreator $creator): RedirectResponse
    {
        [$data, $error] = $this->validated($request, $task);
        if ($error) {
            return back()->withErrors($error->getData(true)['errors'] ?? ['erro' => 'Dados inválidos.'])->withInput();
        }

        $caption = trim((string) ($task->latestApprovalRound?->caption ?? ''));
        $changed = $caption !== '' && trim($data['input']['message']) !== $caption;

        try {
            $creation = $creator->create($task, $data['account'], $data['image'], $data['input'], Auth::id(), $changed);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['meta' => 'Não foi possível criar: ' . $e->getMessage()])->withInput();
        }

        if ($creation->status === 'erro') {
            return back()->withErrors(['meta' => 'A Meta recusou o anúncio: ' . $creation->error])->withInput();
        }

        return redirect()->route('ad-creations.create', $task)
            ->with('success', 'Anúncio criado PAUSADO na Meta. Confira e clique em "Ativar" quando quiser que comece a rodar.');
    }

    public function activate(MetaAdCreation $creation, MetaAdCreator $creator): RedirectResponse
    {
        abort_unless($creation->status === 'pausado' && $creation->ad_id, 422);

        if ($error = $creator->activate($creation, Auth::id())) {
            return back()->withErrors(['meta' => 'A Meta não ativou: ' . $error]);
        }

        return back()->with('success', 'Anúncio ativado. Ele começa a rodar se o conjunto e a campanha também estiverem ativos.');
    }

    /** Imagens entregues na rodada aprovada (ou, se não houver, todas as entregues). */
    private function approvedImages(Task $task)
    {
        $round = $task->latestApprovalRound?->round_number;
        $all = TaskAttachment::where('task_id', $task->id)->where('is_deliverable', true)
            ->where('mime_type', 'like', 'image/%')->orderBy('created_at')->get();
        $inRound = $round ? $all->where('round_number', $round)->values() : collect();

        return $inRound->isNotEmpty() ? $inRound : $all;
    }

    /** @return array{0: ?array, 1: ?JsonResponse} */
    private function validated(Request $request, Task $task): array
    {
        $v = Validator::make($request->all(), [
            'client_ad_account_id' => 'required|uuid',
            'adset_id'             => 'required|string|max:50',
            'task_attachment_id'   => 'required|uuid',
            'message'              => 'required|string|max:5000',
            'headline'             => 'nullable|string|max:255',
            'link'                 => 'nullable|url|max:1000',
            'cta'                  => 'nullable|in:' . implode(',', array_keys(MetaAdCreator::CTA_OPTIONS)),
            'format'               => 'nullable|in:MOBILE_FEED_STANDARD,INSTAGRAM_STANDARD,INSTAGRAM_STORY',
        ], ['message.required' => 'O texto do anúncio é obrigatório.']);

        if ($v->fails()) {
            return [null, response()->json(['errors' => $v->errors()], 422)];
        }

        $account = ClientAdAccount::where('client_id', $task->client_id)->where('platform', 'meta_ads')->find($request->input('client_ad_account_id'));
        $image = $this->approvedImages($task)->firstWhere('id', $request->input('task_attachment_id'));
        if (!$account || !$image) {
            return [null, response()->json(['errors' => ['conta' => ['Conta ou imagem inválida para esta tarefa.']]], 422)];
        }

        return [[
            'account' => $account,
            'image'   => $image,
            'input'   => $request->only(['adset_id', 'message', 'headline', 'link', 'cta', 'format']),
        ], null];
    }
}
