<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Aviso ao cliente de que um Planejamento ou uma Campanha/Projeto entrou em execução.
 * Fica na fila por DELAY_MINUTES (ClientItemNoticeService): dá tempo de desfazer um clique
 * errado e junta tudo do mesmo cliente numa mensagem só. O token é o link sem senha.
 */
class ClientItemNotice extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    public const DELAY_MINUTES = 60;

    protected $fillable = ['organization_id', 'client_id', 'item_type', 'item_id', 'token', 'due_at', 'sent_at'];

    protected $casts = [
        'due_at'  => 'datetime',
        'sent_at' => 'datetime',
    ];

    // Sem morphTo: o link público e o agendador rodam fora do contexto da organização.
    public function resolveItem(): MacroPlan|Project|null
    {
        $class = $this->item_type === 'macro_plan' ? MacroPlan::class : Project::class;

        return $class::withoutGlobalScopes()->find($this->item_id);
    }
}
