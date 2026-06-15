<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdDailySnapshot extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_ad_account_id',
        'entity_level', 'entity_id', 'entity_name', 'parent_entity_id', 'platform',
        'snapshot_date',
        'spend', 'revenue', 'impressions', 'clicks', 'conversions', 'reach',
        'cpm', 'cpc', 'ctr', 'cpa', 'roas',
        'raw_data',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'spend'         => 'decimal:4',
        'revenue'       => 'decimal:4',
        'impressions'   => 'integer',
        'clicks'        => 'integer',
        'conversions'   => 'integer',
        'reach'         => 'integer',
        'cpm'           => 'decimal:4',
        'cpc'           => 'decimal:4',
        'ctr'           => 'decimal:4',
        'cpa'           => 'decimal:4',
        'roas'          => 'decimal:4',
        'raw_data'      => 'array',
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    // Calcula métricas derivadas antes de salvar
    public static function withCalculatedMetrics(array $data): array
    {
        $spend       = (float) ($data['spend'] ?? 0);
        $revenue     = (float) ($data['revenue'] ?? 0);
        $impressions = (int)   ($data['impressions'] ?? 0);
        $clicks      = (int)   ($data['clicks'] ?? 0);
        $conversions = (int)   ($data['conversions'] ?? 0);

        $data['cpm']  = $impressions > 0 ? round(($spend / $impressions) * 1000, 4) : null;
        $data['cpc']  = $clicks > 0      ? round($spend / $clicks, 4)               : null;
        $data['ctr']  = $impressions > 0 ? round(($clicks / $impressions) * 100, 4) : null;
        $data['cpa']  = $conversions > 0 ? round($spend / $conversions, 4)          : null;
        $data['roas'] = $spend > 0       ? round($revenue / $spend, 4)              : null;

        return $data;
    }
}
