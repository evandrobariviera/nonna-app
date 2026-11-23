<?php

namespace App\Services\Documents;

use App\Models\MacroPlan;
use App\Models\MacroPlanAttachment;
use App\Models\Meeting;
use App\Models\MeetingAttachment;
use App\Services\Tasks\TaskAssistantContext;

/**
 * As fontes que o agente pode ler para montar o rascunho do planejamento (23/11): reuniões do
 * cliente (ATA + transcrição), o planejamento anterior com o fechamento das campanhas, o
 * Briefing do cliente e anexos (do planejamento, das reuniões, ou enviados na hora). O
 * estrategista escolhe quais; nada é lido sem ser marcado.
 *
 * Chave de cada fonte: meeting:{id} · plan:{id} · briefing · att:macro:{id} · att:meeting:{id}
 */
class PlanningSources
{
    /** Tipos de reunião marcados por padrão (as que costumam virar planejamento). */
    private const PLANNING_TYPES = ['macroplanejamento', 'kickoff_estrategico', 'revisao_interna'];

    private const MAX_TRANSCRIPT = 150000;

    /** @return array<int, array{key: string, group: string, label: string, detail: string, default: bool}> */
    public function available(MacroPlan $plan): array
    {
        $plan->loadMissing('client');
        $out = [];

        $meetings = Meeting::where('client_id', $plan->client_id)
            ->where(fn ($q) => $q->whereNotNull('ata')->orWhereNotNull('transcricao'))
            ->orderByDesc('scheduled_at')
            ->limit(8)
            ->get(['id', 'title', 'type', 'scheduled_at', 'ata', 'transcricao']);
        $defaultMeeting = $meetings->first(fn ($m) => in_array($m->type, self::PLANNING_TYPES, true));
        foreach ($meetings as $m) {
            $has = array_filter([filled($m->ata) ? 'ATA' : null, filled($m->transcricao) ? 'transcrição' : null]);
            $out[] = [
                'key'     => "meeting:{$m->id}",
                'group'   => 'Reuniões',
                'label'   => $m->title ?: (Meeting::$types[$m->type] ?? 'Reunião'),
                'detail'  => $m->scheduled_at?->format('d/m/Y') . ' · ' . implode(' + ', $has),
                'default' => $defaultMeeting && $m->id === $defaultMeeting->id,
            ];
        }

        $previous = MacroPlan::where('client_id', $plan->client_id)->where('id', '!=', $plan->id)
            ->where('period_start', '<=', $plan->period_start)
            ->orderByDesc('period_start')->first();
        if ($previous) {
            $out[] = [
                'key'     => "plan:{$previous->id}",
                'group'   => 'Planejamento anterior',
                'label'   => $previous->title,
                'detail'  => $previous->periodLabel() . ' · com o fechamento das campanhas e projetos que tiverem',
                'default' => true,
            ];
        }

        if (filled($plan->client->briefing ?? null)) {
            $out[] = ['key' => 'briefing', 'group' => 'Cliente', 'label' => 'Briefing do cliente', 'detail' => 'cadastro do cliente', 'default' => true];
        }

        foreach (MacroPlanAttachment::where('macro_plan_id', $plan->id)->latest()->get() as $a) {
            $out[] = ['key' => "att:macro:{$a->id}", 'group' => 'Anexos', 'label' => $a->filename,
                'detail' => 'anexo deste planejamento', 'default' => false];
        }
        foreach (MeetingAttachment::whereIn('meeting_id', $meetings->pluck('id'))->latest()->get() as $a) {
            $meeting = $meetings->firstWhere('id', $a->meeting_id);
            $out[] = ['key' => "att:meeting:{$a->id}", 'group' => 'Anexos', 'label' => $a->filename,
                'detail' => 'anexo da reunião ' . ($meeting?->scheduled_at?->format('d/m/Y') ?? ''), 'default' => false];
        }

        return $out;
    }

    /**
     * Junta o conteúdo das fontes marcadas.
     * @return array{blocks: array<int, array{title: string, text: string}>, files: array, used: array, skipped: array}
     */
    public function gather(MacroPlan $plan, array $keys): array
    {
        $allowed = collect($this->available($plan))->keyBy('key');
        $result  = ['blocks' => [], 'files' => [], 'used' => [], 'skipped' => []];

        foreach (array_unique($keys) as $key) {
            if (!$allowed->has($key)) {
                continue; // só o que o App ofereceu para este cliente
            }
            $label = $allowed[$key]['label'];
            [$type, $id, $sub] = array_pad(explode(':', $key, 3), 3, null);

            if ($type === 'meeting') {
                $m = Meeting::find($id);
                $title = "Reunião: {$label} (" . $m?->scheduled_at?->format('d/m/Y') . ')';
                if (filled($m?->ata)) {
                    $result['blocks'][] = ['title' => "{$title} — ATA", 'text' => trim(SourceText::html((string) $m->ata))];
                }
                if (filled($m?->transcricao)) {
                    $result['blocks'][] = ['title' => "{$title} — transcrição", 'text' => mb_substr(trim((string) $m->transcricao), 0, self::MAX_TRANSCRIPT)];
                }
                $result['used'][] = $title;
            } elseif ($type === 'plan') {
                $prev = MacroPlan::with('projects')->find($id);
                $text = $this->planText($prev);
                if ($text) {
                    $result['blocks'][] = ['title' => "Planejamento anterior: {$label} ({$prev->periodLabel()})", 'text' => $text];
                    $result['used'][] = "Planejamento anterior: {$label}";
                } else {
                    $result['skipped'][] = "{$label}: sem documento para ler";
                }
            } elseif ($type === 'briefing') {
                $result['blocks'][] = ['title' => 'Briefing do cliente', 'text' => trim(SourceText::html((string) $plan->client->briefing))];
                $result['used'][] = 'Briefing do cliente';
            } elseif ($type === 'att') {
                $att = $id === 'macro' ? MacroPlanAttachment::find($sub) : MeetingAttachment::find($sub);
                $this->addFile($result, $att?->disk, $att?->disk_path, $att?->mime_type, $att?->filename ?? $label);
            }
        }

        return $result;
    }

    /** Arquivo enviado na hora (já guardado como anexo do planejamento). */
    public function addFile(array &$result, ?string $disk, ?string $path, ?string $mime, string $name): void
    {
        if (!$disk || !$path) {
            $result['skipped'][] = "{$name}: arquivo não encontrado";

            return;
        }
        $read = SourceText::fromStorage($disk, $path, $mime, $name);
        match ($read['kind']) {
            'text'  => $result['blocks'][] = ['title' => "Arquivo: {$name}", 'text' => $read['text']],
            'file'  => $result['files'][] = $read['file'],
            default => $result['skipped'][] = "{$name}: {$read['reason']}",
        };
        if ($read['kind'] !== 'skip') {
            $result['used'][] = "Arquivo: {$name}";
        }
    }

    /** Planejamento anterior: documento em campos (ou o HTML antigo) + fechamento das campanhas/projetos. */
    private function planText(?MacroPlan $plan): ?string
    {
        if (!$plan) {
            return null;
        }
        $parts = [];
        if ($doc = $plan->fieldDocument) {
            $parts[] = $doc->schema()->toText($doc->content);
        } elseif ($html = TaskAssistantContext::documentText($plan->mainDocument(), 40000)) {
            $parts[] = $html;
        }

        $closings = [];
        foreach ($plan->projects as $project) {
            $doc = $project->fieldDocument;
            if (!$doc || blank($doc->content['resultado'] ?? null)) {
                continue;
            }
            $c = $doc->content;
            $closings[] = "- {$project->title} ({$project->type}): resultado " . ($c['resultado'] ?? '') . '. '
                . trim(implode(' ', array_filter([
                    $c['aconteceu'] ?? $c['entregamos'] ?? null,
                    isset($c['angulo_melhor']) ? "Ângulo que funcionou melhor: {$c['angulo_melhor']}" : null,
                    isset($c['levamos']) ? "Levamos para o próximo: {$c['levamos']}" : null,
                ])));
        }
        if ($closings) {
            $parts[] = "## Fechamento das campanhas e projetos\n" . implode("\n", $closings);
        }

        return $parts ? implode("\n\n", $parts) : null;
    }
}
