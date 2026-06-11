<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    protected $connection = 'pgsql';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'task_id', 'filename', 'disk_path', 'disk', 'mime_type', 'size', 'uploaded_by',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->disk_path);
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024)       return "{$bytes} B";
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function icon(): string
    {
        $mime = $this->mime_type ?? '';
        if (str_starts_with($mime, 'image/'))       return '🖼';
        if (str_contains($mime, 'pdf'))             return '📄';
        if (str_contains($mime, 'video'))           return '🎬';
        if (str_contains($mime, 'zip') || str_contains($mime, 'rar')) return '📦';
        return '📎';
    }
}
