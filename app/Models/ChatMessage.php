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

    // Citação de tarefa/projeto/campanha/cliente (atalho "/" no chat) fica no texto como
    // [[tipo:uuid|Rótulo]]. Rótulo é só o que foi escolhido na hora — o link leva pra
    // tela de verdade, que aplica as permissões normais dela.
    public const REFERENCE_PATTERN = '/\[\[(tarefa|projeto|campanha|cliente):([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\|([^\]\|\n]{1,200})\]\]/';

    public const REFERENCE_ROUTES = [
        'tarefa'   => 'tasks.show',
        'projeto'  => 'projects.showDirect',
        'campanha' => 'campaigns.show',
        'cliente'  => 'clients.show',
    ];

    // Versão texto (prévia na lista/aviso): citação vira "@Rótulo".
    public static function plainText(?string $body): string
    {
        return preg_replace(self::REFERENCE_PATTERN, '@$3', (string) $body);
    }

    // Texto puro → HTML seguro pra exibir: escapa TUDO primeiro e só depois transforma
    // link em <a>, citação em chip e quebra de linha em <br>. Nunca confiar em HTML vindo do usuário.
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

        // Depois do linkify de propósito: o href da citação não pode ser reprocessado como URL.
        // Rótulo ($m[3]) já está escapado (veio de $escaped); tipo e uuid são validados pela regex.
        $withReferences = preg_replace_callback(
            self::REFERENCE_PATTERN,
            fn ($m) => '<a href="' . route(self::REFERENCE_ROUTES[$m[1]], $m[2], false) . '" class="chat-ref chat-ref-' . $m[1] . '" data-chat-ref="' . $m[1] . '">' . $m[3] . '</a>',
            $linked
        );

        return nl2br($withReferences, false);
    }
}
