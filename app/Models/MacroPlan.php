<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MacroPlan extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $table = 'macro_plans';

    protected $fillable = [
        'organization_id',
        'client_id',
        'responsible_id',
        'created_by',
        'title',
        'focus',
        'version',
        'period_start',
        'period_end',
        'status',
        'disciplines',
        'bloco1',
        'bloco2',
        'bloco4',
        'bloco5',
        'clickup_task_id',
        'clickup_attachments',
        'launched_at',
    ];

    protected $casts = [
        'period_start'        => 'date',
        'period_end'          => 'date',
        'launched_at'         => 'datetime',
        'disciplines'         => 'array',
        'bloco1'              => 'array',
        'bloco2'              => 'array',
        'bloco4'              => 'array',
        'bloco5'              => 'array',
        'clickup_attachments' => 'array',
    ];

    public static array $disciplineOptions = [
        'criacao'      => 'Criação / Audiovisual',
        'web'          => 'Web / Dev',
        'trafego'      => 'Tráfego / Performance',
        'setup'        => 'Setup / Tracking',
        'social'       => 'Social Media',
        'seo'          => 'SEO',
        'email'        => 'E-mail Marketing',
        'estrategia'   => 'Estratégia',
        'relacionamento' => 'Relacionamento / CS',
    ];

    public static array $statuses = [
        'em_planejamento' => ['label' => 'Em Planejamento', 'color' => 'muted'],
        'revisao_interna' => ['label' => 'Revisão Interna',  'color' => 'purple'],
        'aprovacao'       => ['label' => 'Aprovação',        'color' => 'orange'],
        'em_execucao'     => ['label' => 'Em Execução',      'color' => 'blue'],
        'concluido'       => ['label' => 'Concluído',        'color' => 'green'],
    ];

    public static array $blocks = [
        'bloco1' => ['num' => '01', 'label' => 'Visão Geral e Metas'],
        'bloco2' => ['num' => '02', 'label' => 'Contexto e Estratégia'],
        'bloco3' => ['num' => '03', 'label' => 'Arquitetura de Projetos'],
        'bloco4' => ['num' => '04', 'label' => 'Tarefas Isoladas e Rotina'],
        'bloco5' => ['num' => '05', 'label' => 'Checklist de Infraestrutura'],
    ];

    /**
     * Normaliza um bloco (bloco1/2/4/5) pra exibição/edição: campos que a UI
     * trata como texto às vezes chegam como lista (a IA do finalize_macro_meeting
     * devolve "pendências" como array de strings) — junta em linhas. Estruturas
     * legítimas com sub-objetos (kpis, pilares, linha_tempo) passam intactas.
     */
    public static function flattenBlockText(?array $block): array
    {
        if (empty($block)) {
            return [];
        }

        return array_map(function ($value) {
            if (!is_array($value)) {
                return $value;
            }
            foreach ($value as $item) {
                if (is_array($item)) {
                    return $value; // lista de objetos (kpis/pilares/linha_tempo) — não mexe
                }
            }
            if (array_is_list($value)) {
                return implode("\n", array_map(fn ($v) => (string) $v, $value));
            }
            return implode("\n", array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($value), array_values($value)));
        }, $block);
    }

    /**
     * Documento principal do planejamento = o HTML anexado mais recente (2026-10: um
     * documento só por planejamento, sem versão interna × cliente). É o que a tela mostra
     * e o que a IA lê.
     */
    public function mainDocument(): ?MacroPlanAttachment
    {
        $attachments = $this->relationLoaded('attachments') ? $this->attachments : $this->attachments()->get();

        return $attachments
            ->filter(fn ($a) => str_contains((string) $a->mime_type, 'html'))
            ->sortByDesc('created_at')
            ->first();
    }

    /**
     * Blocos do formato antigo (1/2/4/5) que têm algum conteúdo — saíram do formulário,
     * mas o que foi preenchido continua visível (só leitura). Bloco 1 só com a verba não
     * conta: a verba agora fica em Configurações.
     *
     * @return string[]
     */
    public function legacyBlocks(): array
    {
        $filled = function ($value) use (&$filled): bool {
            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($filled($v)) {
                        return true;
                    }
                }
                return false;
            }
            return trim((string) $value) !== '';
        };

        return array_values(array_filter(['bloco1', 'bloco2', 'bloco4', 'bloco5'], function ($key) use ($filled) {
            $data = $this->{$key} ?? [];
            if ($key === 'bloco1') {
                unset($data['verba_total']);
            }
            return $filled($data);
        }));
    }

    public function block(string $name): array
    {
        return static::flattenBlockText($this->{$name} ?? []);
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function isLaunched(): bool
    {
        return $this->launched_at !== null;
    }

    public function periodLabel(): string
    {
        return $this->period_start->format('d/m/Y') . ' a ' . $this->period_end->format('d/m/Y');
    }

    /**
     * Nome padrão e imutável (2026-10): "Planejamento {apelido} · 09/26 a 12/26". Ninguém
     * digita o título — é gravado no saving() e reescrito quando o apelido/razão social do
     * cliente muda (Client::booted). O que diferenciava um planejamento do outro ("Expointer",
     * "Calendário editorial") mora em `focus`, mostrado à parte.
     *
     * $clientFacing = razão social em vez do apelido — o apelido é interno e nunca aparece
     * pro cliente (Portal), ver Client::displayName().
     */
    public static function standardTitleFor(?Client $client, $start, $end, bool $clientFacing = false): string
    {
        $name = $client ? ($clientFacing ? $client->company_name : $client->displayName()) : null;
        $title = trim('Planejamento ' . ($name ?? ''));

        if ($start && $end) {
            $from = $start->format('m/y');
            $to   = $end->format('m/y');
            $title .= ' · ' . ($from === $to ? $from : $from . ' a ' . $to);
        }

        return $title;
    }

    public function standardTitle(bool $clientFacing = false): string
    {
        $client = $this->relationLoaded('client') && $this->client?->id === $this->client_id
            ? $this->client
            : Client::withoutGlobalScopes()->find($this->client_id);

        return self::standardTitleFor($client, $this->period_start, $this->period_end, $clientFacing);
    }

    /** Título pra tela do cliente (Portal): mesmo padrão, com a razão social. */
    public function clientTitle(): string
    {
        return $this->standardTitle(clientFacing: true);
    }

    /**
     * Reescreve o título dos planejamentos do cliente direto no banco (sem eventos — não é
     * uma edição do planejamento, então não entra no histórico nem dispara automação).
     */
    public static function refreshTitlesFor(Client $client): void
    {
        self::withoutGlobalScopes()->where('client_id', $client->id)
            ->get(['id', 'client_id', 'period_start', 'period_end', 'title'])
            ->each(function (self $plan) use ($client) {
                $title = self::standardTitleFor($client, $plan->period_start, $plan->period_end);
                if ($title !== $plan->title) {
                    self::withoutGlobalScopes()->whereKey($plan->id)->update(['title' => $title]);
                }
            });
    }

    protected static function booted(): void
    {
        static::saving(function (self $plan) {
            $plan->title = $plan->standardTitle();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position');
    }

    // Tarefas/tickets vinculados direto ao planejamento, sem passar por um Projeto
    // (ver Task::macroPlan()) — inclui as que também têm project_id; filtrar
    // whereNull('project_id') pra pegar só as soltas.
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MacroPlanAttachment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(MacroPlanActivity::class)->orderByDesc('created_at');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class)->orderBy('scheduled_at');
    }

    // Anexo HTML original do planejamento (import via skill externa) — quem prefere
    // ver o documento fonte em vez da versão renderizada no App usa esse link.
    public function htmlAttachment(): ?MacroPlanAttachment
    {
        return $this->attachments->first(fn (MacroPlanAttachment $a) => str_contains($a->mime_type ?? '', 'html')
            || str_ends_with(strtolower($a->filename ?? ''), '.html'));
    }
}
