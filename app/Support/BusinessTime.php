<?php

namespace App\Support;

use Carbon\CarbonInterface;

class BusinessTime
{
    /**
     * Segundos úteis entre dois instantes — sábado e domingo são descontados
     * por completo, dias de semana contam as 24h corridas (sem recorte de
     * horário de expediente, só exclusão de fim de semana).
     */
    public static function secondsBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $seconds = 0;
        $cursor = $start->copy();

        while ($cursor->lessThan($end)) {
            $dayEnd = $cursor->copy()->endOfDay()->addSecond();
            $dayEnd = $dayEnd->greaterThan($end) ? $end->copy() : $dayEnd;

            if (!$cursor->isWeekend()) {
                $seconds += $cursor->diffInSeconds($dayEnd);
            }

            $cursor = $dayEnd;
        }

        return $seconds;
    }

    /**
     * Avança $date até o próximo dia útil (pula sábado/domingo). Se $date já cai num
     * dia útil, ela é retornada como está — "próximo dia útil" aqui é sobre não deixar
     * a data cair num fim de semana, não sobre sempre avançar pelo menos 1 dia.
     */
    public static function nextBusinessDay(CarbonInterface $date): CarbonInterface
    {
        $result = $date->copy();

        while ($result->isWeekend()) {
            $result = $result->addDay();
        }

        return $result;
    }

    public static function humanize(int $seconds): string
    {
        if ($seconds < 3600) {
            return round($seconds / 60) . 'min';
        }

        $hours = $seconds / 3600;
        if ($hours < 24) {
            return round($hours, 1) . 'h';
        }

        return round($hours / 24, 1) . ' dias úteis';
    }
}
