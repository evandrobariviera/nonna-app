<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma cobrança feita numa pendência — quem, como e quando (ver PendencyService::nudge). */
class PendencyNudge extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    const UPDATED_AT = null;

    protected $fillable = ['pendency_id', 'user_id', 'channel'];

    public static array $channels = [
        'whatsapp'   => 'WhatsApp',
        'email'      => 'e-mail',
        'ligacao'    => 'ligação',
        'presencial' => 'pessoalmente',
        'lembrete'   => 'aviso no App',
    ];

    public function pendency(): BelongsTo
    {
        return $this->belongsTo(Pendency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channelLabel(): string
    {
        return self::$channels[$this->channel] ?? $this->channel;
    }
}
