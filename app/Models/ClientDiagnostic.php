<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientDiagnostic extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'version',
        'title',
        'status',
        'sec01_briefing',
        'sec02_marketing',
        'sec03_audit',
        'sec04_competition',
        'sec05_persona',
        'sec06_synthesis',
        'created_by',
        'completed_at',
    ];

    protected $casts = [
        'sec01_briefing'  => 'array',
        'sec02_marketing' => 'array',
        'sec03_audit'     => 'array',
        'sec04_competition' => 'array',
        'sec05_persona'   => 'array',
        'sec06_synthesis' => 'array',
        'completed_at'    => 'datetime',
        'version'         => 'integer',
    ];

    public static array $statuses = [
        'draft'    => ['label' => 'Rascunho', 'color' => 'muted'],
        'complete' => ['label' => 'Concluído', 'color' => 'green'],
    ];

    public static array $sections = [
        'sec01' => ['num' => '01', 'label' => 'Briefing de Negócio',      'icon' => '🏢'],
        'sec02' => ['num' => '02', 'label' => 'Cenário de Marketing',      'icon' => '📡'],
        'sec03' => ['num' => '03', 'label' => 'Auditoria de Comunicação',  'icon' => '🔎'],
        'sec04' => ['num' => '04', 'label' => 'Análise de Concorrência',   'icon' => '⚔️'],
        'sec05' => ['num' => '05', 'label' => 'Público & Personas',        'icon' => '👤'],
        'sec06' => ['num' => '06', 'label' => 'Síntese Estratégica',       'icon' => '💡'],
    ];

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }

    public function isSectionFilled(string $section): bool
    {
        $col = $section . '_briefing';
        if ($section === 'sec01') $col = 'sec01_briefing';
        if ($section === 'sec02') $col = 'sec02_marketing';
        if ($section === 'sec03') $col = 'sec03_audit';
        if ($section === 'sec04') $col = 'sec04_competition';
        if ($section === 'sec05') $col = 'sec05_persona';
        if ($section === 'sec06') $col = 'sec06_synthesis';

        return !empty($this->$col);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(DiagnosticCompetitor::class, 'diagnostic_id')->orderBy('position');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(DiagnosticPersona::class, 'diagnostic_id')->orderBy('position');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
