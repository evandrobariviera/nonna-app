<?php

namespace App\Services\DocumentUpdate;

use App\Jobs\AnalyzeDocumentUpdateJob;
use App\Models\AiAgent;
use App\Models\DocumentUpdateRun;
use App\Models\MacroPlan;
use App\Models\Meeting;
use App\Models\Project;
use App\Services\AiService;
use App\Services\DocumentEditService;
use App\Services\MeetingText;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * "Atualizar documento com reunião" (2026-11-03). A IA lê a ATA + transcrição de uma reunião,
 * confere o HTML do planejamento/campanha/projeto e devolve sugestões de troca de trecho
 * (antes → depois) + o bloco de dados atualizado. Ela escreve seguindo as MESMAS regras da
 * skill que gerou o documento (resources/ai/skills/*-nonna.md, cópia das skills do Claude).
 *
 * Nada muda sozinho: a equipe marca quais sugestões aceita e só então vira uma versão nova
 * do documento pelo DocumentEditService (versão anterior guardada, datas/verba do bloco
 * de dados levadas pro App, histórico com quem aplicou).
 */
class DocumentUpdateService
{
    public function __construct(
        private AiService $ai,
        private DocumentEditService $documents,
    ) {}

    public static function agent(): ?AiAgent
    {
        return AiAgent::bySlug(AiAgent::SLUG_DOCUMENT_UPDATER);
    }

    public static function ownerType(MacroPlan|Project $owner): string
    {
        return $owner instanceof MacroPlan ? 'macro_plan' : 'project';
    }

    public static function kindLabel(MacroPlan|Project $owner): string
    {
        return $owner instanceof MacroPlan ? 'planejamento' : ($owner->type === 'campanha' ? 'campanha' : 'projeto');
    }

    /** Reuniões do cliente que dá pra usar (com ATA ou transcrição), as citadas na pauta primeiro. */
    public function meetingsFor(MacroPlan|Project $owner): Collection
    {
        $clientId = $owner->client_id ?? ($owner instanceof Project ? $owner->macroPlan?->client_id : null);
        if (!$clientId) {
            return collect();
        }

        $meetings = Meeting::where('client_id', $clientId)
            ->where(fn ($q) => $q->whereNotNull('ata')->where('ata', '!=', '')
                ->orWhere(fn ($q) => $q->whereNotNull('transcricao')->where('transcricao', '!=', '')))
            ->where('scheduled_at', '>=', now()->subMonths(6))
            ->orderByDesc('scheduled_at')
            ->limit(30)
            ->get(['id', 'title', 'type', 'scheduled_at', 'macro_plan_id']);

        $relation = $owner instanceof MacroPlan ? 'agendaMacroPlans' : 'agendaProjects';
        $cited = Meeting::whereIn('id', $meetings->pluck('id'))
            ->whereHas($relation, fn ($q) => $q->whereKey($owner->getKey()))
            ->pluck('id')
            ->flip();

        return $meetings
            ->each(fn ($m) => $m->cites_owner = $cited->has($m->id) || ($owner instanceof MacroPlan && $m->macro_plan_id === $owner->id))
            ->sortByDesc('cites_owner')
            ->values();
    }

    /**
     * Documentos que a reunião pode atualizar: planejamentos e campanhas/projetos do cliente
     * que têm documento e não terminaram. Citados na pauta (ou o planejamento da reunião) primeiro.
     */
    public function ownersFor(Meeting $meeting): Collection
    {
        if (!$meeting->client_id) {
            return collect();
        }

        $citedPlans    = $meeting->agendaMacroPlans->pluck('id')->push($meeting->macro_plan_id)->filter()->flip();
        $citedProjects = $meeting->agendaProjects->pluck('id')->flip();

        $plans = MacroPlan::where('client_id', $meeting->client_id)
            ->whereNotNull('document_attachment_id')
            ->where('status', '!=', 'concluido')
            ->orderByDesc('period_start')
            ->get(['id', 'title', 'client_id', 'period_start', 'period_end', 'status'])
            ->map(fn ($p) => ['owner' => $p, 'kind' => 'planejamento', 'cited' => $citedPlans->has($p->id)]);

        $projects = Project::where(fn ($q) => $q->where('client_id', $meeting->client_id)
                ->orWhereHas('macroPlan', fn ($q) => $q->where('client_id', $meeting->client_id)))
            ->whereNotNull('document_attachment_id')
            ->whereNotIn('status', ['concluido', 'cancelado'])
            ->orderBy('start_date')
            ->get(['id', 'title', 'type', 'macro_plan_id', 'client_id', 'start_date', 'end_date', 'status'])
            ->map(fn ($p) => ['owner' => $p, 'kind' => $p->type === 'campanha' ? 'campanha' : 'projeto', 'cited' => $citedProjects->has($p->id)]);

        return $plans->concat($projects)->sortByDesc('cited')->values();
    }

    /**
     * Trava de custo (2026-11-06): cada documento é conferido com cada reunião UMA vez. Conta a
     * análise em andamento, pronta, aplicada ou descartada depois de pronta; a que deu erro (a
     * IA não entregou) não conta, dá pra tentar de novo.
     */
    public static function usedRunsQuery()
    {
        return DocumentUpdateRun::query()->where(fn ($q) => $q
            ->whereIn('status', ['pronto', 'aplicado'])
            // "processando" preso (worker morreu) não trava: ver DocumentUpdateRun::isStuck()
            ->orWhere(fn ($q) => $q->where('status', 'processando')
                ->where('created_at', '>=', now()->subMinutes(DocumentUpdateRun::STUCK_AFTER_MINUTES)))
            ->orWhere(fn ($q) => $q->where('status', 'descartado')->whereNotNull('suggestions')));
    }

    public function existingRun(MacroPlan|Project $owner, Meeting $meeting): ?DocumentUpdateRun
    {
        return self::usedRunsQuery()
            ->where('owner_type', self::ownerType($owner))
            ->where('owner_id', $owner->id)
            ->where('meeting_id', $meeting->id)
            ->latest()
            ->first();
    }

    /** Cria a análise e manda pra fila (a IA leva alguns minutos com documento + transcrição). */
    public function start(MacroPlan|Project $owner, Meeting $meeting, ?string $instruction, int $userId): DocumentUpdateRun
    {
        if (!$owner->document_attachment_id) {
            throw new DomainException('Este item ainda não tem documento.');
        }
        if ($this->existingRun($owner, $meeting)) {
            throw new DomainException('Este documento já foi conferido com esta reunião — cada reunião é analisada uma vez só.');
        }
        if (blank($meeting->ata) && blank($meeting->transcricao)) {
            throw new DomainException('A reunião não tem ATA nem transcrição.');
        }

        $run = DocumentUpdateRun::create([
            'organization_id'    => $owner->organization_id,
            'owner_type'         => self::ownerType($owner),
            'owner_id'           => $owner->id,
            'meeting_id'         => $meeting->id,
            'base_attachment_id' => $owner->document_attachment_id,
            'instruction'        => filled($instruction) ? Str::limit(trim($instruction), 2000, '') : null,
            'status'             => 'processando',
            'requested_by'       => $userId,
        ]);

        AnalyzeDocumentUpdateJob::dispatch($run->id);

        return $run;
    }

    /** Roda a IA e grava as sugestões (chamado pelo job). */
    public function analyze(DocumentUpdateRun $run): void
    {
        $owner   = $run->owner() ?? throw new \RuntimeException('O planejamento/projeto não existe mais.');
        $meeting = $run->meeting ?? throw new \RuntimeException('A reunião não existe mais.');
        $agent   = self::agent() ?? throw new \RuntimeException('Nenhum agente ativo pra atualizar documentos (Agentes & IA → "Documentos — Atualizar com Reunião").');

        [, $html, $data] = $this->documents->load($owner);

        $response = $this->ai->runStructured(
            agent: $agent,
            userMessage: $this->buildMessage($owner, $meeting, $html, $data, $run->instruction),
            userId: $run->requested_by,
            clientId: $owner->client_id ?? ($owner instanceof Project ? $owner->macroPlan?->client_id : null),
            trigger: 'documento:atualizar_com_reuniao',
        );

        $suggestions = collect($response['sugestoes'] ?? [])
            ->filter(fn ($s) => is_array($s) && filled($s['trecho_atual'] ?? null))
            // estratégicas, depois criativas, depois táticas (sortBy é estável: mantém a ordem da IA no grupo)
            ->sortBy(fn ($s) => ($rank = array_search($s['impacto'] ?? null, self::IMPACTOS, true)) === false ? 2 : $rank)
            ->values()
            ->map(function (array $s, int $i) use ($html) {
                $new = preg_replace('~<script\b[^>]*>.*?</script>~is', '', (string) ($s['trecho_novo'] ?? ''));

                return [
                    'id'           => $i + 1,
                    'impacto'      => in_array($s['impacto'] ?? null, self::IMPACTOS, true) ? $s['impacto'] : 'tatico',
                    'secao'        => Str::limit((string) ($s['secao'] ?? ''), 120),
                    'tipo'         => in_array($s['tipo'] ?? null, ['alterar', 'incluir', 'remover'], true) ? $s['tipo'] : 'alterar',
                    'titulo'       => Str::limit((string) ($s['titulo'] ?? 'Mudança'), 140),
                    'motivo'       => (string) ($s['motivo'] ?? ''),
                    'evidencia'    => (string) ($s['evidencia'] ?? ''),
                    'trecho_atual' => (string) $s['trecho_atual'],
                    'trecho_novo'  => $new,
                    'aplicavel'    => HtmlPatch::locate($html, (string) $s['trecho_atual']) !== null,
                    'status'       => null,
                ];
            })
            ->all();

        $dataAfter = is_array($response['dados_novos'] ?? null) && $data !== null && $response['dados_novos'] != $data
            ? $response['dados_novos']
            : null;

        $run->update([
            'status'      => 'pronto',
            'summary'     => (string) ($response['resumo'] ?? ''),
            'strategic_review' => $this->strategicReview($response),
            'suggestions' => $suggestions,
            'data_after'  => $dataAfter,
            'data_reason' => $dataAfter ? ($response['dados_motivo'] ?? null) : null,
            'open_points' => array_values(array_filter((array) ($response['pontos_em_aberto'] ?? []), 'is_string')),
        ]);
    }

    public const VERDICTS = ['alinhado', 'ajustes', 'mudanca_de_rota', 'risco_de_desvio'];
    public const MOVES    = ['mudanca_de_rota', 'desvio', 'nova_demanda', 'criativo', 'ajuste', 'reforco'];
    public const DESTINOS = ['documento', 'aberto', 'alerta', 'fora', 'ja_esta'];
    public const IMPACTOS = ['estrategico', 'criativo', 'tatico'];

    /**
     * Leitura de fiscal + secretário: a espinha dorsal do documento, o que a reunião fez com ela
     * (rota, desvio, demanda nova, criativo, ajuste, reforço) e a pauta coberta. Normaliza o que a IA devolveu.
     */
    private function strategicReview(array $response): ?array
    {
        $line = collect((array) ($response['linha_estrategica'] ?? []))
            ->only(['objetivo', 'publico', 'posicionamento', 'mensagem_central', 'tom', 'linha_criativa', 'mecanica_canais', 'prioridades'])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => Str::limit(trim($v), 400))
            ->all();

        $str = fn ($v, int $max = 600) => is_string($v) && trim($v) !== '' ? Str::limit(trim($v), $max) : null;
        $moves = collect($response['movimentos'] ?? [])
            ->filter(fn ($m) => is_array($m) && in_array($m['tipo'] ?? null, self::MOVES, true) && filled($m['titulo'] ?? null))
            ->map(fn ($m) => [
                'tipo'            => $m['tipo'],
                'titulo'          => Str::limit((string) $m['titulo'], 140),
                'documento_diz'   => $str($m['documento_diz'] ?? null),
                'reuniao_indica'  => $str($m['reuniao_indica'] ?? null),
                'por_que_importa' => $str($m['por_que_importa'] ?? null),
                'recomendacao'    => $str($m['recomendacao'] ?? null),
                'evidencia'       => $str($m['evidencia'] ?? null, 400),
            ])
            ->sortBy(fn ($m) => array_search($m['tipo'], self::MOVES, true))
            ->values()
            ->all();

        // Secretário: todos os assuntos da reunião, cada um com seu destino — o time confere que nada se perdeu.
        $agenda = collect($response['pauta_coberta'] ?? [])
            ->filter(fn ($p) => is_array($p) && filled($p['assunto'] ?? null))
            ->map(fn ($p) => [
                'assunto'    => Str::limit(trim((string) $p['assunto']), 160),
                'destino'    => in_array($p['destino'] ?? null, self::DESTINOS, true) ? $p['destino'] : 'aberto',
                'observacao' => $str($p['observacao'] ?? null, 300),
            ])
            ->values()
            ->all();

        $verdict = in_array($response['veredito'] ?? null, self::VERDICTS, true) ? $response['veredito'] : null;
        if (!$verdict && !$line && !$moves && !$agenda) {
            return null;
        }

        return ['veredito' => $verdict, 'linha_estrategica' => $line, 'movimentos' => $moves, 'pauta_coberta' => $agenda];
    }

    /**
     * Aplica as sugestões aceitas no documento ATUAL (pode ter mudado desde a análise — por
     * isso cada trecho é procurado de novo) e salva uma versão nova.
     *
     * @param  array<int, int>  $acceptedIds
     * @return array{applied: int, failed: array<int, string>, changes: array<int, string>}
     */
    public function apply(DocumentUpdateRun $run, array $acceptedIds, bool $withData, int $userId): array
    {
        if (!$run->isReady()) {
            throw new DomainException('Essas sugestões já foram aplicadas ou descartadas.');
        }

        $owner = $run->owner() ?? abort(404);
        [$current, $html, $currentData] = $this->documents->load($owner);

        $suggestions = $run->suggestions ?? [];
        $applied = 0;
        $failed = [];
        foreach ($suggestions as &$s) {
            if (!in_array($s['id'], $acceptedIds, true)) {
                continue;
            }
            $patched = HtmlPatch::replace($html, $s['trecho_atual'], $s['trecho_novo']);
            if ($patched === null) {
                $s['status'] = 'falhou';
                $failed[] = $s['titulo'];
                continue;
            }
            $html = $patched;
            $s['status'] = 'aplicada';
            $applied++;
        }
        unset($s);

        $data = $withData && $run->data_after ? $this->mergeData($run, $current->id, $currentData) : null;
        if ($applied === 0 && $data === null) {
            throw new DomainException($failed
                ? 'Nenhuma sugestão pôde ser aplicada: o documento mudou nesses trechos desde a análise. Ajuste pelo editor.'
                : 'Marque pelo menos uma sugestão.');
        }

        $meeting = $run->meeting;
        $changes = $this->documents->save($owner, $html, $data, $userId, 'document_ai_updated',
            'reunião ' . ($meeting ? $meeting->scheduled_at->format('d/m') . ' ' . $meeting->title : '') . " · {$applied} mudança(s)");

        $run->update([
            'status'                => 'aplicado',
            'suggestions'           => $suggestions,
            'applied_attachment_id' => $owner->fresh()->document_attachment_id,
            'applied_by'            => $userId,
            'applied_at'            => now(),
        ]);

        return ['applied' => $applied, 'failed' => $failed, 'changes' => $changes];
    }

    /**
     * Bloco de dados a gravar: parte do bloco ATUAL e só leva os campos que a IA mudou em
     * relação à versão que ela leu — se alguém editou o documento no meio tempo, o que essa
     * pessoa mudou não volta atrás.
     */
    private function mergeData(DocumentUpdateRun $run, string $currentAttachmentId, ?array $currentData): ?array
    {
        if ($currentData === null) {
            return null;
        }
        if ($currentAttachmentId === $run->base_attachment_id) {
            return $run->data_after;
        }

        $owner = $run->owner();
        $base = $owner->attachments()->find($run->base_attachment_id);
        $baseData = $base ? $this->documents->readData(Storage::disk($base->disk)->get($base->disk_path) ?? '') : null;

        $merged = $currentData;
        foreach ($run->data_after as $key => $value) {
            if (($baseData[$key] ?? null) != $value) {
                $merged[$key] = $value;
            }
        }

        return $merged == $currentData ? null : $merged;
    }

    private function buildMessage(MacroPlan|Project $owner, Meeting $meeting, string $html, ?array $data, ?string $instruction): string
    {
        $kind = self::kindLabel($owner);
        $skill = match ($kind) {
            'planejamento' => 'planejamento-nonna',
            'campanha'     => 'campanha-nonna',
            default        => 'projeto-nonna',
        };

        $client = $owner->client ?? ($owner instanceof Project ? $owner->macroPlan?->client : null);
        $facts = $owner instanceof MacroPlan
            ? 'Período no App: ' . $owner->period_start?->format('d/m/Y') . ' a ' . $owner->period_end?->format('d/m/Y')
            : collect([
                $owner->type === 'campanha' ? 'Vai ao ar' : 'Início' => $owner->start_date?->format('d/m/Y'),
                'Entrega das peças' => $owner->type === 'campanha' ? $owner->pieces_due_date?->format('d/m/Y') : null,
                'Término' => $owner->end_date?->format('d/m/Y'),
            ])->filter()->map(fn ($v, $k) => "{$k} no App: {$v}")->implode("\n");

        $ata = MeetingText::toMarkdown($meeting->ata);

        return "TIPO DO DOCUMENTO: {$kind}\n"
            . "Título: {$owner->title}\n"
            . 'Cliente: ' . ($client?->displayName() ?? '—') . "\n"
            . "{$facts}\n\n"
            . "=== REGRAS DA SKILL ({$skill}) ===\n"
            . (file_get_contents(resource_path("ai/skills/{$skill}.md")) ?: '') . "\n\n"
            . "=== REUNIÃO ===\n"
            . "Título: {$meeting->title}\n"
            . "Tipo: {$meeting->typeLabel()}\n"
            . 'Data da reunião: ' . $meeting->scheduled_at->translatedFormat('d/m/Y (l)') . "\n\n"
            . 'ATA:' . "\n" . (filled($ata) ? $ata : '(sem ATA)') . "\n\n"
            . 'TRANSCRIÇÃO:' . "\n" . (filled($meeting->transcricao) ? $meeting->transcricao : '(sem transcrição)') . "\n\n"
            . (filled($instruction) ? "=== PEDIDO DA EQUIPE ===\n{$instruction}\n\n" : '')
            . "=== DADOS ATUAIS (nonna-dados) ===\n"
            . ($data !== null ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '(o documento não tem bloco de dados — devolva "dados_novos": null)') . "\n\n"
            . "=== DOCUMENTO ATUAL (HTML) ===\n"
            . HtmlPatch::forAi($html) . "\n\n"
            . 'Primeiro extraia a linha do documento, depois levante TODOS os assuntos da reunião e o que cada um '
            . 'faz com a linha (rota, desvio, demanda nova, criativo, ajuste, reforço); proponha as mudanças que importam '
            . 'e preste contas de cada assunto na pauta coberta. Não compare redação. Responda no formato JSON definido.';
    }
}
