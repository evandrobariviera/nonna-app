<?php

namespace App\Console\Commands;

use App\Models\InternalNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Faxina do sino — apaga a luz do aviso que perdeu o sentido sozinho (status "expirado").
 *
 * Levantamento de 12/11/2026: ~44 dos 170 avisos abertos já não serviam pra nada (lembrete
 * de reunião que já passou, "pendência resolvida" de semanas atrás...) e nada expirava, então
 * o sino virava depósito e o time parava de olhar. Só mexe em aviso "novo" (o lido já saiu
 * do sino). Não toca em: "Li a ATA" (é a confirmação de leitura), pendência aberta (fecha
 * sozinha quando a pendência é resolvida — PendencyService) e fila de setor (quem fecha é a
 * fila, ex.: Mídia Paga). Roda de hora em hora; pode rodar à vontade, não duplica nada.
 */
class TidyNotifications extends Command
{
    protected $signature = 'notifications:tidy {--dry : só mostra quantos expirariam}';

    protected $description = 'Expira avisos do sino que perderam o sentido (reunião passou, tarefa fechou, informativo velho)';

    private const INFO_DAYS = 3;
    private const CONVERSATION_DAYS = 14;
    private const ANY_DAYS = 30;

    public function handle(): int
    {
        // "Pendência resolvida" gravada antes de 12/11 com o kind 'pendencia' — passa pro kind
        // de informativo, senão cai em "Pede você" no sino. Idempotente (depois não acha mais nada).
        if (! $this->option('dry')) {
            $fixed = DB::connection('pgsql')->table('internal_notifications')
                ->where('kind', 'pendencia')
                ->where('title', 'like', 'Pendência resolvida:%')
                ->update(['kind' => InternalNotification::KIND_PENDENCY_RESOLVED]);
            if ($fixed) {
                $this->line("  'Pendência resolvida' antiga passou a informativo: {$fixed}");
            }
        }

        $base = fn () => DB::connection('pgsql')->table('internal_notifications')->where('status', 'novo');
        $closedTask = ['concluido', 'cancelado'];

        $rules = [
            // Lembrete de reunião depois que a reunião começou.
            'lembrete de reunião que já passou' => $base()
                ->whereIn('kind', InternalNotification::AGENDA_KINDS)
                ->where('source_type', 'Meeting')
                ->whereExists(fn ($q) => $q->from('meetings')
                    ->whereRaw('meetings.id::text = internal_notifications.source_id')
                    ->where('meetings.scheduled_at', '<', now())),

            // Aviso de tarefa já concluída/cancelada (ex.: "Cliente pediu ajuste" numa tarefa
            // que já fechou). Fica de fora o "criativo pronto": a tarefa conclui no despacho e
            // o material ainda está esperando o Tráfego/Web.
            'aviso de tarefa já encerrada' => $base()
                ->where('source_type', 'Task')
                ->whereNotIn('kind', ['criativo_pronto_campanha', 'criativo_pronto_dev'])
                ->whereExists(fn ($q) => $q->from('tasks')
                    ->whereRaw('tasks.id::text = internal_notifications.source_id')
                    ->whereIn('tasks.status', $closedTask)),

            // Comentário de campanha/projeto já encerrado.
            'comentário de item encerrado' => $base()
                ->whereIn('kind', InternalNotification::CONVERSATION_KINDS)
                ->where('source_type', 'Project')
                ->whereExists(fn ($q) => $q->from('projects')
                    ->whereRaw('projects.id::text = internal_notifications.source_id')
                    ->whereIn('projects.status', $closedTask)),

            'conversa com mais de ' . self::CONVERSATION_DAYS . ' dias' => $base()
                ->whereIn('kind', InternalNotification::CONVERSATION_KINDS)
                ->where('generated_at', '<', now()->subDays(self::CONVERSATION_DAYS)),

            // Informativo velho. Inclui os "Pendência resolvida" de antes de 12/11, que saíam
            // com o kind 'pendencia'.
            'informativo com mais de ' . self::INFO_DAYS . ' dias' => $base()
                ->where('generated_at', '<', now()->subDays(self::INFO_DAYS))
                ->where(fn ($q) => $q->whereIn('kind', InternalNotification::INFO_KINDS)
                    ->orWhere(fn ($q) => $q->where('kind', 'pendencia')->where('title', 'like', 'Pendência resolvida:%'))),

            // Rede de segurança: qualquer outro aviso parado há mais de 30 dias.
            'qualquer aviso com mais de ' . self::ANY_DAYS . ' dias' => $base()
                ->where('generated_at', '<', now()->subDays(self::ANY_DAYS))
                ->whereNotIn('kind', [
                    InternalNotification::KIND_ATA, 'pendencia', ...InternalNotification::QUEUE_KINDS,
                ]),
        ];

        $total = 0;
        foreach ($rules as $label => $query) {
            $n = $this->option('dry')
                ? $query->count()
                : $query->update(['status' => 'expirado', 'updated_at' => now()]);
            $total += $n;
            if ($n) {
                $this->line("  {$label}: {$n}");
            }
        }

        $this->info(($this->option('dry') ? 'Expirariam' : 'Expirados') . ": {$total}");

        return self::SUCCESS;
    }
}
