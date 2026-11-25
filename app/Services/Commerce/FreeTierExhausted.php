<?php

namespace App\Services\Commerce;

/** A franquia grátis do Google do mês acabou e o uso pago não foi liberado (ver GoogleUsage). */
class FreeTierExhausted extends \RuntimeException
{
    public function __construct(string $sku)
    {
        parent::__construct(match ($sku) {
            'coleta' => 'A franquia grátis de coletas do Google deste mês acabou. Para continuar, um admin precisa liberar o uso pago ao pedir a coleta.',
            default  => 'A franquia grátis do Google deste mês acabou para esta consulta.',
        });
    }
}
