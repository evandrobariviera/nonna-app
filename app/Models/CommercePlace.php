<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Empresa mapeada na prospecção. Termos do Google: só o place_id é guardado.
 * Nome, endereço, telefone e avaliações nunca vão para o banco — são lidos ao vivo
 * (App\Services\Commerce\GooglePlaces::details). Ver .claude/docs/central-comercio.md.
 */
class CommercePlace extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'place_id', 'city_id', 'segment_id', 'status', 'score', 'score_parts',
        'last_seen_at', 'place_id_checked_at', 'site_signals', 'collected_at', 'collect_error',
        'ads_meta', 'ads_google', 'ads_checked_by', 'ads_checked_at',
        'google_data', 'google_data_at', // dados do Google guardados (decisão 25/11/2026, ver migration)
        'report_token', 'report_at', 'report_views', 'report_last_view_at', 'opted_out_at', 'report_cta_clicks', 'report_cta_at', // raio-x público (etapa 5)
    ];

    protected $casts = [
        'score_parts'         => 'array',
        'last_seen_at'        => 'datetime',
        'place_id_checked_at' => 'datetime',
        'site_signals'        => 'array',
        'collected_at'        => 'datetime',
        'ads_checked_at'      => 'datetime',
        'google_data'         => 'array',
        'google_data_at'      => 'datetime',
        'report_at'           => 'datetime',
        'report_last_view_at' => 'datetime',
        'opted_out_at'        => 'datetime',
        'report_views'        => 'integer',
        'report_cta_at'       => 'datetime',
        'report_cta_clicks'   => 'integer',
    ];

    /**
     * Cria o link do raio-x (uma vez; o mesmo link continua valendo e mostra sempre os dados
     * mais recentes). Empresa Mapeada/Com nota passa para "Raio-x pronto".
     */
    public function ensureReport(): void
    {
        if ($this->report_token) {
            return;
        }
        $this->update([
            // 32 hex sem hífen (ver memória whatsapp-uuid-pix-link-bug).
            'report_token' => \Illuminate\Support\Str::uuid()->getHex()->toString(),
            'report_at'    => now(),
            'status'       => in_array($this->status, ['mapped', 'scored'], true) ? 'report' : $this->status,
        ]);
    }

    /**
     * O raio-x compara a empresa com as outras do mesmo segmento e cidade; só é justo se quase
     * todas tiverem nota (Evandro, 26/11/2026: "7º de 7" com 41 sem avaliar não tem lógica).
     */
    public const REPORT_MIN_COVERAGE = 0.8;

    /** @return array{scored: int, mapped: int, missing: int, ok: bool} */
    public function peerCoverage(): array
    {
        $row = static::withoutGlobalScopes()
            ->where('organization_id', $this->organization_id)->where('city_id', $this->city_id)
            ->when($this->segment_id, fn ($q, $v) => $q->where('segment_id', $v), fn ($q) => $q->whereNull('segment_id'))
            ->whereNotIn('status', ['discarded'])
            ->selectRaw('count(*) as mapped, count(score) as scored')->first();
        $mapped = (int) $row->mapped;
        $scored = (int) $row->scored;

        return ['scored' => $scored, 'mapped' => $mapped, 'missing' => $mapped - $scored,
            'ok' => $mapped > 0 && $scored / $mapped >= self::REPORT_MIN_COVERAGE];
    }

    public function reportUrl(): ?string
    {
        return $this->report_token ? route('commerce.report.show', $this->report_token) : null;
    }

    public static array $statuses = [
        'mapped'    => ['label' => 'Mapeada',          'color' => 'muted'],
        'scored'    => ['label' => 'Com nota',         'color' => 'purple'],
        'report'    => ['label' => 'Raio-x pronto',    'color' => 'orange'],
        'sent'      => ['label' => 'Enviada',          'color' => 'purple'],
        'replied'   => ['label' => 'Respondeu',        'color' => 'green'],
        'opted_out' => ['label' => 'Pediu pra sair',   'color' => 'red'],
        'discarded' => ['label' => 'Descartada',       'color' => 'muted'],
    ];

    public function adsChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ads_checked_by');
    }

    /** Nota parcial enquanto as rodadas de IA (etapa 4) não existem: vai até 80. */
    public function scoreMax(): int
    {
        return (int) ($this->score_parts['max'] ?? 100);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(CommerceCity::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CommerceSegment::class);
    }

    public function scans(): BelongsToMany
    {
        return $this->belongsToMany(CommerceScan::class, 'commerce_scan_place', 'place_id', 'scan_id');
    }
}
