<?php

namespace App\Services\Agent;

use App\Models\AgentCreditGrant;
use App\Models\AgentCreditLimit;
use App\Models\AiAgent;
use App\Models\AiTokenUsage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Créditos semanais do Agente Nonna. Só o chat do agente gasta crédito (as automações
 * com IA não). O gasto vem do custo real já registrado em ai_token_usage, convertido:
 * US$1 = 1.000 créditos — o time vê só créditos, nunca dinheiro. Semana começa na
 * segunda 00:00. A resposta em andamento sempre termina; o bloqueio vale pra próxima
 * pergunta (AgentController::send).
 */
class AgentCredits
{
    public const PER_USD = 1000;
    public const DEFAULT_WEEKLY = 1000; // = US$1 por pessoa por semana (decisão 17/11)
    public const WARN_AT = 0.8;

    public static function weekStart(): Carbon
    {
        return now()->startOfWeek(Carbon::MONDAY);
    }

    /** @return array{limit:int, extra:int, total:int, used:int, remaining:int, pct:int, warn:bool, blocked:bool, renews:string} */
    public static function status(User $user): array
    {
        return self::forUsers(collect([$user]))[$user->id];
    }

    /** Status de várias pessoas com 3 consultas no total (tela de Uso & Custos). */
    public static function forUsers(Collection $users): array
    {
        $ids   = $users->pluck('id')->all();
        $week  = self::weekStart();
        $agent = AiAgent::where('slug', AiAgent::SLUG_NONNA_AGENT)->value('id');

        $limits = AgentCreditLimit::whereIn('user_id', $ids)->pluck('weekly_credits', 'user_id');
        $extras = AgentCreditGrant::whereIn('user_id', $ids)->whereDate('week_start', $week->toDateString())
            ->groupBy('user_id')->selectRaw('user_id, sum(credits) as total')->pluck('total', 'user_id');
        $spent  = $agent
            ? AiTokenUsage::where('agent_id', $agent)->whereIn('user_id', $ids)->where('created_at', '>=', $week)
                ->groupBy('user_id')->selectRaw('user_id, sum(estimated_cost_usd) as usd')->pluck('usd', 'user_id')
            : collect();

        $renews = $week->copy()->addWeek()->locale('pt_BR')->isoFormat('dddd, DD/MM');
        $out = [];
        foreach ($ids as $id) {
            $limit = (int) ($limits[$id] ?? self::DEFAULT_WEEKLY);
            $extra = (int) ($extras[$id] ?? 0);
            $total = $limit + $extra;
            $used  = (int) ceil(((float) ($spent[$id] ?? 0)) * self::PER_USD);
            $out[$id] = [
                'limit'     => $limit,
                'extra'     => $extra,
                'total'     => $total,
                'used'      => $used,
                'remaining' => max(0, $total - $used),
                'pct'       => $total > 0 ? min(100, (int) round(100 * $used / $total)) : 100,
                'warn'      => $total > 0 && $used >= $total * self::WARN_AT,
                'blocked'   => $used >= $total,
                'renews'    => $renews,
            ];
        }

        return $out;
    }

    public static function blockedMessage(array $status): string
    {
        return "Seus créditos do Agente Nonna desta semana acabaram ({$status['used']} de {$status['total']}). "
            . "Eles renovam na {$status['renews']}. Se precisar antes, peça crédito extra a um administrador.";
    }
}
