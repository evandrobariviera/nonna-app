<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\CampaignDailyCheck;
use App\Models\CampaignLog;
use App\Models\Client;
use App\Models\ClientAdAccount;
use App\Services\CampaignCentral\OptimizationLadder;
use App\Services\CampaignCentral\TodayBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Central de Campanhas: a prancheta do gestor de tráfego. As abas Campanhas
 * (CampaignController) e Verbas (OrcamentoController) já existiam e só
 * ganharam a barra da Central; aqui ficam Hoje e Otimizações.
 */
class CampaignCentralController extends Controller
{
    public function today(Request $request): View
    {
        [$clientId, $mine] = $this->scope($request);

        $board = (new TodayBoard($clientId, $mine ? Auth::id() : null))->build(Auth::id());

        return view('campaign-central.today', $board + [
            'clients'  => $this->clientOptions(),
            'clientId' => $clientId,
            'mine'     => $mine,
        ]);
    }

    public function optimizations(Request $request): View
    {
        [$clientId, $mine] = $this->scope($request);

        $accountIds = ClientAdAccount::query()
            ->whereHas('client', fn ($q) => $q->where('status', '!=', 'inactive'))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($mine, fn ($q) => $q->where('responsible_user_id', Auth::id()))
            ->pluck('id');

        $logs = CampaignLog::with(['user:id,name', 'adAccount.client'])
            ->whereIn('client_ad_account_id', $accountIds)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Para o "Nova otimização": campanhas ativas do escopo, as mais
        // atrasadas primeiro (mesma ordem da listagem de Campanhas).
        $campaigns = AdCampaign::with('adAccount.client:id,company_name,nickname')
            ->whereIn('client_ad_account_id', $accountIds)
            ->where('status', 'active')
            ->orderByRaw('last_optimized_at ASC NULLS FIRST')
            ->get(['id', 'name', 'client_ad_account_id', 'last_optimized_at', 'optimization_tier']);

        return view('campaign-central.optimizations', [
            'logs'          => $logs,
            'campaigns'     => $campaigns,
            'overdueCount'  => $campaigns->filter->isOptimizationOverdue()->count(),
            'check'         => CampaignDailyCheck::forToday(Auth::id()),
            'clients'       => $this->clientOptions(),
            'clientId'      => $clientId,
            'mine'          => $mine,
        ]);
    }

    /** Marca/desmarca um item da conferência diária do gestor. */
    public function toggleCheck(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item'    => 'required|in:' . implode(',', array_keys(CampaignDailyCheck::$items)),
            'checked' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $check = CampaignDailyCheck::firstOrNew(['user_id' => Auth::id(), 'check_date' => today()->toDateString()]);
        $items = collect($check->items ?? [])
            ->reject(fn ($i) => $i === $request->input('item'))
            ->when($request->boolean('checked'), fn ($c) => $c->push($request->input('item')))
            ->values()
            ->all();
        $check->items = $items;
        $check->save();

        return response()->json(['items' => $items]);
    }

    /** Números de cada degrau da escada, para o modal de otimização. */
    public function ladder(AdCampaign $campaign): JsonResponse
    {
        return response()->json([
            'campaign' => [
                'id'     => $campaign->id,
                'name'   => $campaign->name,
                'client' => $campaign->adAccount?->client?->displayName(),
                'locked' => $campaign->optimization_locked_reason,
            ],
            'steps' => collect(OptimizationLadder::$steps)->map(fn ($s, $key) => [
                'key'      => $key,
                'question' => $s[0],
                'hint'     => $s[1],
                'owner'    => $s[2],
            ])->values(),
            'facts' => OptimizationLadder::facts($campaign),
        ]);
    }

    public function storeLadder(Request $request, AdCampaign $campaign): JsonResponse
    {
        $keys = array_keys(OptimizationLadder::$steps);
        $validator = Validator::make($request->all(), [
            'answers'   => 'required|array|min:1',
            'answers.*' => 'boolean',
            'comment'   => 'required|string|max:2000',
        ], [
            'comment.required' => 'Escreva o que foi feito ou decidido.',
        ]);
        $validator->after(function ($v) use ($request, $keys) {
            // Respostas na ordem da escada e parando no primeiro "não".
            $answers = (array) $request->input('answers');
            $expected = array_slice($keys, 0, count($answers));
            if (array_keys($answers) !== $expected) {
                $v->errors()->add('answers', 'Responda os degraus na ordem.');
            }
            $firstNo = array_search(false, array_map('boolval', $answers), true);
            if ($firstNo !== false && $firstNo !== array_key_last($answers)) {
                $v->errors()->add('answers', 'A escada para no primeiro "não".');
            }
        });
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $answers = array_map('boolval', (array) $request->input('answers'));
        $log = OptimizationLadder::record($campaign, $answers, $request->input('comment'), Auth::id());

        return response()->json(['ok' => true, 'label' => $log->metadata['escada']['label']]);
    }

    /** @return array{0: ?string, 1: bool} cliente escolhido e "só os meus". */
    private function scope(Request $request): array
    {
        $client = $request->get('client_id');

        return [
            $client && $client !== 'meus' ? $client : null,
            $client === 'meus',
        ];
    }

    private function clientOptions()
    {
        return Client::where('status', '!=', 'inactive')
            ->whereHas('adAccounts')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'nickname']);
    }
}
