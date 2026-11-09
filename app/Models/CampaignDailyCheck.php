<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conferência diária do gestor de tráfego na Central de Campanhas.
 * Uma linha por gestor por dia. Na conferência só se olha, não se mexe:
 * o que estiver errado vai para "Resolver agora".
 */
class CampaignDailyCheck extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = ['organization_id', 'user_id', 'check_date', 'items'];

    protected $casts = [
        'check_date' => 'date',
        'items'      => 'array',
    ];

    /** Itens do checklist, na ordem em que aparecem. */
    public static array $items = [
        'gasto'  => 'Todas as campanhas ativas gastaram ontem?',
        'reprov' => 'Algum anúncio reprovado ou com problema?',
        'saldo'  => 'Algum saldo acabando?',
        'alerta' => 'Algum alerta novo?',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forToday(int $userId): ?self
    {
        return self::where('user_id', $userId)->whereDate('check_date', today())->first();
    }
}
