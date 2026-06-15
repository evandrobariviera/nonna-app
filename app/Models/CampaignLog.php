<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignLog extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_ad_account_id',
        'entity_level', 'entity_id', 'entity_name', 'platform',
        'logged_by', 'type', 'description', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public static array $types = [
        'budget_change' => ['label' => 'Mudança de Orçamento', 'color' => 'orange'],
        'targeting'     => ['label' => 'Segmentação',          'color' => 'purple'],
        'creative'      => ['label' => 'Criativo',             'color' => 'blue'],
        'bid'           => ['label' => 'Lance / Bid',          'color' => 'orange'],
        'pause'         => ['label' => 'Pausado',              'color' => 'red'],
        'resume'        => ['label' => 'Reativado',            'color' => 'green'],
        'note'          => ['label' => 'Observação',           'color' => 'muted'],
        'other'         => ['label' => 'Outro',                'color' => 'muted'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }
}
