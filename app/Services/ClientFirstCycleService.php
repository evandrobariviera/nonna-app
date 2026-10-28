<?php

namespace App\Services;

use App\Models\Client;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\ProjectPlaybook;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Primeiro ciclo do cliente (2026-10-28, pedido do Evandro): todo cliente novo nasce com um
 * planejamento de 90 dias em execução e, dentro dele, o projeto "Onboarding" montado pelo
 * Playbook "Onboarding de Cliente" (fases + tarefas com responsável por papel funcional).
 *
 * O planejamento do kick-off (Fase 5) entra NESTE ciclo — enviando o HTML na página do
 * planejamento, as campanhas/projetos são acrescentadas (MacroPlanDocumentService::importIntoPlan).
 *
 * Marcado auto_created: enquanto ninguém mexer (toda tarefa ainda no Backlog), não impede
 * apagar cliente duplicado (Client::untouchedFirstCycle).
 */
class ClientFirstCycleService
{
    public const CYCLE_DAYS = 90;
    public const PLAYBOOK_NAME = 'Onboarding de Cliente';

    public function __construct(private TaskDraftService $drafts) {}

    public function start(Client $client): ?MacroPlan
    {
        if ($client->status === 'inactive' || $client->macroplans()->exists()) {
            return null;
        }

        try {
            return DB::connection('pgsql')->transaction(function () use ($client) {
                $start = today();

                $plan = $client->macroplans()->create([
                    'organization_id' => $client->organization_id,
                    'focus'           => 'Onboarding',
                    'period_start'    => $start,
                    'period_end'      => $start->copy()->addDays(self::CYCLE_DAYS - 1),
                    'status'          => 'em_execucao',
                    'created_by'      => Auth::id(),
                    'auto_created'    => true,
                ]);

                $project = $plan->projects()->create([
                    'organization_id' => $client->organization_id,
                    'client_id'       => $client->id,
                    'position'        => 1,
                    'title'           => 'Onboarding',
                    'type'            => 'projeto',
                    'status'          => 'em_execucao',
                    'objective'       => 'Entrada do cliente: boas-vindas, acessos, setup técnico, kick-off e primeiro planejamento.',
                    'start_date'      => $start,
                ]);

                $playbook = ProjectPlaybook::where('name', self::PLAYBOOK_NAME)->where('is_active', true)->first();
                if ($playbook) {
                    $this->drafts->applyPlaybook($project, $playbook, Auth::id());
                }

                return $plan;
            });
        } catch (\Throwable $e) {
            // Nunca derruba o cadastro do cliente por causa disso.
            Log::error('Primeiro ciclo do cliente não criado', ['client_id' => $client->id, 'error' => $e->getMessage()]);
            report($e);
            return null;
        }
    }

    /** Projeto Onboarding do cliente (o mais recente, não cancelado) — feito pelo Playbook ou com esse nome. */
    public static function onboardingProject(Client $client): ?Project
    {
        return self::lifecycleProject($client, 'Onboarding', self::PLAYBOOK_NAME);
    }

    // ── Offboarding (2026-10-28): a saída do cliente também é um projeto, pelo Playbook ──
    public const OFFBOARDING_PLAYBOOK = 'Offboarding de Cliente';

    public static function offboardingProject(Client $client): ?Project
    {
        return self::lifecycleProject($client, 'Offboarding', self::OFFBOARDING_PLAYBOOK);
    }

    /**
     * Inicia a saída: projeto "Offboarding" no planejamento aberto do cliente (exceção ao ciclo
     * fechado: sem planejamento aberto, entra no mais recente — saída não espera ciclo novo).
     * Devolve [projeto, erro].
     */
    public function startOffboarding(Client $client): array
    {
        if ($existing = self::offboardingProject($client)) {
            return [$existing, null];
        }

        $plan = MacroPlan::where('client_id', $client->id)
            ->orderByRaw("CASE status WHEN 'em_execucao' THEN 0 WHEN 'concluido' THEN 2 ELSE 1 END")
            ->orderByDesc('period_start')
            ->first();
        if (!$plan) {
            return [null, 'O cliente não tem planejamento — não há onde registrar o offboarding.'];
        }

        return [$this->createPlaybookProject($client, $plan, 'Offboarding', self::OFFBOARDING_PLAYBOOK,
            'Saída do cliente: cobranças, cancelamento das cobranças futuras, distrato, encerrar campanhas/projetos, sair das contas e inativar.'), null];
    }

    private static function lifecycleProject(Client $client, string $title, string $playbookName): ?Project
    {
        $playbookId = ProjectPlaybook::where('name', $playbookName)->value('id');

        return Project::where('client_id', $client->id)
            ->where('status', '!=', 'cancelado')
            ->where(fn ($q) => $q->where('title', $title)->when($playbookId, fn ($w) => $w->orWhere('project_playbook_id', $playbookId)))
            ->orderByDesc('created_at')
            ->first();
    }

    private function createPlaybookProject(Client $client, MacroPlan $plan, string $title, string $playbookName, ?string $objective = null): Project
    {
        return DB::connection('pgsql')->transaction(function () use ($client, $plan, $title, $playbookName, $objective) {
            $project = $plan->projects()->create([
                'organization_id' => $client->organization_id,
                'client_id'       => $client->id,
                'position'        => (int) $plan->projects()->max('position') + 1,
                'title'           => $title,
                'type'            => 'projeto',
                'status'          => 'em_execucao',
                'objective'       => $objective,
                'start_date'      => today(),
            ]);
            if ($playbook = ProjectPlaybook::where('name', $playbookName)->where('is_active', true)->first()) {
                $this->drafts->applyPlaybook($project, $playbook, Auth::id());
            }
            return $project;
        });
    }

    /**
     * Cliente antigo sem projeto Onboarding: cria no planejamento aberto dele (ou o primeiro
     * ciclo inteiro, se ele não tiver planejamento nenhum). Devolve [projeto, erro].
     */
    public function ensureOnboardingProject(Client $client): array
    {
        if ($existing = self::onboardingProject($client)) {
            return [$existing, null];
        }
        if (!$client->macroplans()->exists()) {
            $plan = $this->start($client);
            return [$plan ? self::onboardingProject($client) : null, $plan ? null : 'Não foi possível criar o primeiro ciclo.'];
        }

        $plan = MacroPlan::where('client_id', $client->id)->where('status', '!=', 'concluido')
            ->orderByRaw("CASE status WHEN 'em_execucao' THEN 0 ELSE 1 END")->first();
        if (!$plan) {
            return [null, 'O cliente não tem planejamento aberto — cadastre o planejamento do ciclo antes.'];
        }

        return [$this->createPlaybookProject($client, $plan, 'Onboarding', self::PLAYBOOK_NAME), null];
    }

    /**
     * O primeiro ciclo automático ainda intocado: auto_created, só o projeto Onboarding (ou
     * nenhum), e nenhuma tarefa saiu do Backlog. Null se não houver ou se já houve trabalho.
     */
    public static function untouched(Client $client): ?MacroPlan
    {
        $plans = $client->macroplans()->get();
        $plan  = $plans->firstWhere('auto_created', true);
        if (!$plan || $plans->count() > 1) {
            return null;
        }

        $projectIds = Project::where('macro_plan_id', $plan->id)->pluck('id');
        if ($projectIds->count() > 1) {
            return null;
        }
        // Tarefa do cliente fora deste ciclo, ou tarefa do ciclo que já andou = houve trabalho.
        $tasks = Task::where('client_id', $client->id)->get(['id', 'project_id', 'status']);
        $worked = $tasks->contains(fn ($t) => !$projectIds->contains($t->project_id) || $t->status !== 'backlog');

        return $worked ? null : $plan;
    }

    /** Apaga o primeiro ciclo intocado (tarefas, projeto, planejamento) — só pra apagar duplicata. */
    public static function deleteUntouched(Client $client): void
    {
        $plan = self::untouched($client);
        if (!$plan) {
            return;
        }

        DB::connection('pgsql')->transaction(function () use ($plan) {
            $projectIds = Project::where('macro_plan_id', $plan->id)->pluck('id');
            Task::whereIn('project_id', $projectIds)->get()->each->delete();
            Project::whereIn('id', $projectIds)->get()->each->delete();
            $plan->delete();
        });
    }
}
