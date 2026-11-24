<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskDraftService;

/**
 * Peças e entregas viram tarefas (23/11, etapa D). Substitui o Assistente de Tarefas que saiu:
 * o documento da campanha (seção 07, peças) ou do projeto (seção 03, entregas; seção 04, o que
 * precisamos do cliente) já diz O QUE produzir e ATÉ QUANDO. Daqui sai uma lista de tarefas
 * sugeridas, sem IA (não gasta crédito): a pessoa confere tipo, data e quem faz, e confirma.
 *
 * Cada tarefa guarda o id da linha do documento (tasks.document_item_id): a mesma linha não
 * vira tarefa duas vezes. Apagou a tarefa? A linha volta a ficar disponível.
 */
class DocumentTasks
{
    public function __construct(private TaskDraftService $tasks) {}

    /**
     * Linhas sugeridas, na ordem do documento.
     *
     * @return array<int, array{item_id: string, grupo: string, title: string, description: string, task_type: ?string, destination: ?string, due_date: ?string, checked: bool, task: ?array}>
     */
    public function rows(Document $document, Project $project): array
    {
        $c       = $document->content ?? [];
        $created = Task::where('project_id', $project->id)->whereNotNull('document_item_id')
            ->get(['id', 'title', 'status', 'document_item_id'])->keyBy('document_item_id');

        $rows      = $document->kind === 'campanha' ? $this->campaignRows($c) : $this->projectRows($c);
        $dismissed = array_flip($document->agent_state['tarefas_dispensadas'] ?? []);

        return array_map(function ($r) use ($created, $dismissed) {
            $task = $created->get($r['item_id']);
            $r['task'] = $task ? ['id' => $task->id, 'title' => $task->title, 'status' => $task->statusLabel(), 'url' => route('tasks.show', $task)] : null;
            $r['dismissed'] = !$task && isset($dismissed[$r['item_id']]);
            $r['checked'] = $r['checked'] && !$task && !$r['dismissed'];

            return $r;
        }, $rows);
    }

    private function campaignRows(array $c): array
    {
        $angulos = collect($c['angulos'] ?? [])->keyBy('id');
        $pago    = collect($c['midia'] ?? [])->contains(fn ($m) => trim((string) ($m['plataforma'] ?? '')) !== '');
        $rows    = [];

        foreach (array_values($c['pecas'] ?? []) as $i => $p) {
            $nome = trim((string) ($p['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $qtd     = max(1, (int) ($p['quantidade'] ?? 1));
            $formato = trim((string) ($p['formato'] ?? ''));
            $angulo  = $angulos->get($p['angulo'] ?? '');

            $desc = [];
            if ($formato !== '') {
                $desc[] = "Formato: {$formato}" . ($qtd > 1 ? " · {$qtd} unidades" : '');
            }
            if ($i === 0) {
                $desc[] = 'É a peça conceito: as outras seguem a linha dela.';
            }
            if ($angulo) {
                $desc[] = '';
                $desc[] = 'Ângulo: ' . ($angulo['nome'] ?? '');
                foreach (['mensagem' => 'O que o anúncio diz', 'para_quem' => 'Para quem é', 'ganchos' => 'Primeiras frases (ganchos)'] as $k => $label) {
                    if (trim((string) ($angulo[$k] ?? '')) !== '') {
                        $desc[] = "{$label}: " . trim($angulo[$k]);
                    }
                }
            }
            if (trim((string) ($c['frase_conceito'] ?? '')) !== '') {
                $desc[] = '';
                $desc[] = 'Grande ideia da campanha: ' . trim($c['frase_conceito']);
            }
            $desc[] = '';
            $desc[] = 'Veio do documento da campanha (07 Peças e datas). O resto do contexto está lá.';

            $rows[] = [
                'item_id'     => $p['id'],
                'grupo'       => 'Peças',
                'title'       => $nome . ($qtd > 1 ? " ({$qtd})" : ''),
                'description' => implode("\n", $desc),
                'task_type'   => $this->guessType($nome . ' ' . $formato),
                'destination' => $pago ? 'campanhas_patrocinadas' : 'publicacao_organica',
                'due_date'    => $this->date($p['data'] ?? null) ?? $this->date($c['entrega_pecas'] ?? null),
                'checked'     => true,
            ];
        }

        return $rows;
    }

    private function projectRows(array $c): array
    {
        $rows = [];
        foreach ($c['entregas'] ?? [] as $e) {
            $nome = trim((string) ($e['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $area = trim((string) ($e['area'] ?? ''));
            $type = $this->guessType($area . ' ' . $nome);
            $rows[] = [
                'item_id'     => $e['id'],
                'grupo'       => 'Entregas',
                'title'       => $nome,
                'description' => ($area !== '' ? "Área: {$area}\n" : '')
                    . (trim((string) ($c['aceite'] ?? '')) !== '' ? "\nO projeto está pronto quando: " . trim($c['aceite']) . "\n" : '')
                    . "\nVeio do documento do projeto (03 Entregas). O escopo (o que entra e o que não entra) está lá.",
                'task_type'   => $type,
                'destination' => $type === 'web' ? 'projeto_web' : null,
                'due_date'    => $this->date($e['data'] ?? null) ?? $this->date($c['fim'] ?? null),
                'checked'     => true,
            ];
        }
        // O que precisamos do cliente: vira tarefa de cobrança, desmarcada (nem todo pedido precisa).
        foreach ($c['cliente_precisa'] ?? [] as $p) {
            $oque = trim((string) ($p['o_que'] ?? ''));
            if ($oque === '') {
                continue;
            }
            $rows[] = [
                'item_id'     => $p['id'],
                'grupo'       => 'Cobrar do cliente',
                'title'       => 'Cobrar do cliente: ' . $oque,
                'description' => "O projeto depende disto do cliente.\n\nVeio do documento do projeto (04 O que precisamos do cliente).",
                'task_type'   => 'reunioes',
                'destination' => 'envio_resposta_cliente',
                'due_date'    => $this->date($p['ate'] ?? null),
                'checked'     => false,
            ];
        }

        return $rows;
    }

    /**
     * Cria as tarefas escolhidas. A linha do documento precisa existir e ainda não ter virado
     * tarefa. A descrição vem da tela (a pessoa pode editar); vazia, usa a do documento.
     * Linha sem item_id = tarefa acrescentada à mão (não fica presa a nenhuma linha do documento).
     *
     * @param  array<int, array{item_id: ?string, title: string, description: ?string, task_type: string, due_date: ?string, responsavel_user_id: ?int, executor_user_id: ?int}>  $picked
     * @return array{created: int, skipped: string[], warnings: string[]}
     */
    public function create(Document $document, Project $project, array $picked, int $userId): array
    {
        $rows    = collect($this->rows($document, $project))->keyBy('item_id');
        $team    = User::whereNull('client_id')->pluck('id')->flip();
        $drafts  = [];
        $skipped = [];

        $person = fn ($id) => is_numeric($id) && $team->has((int) $id) ? (int) $id : null;

        foreach ($picked as $p) {
            $desc = trim((string) ($p['description'] ?? ''));
            if (blank($p['item_id'] ?? null)) {
                $drafts[] = [
                    'title'               => trim($p['title']),
                    'description'         => $desc !== '' ? $desc : null,
                    'task_type'           => $p['task_type'],
                    'destination'         => $this->destination($document),
                    'due_date'            => $p['due_date'] ?: null,
                    'responsavel_user_id' => $person($p['responsavel_user_id'] ?? null),
                    'executor_user_id'    => $person($p['executor_user_id'] ?? null),
                ];
                continue;
            }
            $row = $rows->get($p['item_id']);
            if (!$row) {
                $skipped[] = 'Uma linha não existe mais no documento.';
                continue;
            }
            if ($row['task']) {
                $skipped[] = "\"{$row['title']}\" já virou tarefa.";
                continue;
            }
            $drafts[] = [
                'title'               => trim($p['title']) !== '' ? trim($p['title']) : $row['title'],
                'description'         => $desc !== '' ? $desc : $row['description'],
                'task_type'           => $p['task_type'],
                'destination'         => $row['destination'],
                'due_date'            => $p['due_date'] ?: null,
                'responsavel_user_id' => $person($p['responsavel_user_id'] ?? null),
                'executor_user_id'    => $person($p['executor_user_id'] ?? null),
                'document_item_id'    => $row['item_id'],
            ];
        }

        $result = $drafts ? $this->tasks->createFromDrafts($project, $drafts, $userId, 'documento') : ['tasks' => collect(), 'warnings' => []];

        return ['created' => $result['tasks']->count(), 'skipped' => $skipped, 'warnings' => $result['warnings']];
    }

    /**
     * Dispensa (ou traz de volta) uma linha sugerida: some da lista sem mexer no documento.
     * Guardado em agent_state.tarefas_dispensadas, relido com trava (jobs do agente também gravam ali).
     */
    public function dismiss(Document $document, string $itemId, bool $dismiss): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($document, $itemId, $dismiss) {
            $fresh = Document::withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $state = $fresh->agent_state ?? [];
            $list  = array_values(array_diff($state['tarefas_dispensadas'] ?? [], [$itemId]));
            if ($dismiss) {
                $list[] = $itemId;
            }
            $state['tarefas_dispensadas'] = $list;
            $fresh->forceFill(['agent_state' => $state])->saveQuietly();
        });
    }

    /** Destino padrão de uma tarefa acrescentada à mão. */
    private function destination(Document $document): ?string
    {
        if ($document->kind !== 'campanha') {
            return null;
        }
        $pago = collect($document->content['midia'] ?? [])->contains(fn ($m) => trim((string) ($m['plataforma'] ?? '')) !== '');

        return $pago ? 'campanhas_patrocinadas' : 'publicacao_organica';
    }

    /** Tipo de tarefa pelo nome/formato da peça ou pela área da entrega. Na dúvida, Material Gráfico. */
    private function guessType(string $text): string
    {
        $t = mb_strtolower($text);
        $map = [
            'video'          => ['vídeo', 'video', 'reels', 'reel', 'shorts', 'audiovisual', 'captação', 'captacao', 'filmagem', 'motion', 'animação'],
            'web'            => ['site', 'landing', 'página', 'pagina', 'web', 'dev', 'wordpress', 'sistema', 'formulário'],
            'setup'          => ['pixel', 'gtm', 'ga4', 'tag', 'rastreamento', 'tracking', 'setup', 'integração', 'crm', 'automação'],
            'trafego'        => ['tráfego', 'trafego', 'público', 'configurar campanha', 'gerenciador', 'mídia paga'],
            'email'          => ['e-mail', 'email', 'newsletter'],
            'seo'            => ['seo', 'artigo', 'blog'],
            'estrategia'     => ['roteiro', 'documento', 'estratégia', 'estrategia', 'planejamento', 'pesquisa', 'briefing', 'copy', 'texto'],
            'social'         => ['stories orgânico', 'publicação', 'social media'],
        ];
        foreach ($map as $type => $words) {
            foreach ($words as $w) {
                if (str_contains($t, $w)) {
                    return $type;
                }
            }
        }

        return 'criacao';
    }

    private function date($v): ?string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}
