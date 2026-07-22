<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBenchmark extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'segment',
        'metric_key',
        'period_start',
        'period_end',
        'sample_size',
        'avg_value',
        'median_value',
        'p10_value',
        'p90_value',
        'computed_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'sample_size'  => 'integer',
        'avg_value'    => 'float',
        'median_value' => 'float',
        'p10_value'    => 'float',
        'p90_value'    => 'float',
        'computed_at'  => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // Em qual percentil aproximado um valor cai dentro deste benchmark (interpolação simples)
    public function percentileFor(float $value): int
    {
        if ($value <= $this->p10_value) return 10;
        if ($value >= $this->p90_value) return 90;
        if ($value <= $this->median_value) {
            $ratio = ($value - $this->p10_value) / max($this->median_value - $this->p10_value, 0.0001);
            return (int) round(10 + $ratio * 40);
        }

        $ratio = ($value - $this->median_value) / max($this->p90_value - $this->median_value, 0.0001);
        return (int) round(50 + $ratio * 40);
    }
}
