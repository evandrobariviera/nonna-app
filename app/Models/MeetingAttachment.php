<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MeetingAttachment extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'meeting_id', 'filename', 'disk_path', 'disk', 'mime_type', 'size', 'uploaded_by',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        if ($this->disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->disk_path, now()->addHours(24));
        }
        return Storage::disk($this->disk)->url($this->disk_path);
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024)       return "{$bytes} B";
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
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
