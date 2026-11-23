<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\MacroPlan;
use App\Models\Project;
use App\Models\FunctionalRole;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Do planejamento para as campanhas e projetos de verdade (23/11). Cada item da seção 04 do
 * documento do planejamento vira um Project (campanha ou projeto) com o seu documento em campos,
 * já preenchido com o que herdou do plano (nome, datas, objetivo, metas, verba...).
 *
 * Idempotente: o documento criado guarda de onde veio (source_document_id + source_item_id);
 * rodar de novo só cria os itens que ainda não viraram campanha/projeto. Depois de criada, a
 * campanha é dona do próprio documento: mudar o planejamento não sobrescreve nada.
 *
 * Ao criar, avisa no sino (só informação, sem pendência) o responsável do planejamento, o
 * responsável do cliente e o Gestor de Projetos, para lerem o plano e os documentos (23/11:
 * saiu a pendência de leitura da Revisão Interna; a leitura é depois das campanhas criadas).
 */
class PlanItemsLauncher
{
    /** Status do planejamento em que dá para criar (só depois da revisão interna). */
    public const ALLOWED_STATUSES = ['aprovacao', 'em_execucao'];

    public function __construct(private DocumentEditor $editor) {}

    /** item id → Project já criado a partir dele. */
    public function created(Document $planDoc): array
    {
        $docs = Document::where('source_document_id', $planDoc->id)
            ->where('documentable_type', (new Project)->getMorphClass())
            ->get(['source_item_id', 'documentable_id']);
        $projects = Project::whereIn('id', $docs->pluck('documentable_id'))->get()->keyBy('id');

        $out = [];
        foreach ($docs as $doc) {
            if ($project = $projects->get($doc->documentable_id)) {
                $out[$doc->source_item_id] = $project;
            }
        }

        return $out;
    }

    /** Por que não dá para criar agora (null = pode). */
    public function blockReason(MacroPlan $plan, Document $planDoc): ?string
    {
        if (!in_array($plan->status, self::ALLOWED_STATUSES, true)) {
            return 'As campanhas e projetos são criados quando o planejamento chega em Aprovação: mude a situação direto de Em Planejamento para Aprovação.';
        }
        if ($planDoc->schema()->hasBlockingIssues($planDoc->content)) {
            return 'O documento ainda tem regra do modelo quebrada.';
        }

        return null;
    }

    /** @return array{created: int, skipped: int} */
    public function launch(MacroPlan $plan, Document $planDoc, ?int $userId): array
    {
        if ($reason = $this->blockReason($plan, $planDoc)) {
            throw new \InvalidArgumentException($reason);
        }

        $content  = $planDoc->content;
        $metas    = collect($content['metas'] ?? [])->keyBy('id');
        $itens    = collect($content['itens'] ?? [])->keyBy('id');
        $existing = $this->created($planDoc);
        $position = (int) $plan->projects()->max('position');
        $created  = 0;
        $names    = [];

        DB::connection('pgsql')->transaction(function () use ($plan, $planDoc, $metas, $itens, $existing, $userId, &$position, &$created, &$names) {
            foreach ($itens as $id => $item) {
                if (isset($existing[$id]) || blank($item['nome'] ?? null)) {
                    continue;
                }
                $isCampaign = ($item['tipo'] ?? 'campanha') === 'campanha';
                $metaNames  = collect($item['metas'] ?? [])->map(fn ($m) => $metas[$m]['nome'] ?? null)->filter()->implode(', ');

                $project = $plan->projects()->create([
                    'client_id'  => $plan->client_id,
                    'position'   => ++$position,
                    'title'      => $item['nome'],
                    'type'       => $isCampaign ? 'campanha' : 'projeto',
                    'objective'  => $item['objetivo'] ?? null,
                    'start_date' => $item['inicio'] ?? null,
                    'end_date'   => $item['fim'] ?? null,
                    'status'     => 'em_planejamento',
                    'disciplines' => [],
                    'created_by' => $userId,
                ]);

                $prefill = $isCampaign
                    ? $this->campaignPrefill($item, $metaNames, $metas)
                    : $this->projectPrefill($item, $metaNames, $itens);

                $this->editor->create($isCampaign ? 'campanha' : 'projeto', $plan->client_id, $project->getMorphClass(), $project->id,
                    $userId, $prefill, 'planejamento', [$planDoc->id, $id]);
                $created++;
                $names[] = $item['nome'];
            }
        });

        if ($created) {
            $this->notifyTeam($plan, $names, $userId);
        }

        return ['created' => $created, 'skipped' => $itens->count() - $created];
    }

    /** Aviso de leitura: campanhas e projetos criados (kind 'planejamento_lancado', só informação). */
    private function notifyTeam(MacroPlan $plan, array $names, ?int $userId): void
    {
        $ids = $plan->discussionRecipientIds()
            ->merge(FunctionalRole::where('key', 'gestor_projetos')->first()?->users->pluck('id') ?? [])
            ->filter()->unique()->reject(fn ($id) => (int) $id === (int) $userId);
        if ($ids->isEmpty()) {
            return;
        }
        $plan->loadMissing('client');
        $count = count($names);
        app(NotificationService::class)->notifyUsers(
            User::whereIn('id', $ids)->get(),
            'planejamento_lancado',
            ($count === 1 ? '1 campanha/projeto criado' : "{$count} campanhas e projetos criados") . ' · ' . ($plan->client?->company_name ?? ''),
            'Leia o planejamento e o documento de cada um: ' . implode(', ', $names) . '.',
            route('macroplans.edit', [$plan, 'bloco' => 'bloco3']),
            $plan,
            $plan->organization_id,
        );
    }

    private function campaignPrefill(array $item, string $metaNames, $metas): array
    {
        $first = $metas[$item['metas'][0] ?? null] ?? null;
        $prefill = [
            'nome'              => $item['nome'],
            'inicio'            => $item['inicio'] ?? null,
            'fim'               => $item['fim'] ?? null,
            'objetivo'          => $item['objetivo'] ?? null,
            'meta_planejamento' => $metaNames ?: null,
            'metrica'           => $item['metrica'] ?? null,
            'checkin_dias'      => 15,
        ];
        // O número de hoje e a fonte da meta que a campanha carrega são o ponto de partida.
        if ($first) {
            $prefill['metrica_hoje']  = $first['hoje'] ?? null;
            $prefill['metrica_fonte'] = $first['fonte'] ?? null;
        }
        // Plataformas do plano viram linhas de tráfego pago (verba vai na primeira; a divisão é decidida aqui).
        $plataformas = array_values(array_filter(array_map('trim', preg_split('/[,;\/]| e /u', (string) ($item['plataformas'] ?? '')))));
        if ($plataformas) {
            $schema = DocumentSchema::for('campanha');
            $prefill['midia'] = array_map(fn ($p, $i) => array_merge($schema->newItem('midia'), [
                'plataforma'   => $p,
                'verba_mensal' => $i === 0 ? ($item['verba_mensal'] ?? null) : null,
            ]), $plataformas, array_keys($plataformas));
        }

        return $prefill;
    }

    private function projectPrefill(array $item, string $metaNames, $itens): array
    {
        $prepara = collect($item['prepara'] ?? [])->map(fn ($i) => $itens[$i]['nome'] ?? null)->filter()->implode(', ');

        return [
            'nome'          => $item['nome'],
            'inicio'        => $item['inicio'] ?? null,
            'fim'           => $item['fim'] ?? null,
            'objetivo'      => $item['objetivo'] ?? null,
            'carrega'       => $metaNames ?: ($prepara ? "Prepara o terreno para {$prepara}" : null),
            'pronto_quando' => $item['pronto_quando'] ?? null,
            'beneficio'     => $item['beneficio'] ?? null,
            'conferir_em'   => $item['beneficio_em'] ?? null,
        ];
    }
}
