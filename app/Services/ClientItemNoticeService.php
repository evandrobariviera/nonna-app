<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientItemNotice;
use App\Models\MacroPlan;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Avisa o cliente quando um Planejamento, Campanha ou Projeto entra em execução
 * (decisão do Evandro, 30/10). Só passagem de status: criar já em execução, o ciclo
 * automático do onboarding e a importação do ClickUp não avisam.
 *
 * Mensagens Padrão: "planejamento_ativo" e "item_ativo". O aviso do planejamento já
 * lista as campanhas, então as que entraram junto com ele não saem de novo.
 */
class ClientItemNoticeService
{
    private static bool $muted = false;

    /** Importação do ClickUp espelha status: não é uma decisão do time de avisar o cliente. */
    public static function mute(): void
    {
        self::$muted = true;
    }

    public function schedule(MacroPlan|Project $item): void
    {
        if (self::$muted || !$item->client_id) {
            return;
        }

        // Falha aqui nunca pode impedir o time de mudar o status.
        try {
            ClientItemNotice::firstOrCreate(
                ['item_type' => $item->getMorphClass(), 'item_id' => $item->id],
                [
                    'organization_id' => $item->organization_id,
                    'client_id'       => $item->client_id,
                    'token'           => Str::random(40),
                    'due_at'          => now()->addMinutes(ClientItemNotice::DELAY_MINUTES),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning("ClientItemNoticeService: não agendei o aviso de {$item->id}: {$e->getMessage()}");
        }
    }

    /** Saiu de execução antes do envio (clique errado): o cliente não fica sabendo. */
    public function cancel(MacroPlan|Project $item): void
    {
        try {
            ClientItemNotice::where('item_type', $item->getMorphClass())
                ->where('item_id', $item->id)
                ->whereNull('sent_at')
                ->delete();
        } catch (\Throwable $e) {
            Log::warning("ClientItemNoticeService: não cancelei o aviso de {$item->id}: {$e->getMessage()}");
        }
    }

    /** @return int  quantos avisos saíram da fila */
    public function sendDue(): int
    {
        $due = ClientItemNotice::whereNull('sent_at')->where('due_at', '<=', now())->orderBy('due_at')->get();

        foreach ($due->groupBy('client_id') as $clientId => $notices) {
            // Marca antes: mensagem repetida no WhatsApp do cliente é pior que uma falha,
            // que fica no Histórico de Envios.
            ClientItemNotice::whereIn('id', $notices->pluck('id'))->update(['sent_at' => now()]);

            $client = Client::withoutGlobalScopes()->find($clientId);

            if ($client && $client->status !== 'inactive' && !$client->is_internal) {
                $this->sendForClient($client, $notices);
            }
        }

        return $due->count();
    }

    private function sendForClient(Client $client, Collection $notices): void
    {
        $items = $notices->mapWithKeys(fn (ClientItemNotice $n) => [$n->token => $n->resolveItem()])
            ->filter(fn ($item) => $item?->status === 'em_execucao');

        $dispatcher = app(NotificationDispatchService::class);
        $plans      = $items->filter(fn ($item) => $item instanceof MacroPlan);
        $projects   = $items->filter(fn ($item) => $item instanceof Project);

        foreach ($plans as $token => $plan) {
            $dispatcher->send('planejamento_ativo', $client, [
                'planejamento'   => $plan->clientTitle(),
                'periodo'        => $plan->period_start?->format('d/m/Y') . ' a ' . $plan->period_end?->format('d/m/Y'),
                'lista_itens'    => $this->planItems($plan),
                'link_documento' => route('public-item.show', $token),
                'link_portal'    => route('portal.projects.index'),
            ], related: $plan);
        }

        $loose = $projects->reject(fn (Project $p) => $plans->contains('id', $p->macro_plan_id));

        if ($loose->isNotEmpty()) {
            $dispatcher->send('item_ativo', $client, [
                'lista_itens' => $loose->map(fn (Project $p, string $token) => $this->itemBlock($p, $token))->implode("\n\n"),
                'link_portal' => route('portal.projects.index'),
            ], related: $loose->count() === 1 ? $loose->first() : null);
        }
    }

    private function planItems(MacroPlan $plan): string
    {
        $items = Project::withoutGlobalScopes()
            ->where('macro_plan_id', $plan->id)
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->orderByRaw('start_date NULLS LAST')
            ->get();

        if ($items->isEmpty()) {
            return 'As campanhas e os projetos deste ciclo estão no documento.';
        }

        return "O que vem por aí:\n" . $items->map(fn (Project $p) => '• ' . $this->itemLine($p))->implode("\n");
    }

    private function itemBlock(Project $project, string $token): string
    {
        $plan = $project->macro_plan_id ? MacroPlan::withoutGlobalScopes()->find($project->macro_plan_id) : null;

        return collect([
            '• ' . $this->itemLine($project),
            $plan ? 'Faz parte do ' . $plan->clientTitle() : null,
            'Veja os detalhes: ' . route('public-item.show', $token),
        ])->filter()->implode("\n");
    }

    private function itemLine(Project $project): string
    {
        $period = match (true) {
            $project->start_date && $project->end_date => $project->start_date->format('d/m') . ' a ' . $project->end_date->format('d/m'),
            (bool) $project->start_date                => 'a partir de ' . $project->start_date->format('d/m'),
            (bool) $project->end_date                  => 'até ' . $project->end_date->format('d/m'),
            default                                    => null,
        };

        return $project->typeLabel() . ' ' . $project->title . ($period ? " ({$period})" : '');
    }
}
