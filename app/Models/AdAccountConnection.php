<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resultado da verificação de uma conta de anúncio (Contas e conexões).
 * Escrito por App\Services\AdSync\AdAccountConnectionChecker.
 */
class AdAccountConnection extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_ad_account_id', 'status', 'can_edit', 'issues', 'page_id', 'page_name', 'instagram_id', 'instagram_username',
        'utm_status', 'utm_current', 'ads_checked', 'ads_off_standard', 'auto_tagging', 'checked_at',
    ];

    protected $casts = [
        'issues'       => 'array',
        'auto_tagging' => 'boolean',
        'can_edit'     => 'boolean',
        'checked_at'   => 'datetime',
    ];

    public static array $statuses = [
        'ok'         => ['label' => 'Tudo certo',   'color' => 'green'],
        'atencao'    => ['label' => 'Falta algo',   'color' => 'orange'],
        'sem_acesso' => ['label' => 'Sem acesso',   'color' => 'red'],
        'erro'       => ['label' => 'Não verificou', 'color' => 'muted'],
    ];

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'muted';
    }
}
