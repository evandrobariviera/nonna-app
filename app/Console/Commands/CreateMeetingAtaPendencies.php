<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\Pendency;
use App\Services\PendencyService;
use App\Support\BusinessTime;
use Illuminate\Console\Command;

/**
 * Reunião passou do horário sem ATA → pendência "Registrar a ATA" pro organizador
 * (decisão do Evandro, 2026-10-22 — substitui a tarefa "Atualização da ATA", que nascia na
 * troca de status). A pendência se resolve sozinha quando a ATA é preenchida
 * (MeetingObserver). Agendado a cada 15 min (routes/console.php).
 *
 * Só olha reuniões que terminaram nos últimos 2 dias: não abre pendência pro histórico
 * antigo. Uma pendência por reunião, nunca recriada (nem se tiver sido cancelada).
 */
class CreateMeetingAtaPendencies extends Command
{
    protected $signature = 'meetings:ata-pendencies {--dry : Só lista o que criaria}';

    protected $description = 'Abre a pendência "Registrar a ATA" pro organizador das reuniões que já terminaram sem ATA';

    // Evento ou captação externa não tem ATA.
    private const SKIP_TYPES = ['evento_captacao'];

    public function handle(PendencyService $pendencies): int
    {
        $meetings = Meeting::withoutGlobalScopes()
            ->whereNull('ata')
            ->whereNotNull('organized_by')
            ->whereNotIn('status', ['cancelada', 'realizada'])
            ->whereNotIn('type', self::SKIP_TYPES)
            ->whereBetween('scheduled_at', [now()->subDays(2)->subHours(8), now()])
            ->whereNotExists(fn ($q) => $q->from('pendencies')
                ->where('pendable_type', 'meeting')
                ->whereColumn('pendable_id', 'meetings.id')
                ->where('title', 'like', Meeting::ATA_PENDENCY_PREFIX . '%'))
            ->with('client')
            ->get()
            ->filter(fn (Meeting $m) => $m->endsAt()->lte(now()) && $m->endsAt()->gte(now()->subDays(2)));

        foreach ($meetings as $meeting) {
            $title = Meeting::ATA_PENDENCY_PREFIX . ' — ' . $meeting->title;
            $this->line("  + {$title} (organizador {$meeting->organized_by})");
            if ($this->option('dry')) {
                continue;
            }

            $organizer = (int) $meeting->organized_by;
            $pendencies->create([
                'organization_id' => $meeting->organization_id,
                'title'           => mb_substr($title, 0, 500),
                'description'     => 'A reunião já aconteceu. Gere a ATA pela transcrição (botão "Gerar ATA com IA" na reunião) ou escreva à mão — a pendência se resolve sozinha quando a ATA for salva.',
                'kind'            => 'outro',
                'owed_by'         => 'agencia',
                'owed_user_id'    => $organizer,
                'owner_id'        => $organizer,
                'requested_by'    => $organizer,
                'due_date'        => BusinessTime::nextBusinessDay(now()->addDay())->toDateString(),
            ], $meeting, $organizer, notifyActor: true);
        }

        $this->info($meetings->count() . ' pendência(s) ' . ($this->option('dry') ? 'a criar.' : 'criada(s).'));

        return self::SUCCESS;
    }
}
