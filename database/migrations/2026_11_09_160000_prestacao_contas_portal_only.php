<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prestação de contas só pelo Portal (decisão 09/11/2026): o aviso não leva
 * mais link público, só o caminho do Portal, onde as notas abrem com login.
 * Atualiza o texto padrão da mensagem — só onde ninguém editou ainda.
 */
return new class extends Migration
{
    protected $connection = 'pgsql';

    private array $old = [
        'whatsapp' => "Oi {{contato}}! As notas fiscais dos anúncios de {{cliente}} referentes a {{mes}} já estão disponíveis ({{quantidade}}, total de {{valor}}).\n\nPara baixar: {{link_prestacao}}",
        'email'    => "Olá {{contato}},\n\nAs notas fiscais dos investimentos em anúncios de {{cliente}} referentes a {{mes}} já estão disponíveis.\n\nQuantidade: {{quantidade}}\nTotal: {{valor}}\n\nPara baixar as notas e ver o extrato do mês: {{link_prestacao}}\n\nQualquer dúvida, estamos à disposição.\n\nEquipe Nonna",
    ];

    private array $new = [
        'whatsapp' => "Oi {{contato}}! As notas fiscais dos anúncios de {{cliente}} referentes a {{mes}} já estão no Portal do Cliente ({{quantidade}}, total de {{valor}}).\n\nAcesse com seu login: {{link_prestacao}}",
        'email'    => "Olá {{contato}},\n\nAs notas fiscais dos investimentos em anúncios de {{cliente}} referentes a {{mes}} já estão disponíveis.\n\nQuantidade: {{quantidade}}\nTotal: {{valor}}\n\nAs notas e o extrato do mês estão no Portal do Cliente. Acesse com seu login: {{link_prestacao}}\n\nQualquer dúvida, estamos à disposição.\n\nEquipe Nonna",
    ];

    public function up(): void
    {
        foreach ($this->new as $channel => $body) {
            DB::connection('pgsql')->table('notification_templates')
                ->where('type', 'prestacao_contas')->where('channel', $channel)->where('body', $this->old[$channel])
                ->update(['body' => $body, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->old as $channel => $body) {
            DB::connection('pgsql')->table('notification_templates')
                ->where('type', 'prestacao_contas')->where('channel', $channel)->where('body', $this->new[$channel])
                ->update(['body' => $body, 'updated_at' => now()]);
        }
    }
};
