<?php

namespace App\Services\Agent;

use App\Models\AgentMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Transforma as mensagens gravadas no que a tela mostra: balão da pessoa,
 * resposta do agente (Markdown → HTML), "o que ele consultou" (atividade) e o
 * cartão de proposta. Chamadas internas sem texto não aparecem.
 */
class AgentTranscript
{
    /** @param Collection<AgentMessage> $messages */
    public static function toUi(Collection $messages): array
    {
        return $messages->map(fn (AgentMessage $m) => self::item($m))->filter()->values()->all();
    }

    public static function item(AgentMessage $m): ?array
    {
        if ($m->role === 'user') {
            return ['id' => $m->id, 'kind' => 'user', 'text' => (string) $m->content];
        }

        if ($m->role === 'assistant') {
            return trim((string) $m->content) === '' ? null
                : ['id' => $m->id, 'kind' => 'assistant', 'html' => self::markdown($m->content)];
        }

        if ($m->role === 'tool' && $m->proposal) {
            return [
                'id'      => $m->id,
                'kind'    => 'proposal',
                'type'    => $m->proposal['type'] ?? 'task',
                'title'   => $m->proposal['title'] ?? 'Proposta',
                'display' => $m->proposal['display'] ?? [],
                'status'  => $m->proposal_status ?? 'pending',
                'result'  => $m->proposal_result,
            ];
        }

        if ($m->role === 'tool') {
            $failed = Str::startsWith((string) $m->content, '{"erro"');
            return [
                'id'     => $m->id,
                'kind'   => 'activity',
                'label'  => (AgentToolbox::LABELS[$m->tool_name] ?? 'Consultou o App') . ($failed ? ' (sem sucesso)' : ''),
            ];
        }

        return null;
    }

    public static function markdown(string $text): string
    {
        $html = Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        // Links abrem em outra aba — a conversa continua aqui.
        return str_replace('<a href=', '<a target="_blank" rel="noopener" href=', $html);
    }
}
