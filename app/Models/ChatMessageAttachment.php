<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatMessageAttachment extends Model
{
    protected $connection = 'pgsql';

    protected $fillable = [
        'message_id',
        'filename',
        'disk_path',
        'disk',
        'mime_type',
        'size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    // Link curto (1h) — quem pede passou pela checagem de participante em
    // ChatController::attachment(); o link do R2 em si não é guardado em lugar nenhum.
    public function temporaryUrl(bool $download = false): string
    {
        if ($this->disk !== 'r2') {
            return Storage::disk($this->disk)->url($this->disk_path);
        }

        $options = $download
            ? ['ResponseContentDisposition' => 'attachment; filename="' . addslashes($this->filename) . '"']
            : [];

        return Storage::disk('r2')->temporaryUrl($this->disk_path, now()->addHour(), $options);
    }
}
