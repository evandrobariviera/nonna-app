<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enxuga o texto das notificações de sino de oportunidade ganha/perdida — o
 * título do card já mostra o nome da oportunidade, então a mensagem não precisa
 * repetir `"{opportunity_title}" ({client_name})` (que fica pior ainda quando o
 * nome da oportunidade e o do cliente são iguais).
 *
 * Só toca automações que ainda estão com a mensagem original do seed
 * (2026_08_31_000002) — se o usuário já editou pela tela de Automações, respeita.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    private const CHANGES = [
        'Oportunidade Ganha → Notificar time' => [
            'from' => 'Oportunidade "{opportunity_title}" ({client_name}) fechada como GANHA — tipo: {opportunity_type}. Fee proposto: R$ {proposed_fee}.',
            'to'   => 'Fechada como GANHA — tipo: {opportunity_type}. Fee proposto: R$ {proposed_fee}.',
        ],
        'Oportunidade Perdida → Notificar time' => [
            'from' => 'Oportunidade "{opportunity_title}" ({client_name}) marcada como PERDIDA — tipo: {opportunity_type}.',
            'to'   => 'Marcada como PERDIDA — tipo: {opportunity_type}.',
        ],
    ];

    public function up(): void
    {
        $this->swap(fn ($c) => $c['from'], fn ($c) => $c['to']);
    }

    public function down(): void
    {
        $this->swap(fn ($c) => $c['to'], fn ($c) => $c['from']);
    }

    private function swap(callable $expected, callable $new): void
    {
        $db = DB::connection('pgsql');

        foreach (self::CHANGES as $name => $change) {
            $row = $db->table('automations')->where('name', $name)->first();
            if (!$row) {
                continue;
            }

            $config = json_decode($row->action_config ?: '{}', true) ?: [];
            if (($config['message'] ?? null) !== $expected($change)) {
                continue; // já editado — não mexe
            }

            $config['message'] = $new($change);
            $db->table('automations')->where('id', $row->id)->update([
                'action_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at'    => now(),
            ]);
        }
    }
};
