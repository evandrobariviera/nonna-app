<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Uma varredura: busca no Google as empresas de um segmento (ou nicho) numa cidade.
 * Só traz os IDs (busca gratuita no Google); nome e endereço são lidos ao vivo na tela.
 */
class CommerceScan extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'city_id', 'segment_id', 'niche', 'status', 'terms', 'places_found', 'places_new',
        'saturated', 'api_calls', 'error', 'created_by', 'finished_at', 'runs', 'last_run_at',
    ];

    protected $casts = [
        'terms'       => 'array',
        'saturated'   => 'boolean',
        'finished_at' => 'datetime',
        'last_run_at' => 'datetime',
        'runs'        => 'integer',
    ];

    /** Começo da última rodada: empresas criadas a partir daqui são as "novas" dela. */
    public function lastRunStart(): \Illuminate\Support\Carbon
    {
        return $this->last_run_at ?? $this->created_at;
    }

    public static array $statuses = [
        'queued'  => ['label' => 'Na fila',     'color' => 'muted'],
        'running' => ['label' => 'Varrendo…',   'color' => 'orange'],
        'done'    => ['label' => 'Concluída',   'color' => 'green'],
        'error'   => ['label' => 'Deu erro',    'color' => 'red'],
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(CommerceCity::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(CommerceSegment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(CommercePlace::class, 'commerce_scan_place', 'scan_id', 'place_id');
    }

    /** Empresas que já tiveram conversa (enviada, respondeu, pediu pra sair) nunca são apagadas. */
    public const PROTECTED_PLACE_STATUSES = ['sent', 'replied', 'opted_out'];

    /**
     * Apaga a varredura e as empresas que só vieram por ela (Evandro, 26/11/2026).
     * Fica: empresa que também está em outra varredura, ou que já teve conversa.
     *
     * @return array{deleted:int, kept:int}
     */
    public function deleteWithPlaces(): array
    {
        return \Illuminate\Support\Facades\DB::connection($this->connection)->transaction(function () {
            $ids = $this->places()->pluck('commerce_places.id');

            $onlyHere = CommercePlace::withoutGlobalScopes()->whereIn('id', $ids)
                ->whereNotIn('status', self::PROTECTED_PLACE_STATUSES)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('commerce_scan_place as o')
                    ->whereColumn('o.place_id', 'commerce_places.id')->where('o.scan_id', '!=', $this->id))
                ->pluck('id');

            $this->delete(); // o vínculo (commerce_scan_place) sai junto, em cascata
            $deleted = CommercePlace::withoutGlobalScopes()->whereIn('id', $onlyHere)->delete();

            return ['deleted' => $deleted, 'kept' => $ids->count() - $deleted];
        });
    }

    public function label(): string
    {
        $what = $this->niche ?: ($this->segment?->name ?? 'Varredura');

        return $what . ' · ' . ($this->city?->name ?? '');
    }
}
