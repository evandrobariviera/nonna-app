<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use SoftDeletes;

    protected $connection = 'pgsql';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'reply_to_id',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }

    // Texto puro → HTML seguro pra exibir: escapa TUDO primeiro e só depois transforma
    // link em <a> e quebra de linha em <br>. Nunca confiar em HTML vindo do usuário.
    public function bodyHtml(): string
    {
        $escaped = e((string) $this->body);

        $linked = preg_replace_callback(
            '~\bhttps?://[^\s<]+~i',
            function ($m) {
                // A URL já está escapada (&amp; etc.) — pode ir direto no href.
                $url = rtrim($m[0], '.,;:!?)');
                $tail = substr($m[0], strlen($url));
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $tail;
            },
            $escaped
        );

        return nl2br($linked, false);
    }
}
