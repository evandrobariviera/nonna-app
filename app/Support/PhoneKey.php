<?php

namespace App\Support;

/**
 * Chave de comparação de telefone brasileiro: DDD + últimos 8 dígitos.
 *
 * Cada fonte manda o número de um jeito — a uazapi manda "+55 49 9908-2184"
 * (muitas vezes SEM o 9 da frente, que é como o WhatsApp registra números
 * antigos), o site manda "5549999082184", o formulário do Facebook manda
 * "+5549999082184". Comparar o texto cru nunca acha a mesma pessoa; ignorar
 * o 55 e o 9 extra acha.
 */
class PhoneKey
{
    public static function from(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        // Sem DDD (8-9 dígitos) não dá pra afirmar que é a mesma pessoa —
        // o mesmo número existe em DDDs diferentes.
        if (strlen($digits) < 10 || strlen($digits) > 11) {
            return null;
        }

        return substr($digits, 0, 2) . substr($digits, -8);
    }
}
