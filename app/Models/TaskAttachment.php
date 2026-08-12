<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'task_id', 'filename', 'disk_path', 'disk', 'mime_type', 'size', 'uploaded_by',
        'uploaded_by_contact_id', 'is_deliverable', 'round_number', 'kind',
    ];

    protected $casts = [
        'is_deliverable' => 'boolean',
    ];

    // Escolhido pelo time no momento do upload. "insumo" = referência/material
    // recebido (nunca entra na aprovação do cliente); "entregavel" = material
    // produzido, candidato à rodada de aprovação (ver TaskApprovalService).
    public static array $kinds = [
        'insumo'     => 'Insumo',
        'entregavel' => 'Entregável',
    ];

    public function kindLabel(): string
    {
        return self::$kinds[$this->kind] ?? $this->kind;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploadedByContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'uploaded_by_contact_id');
    }

    // Nome de quem enviou, seja funcionário (uploaded_by) ou contato do Portal
    // (uploaded_by_contact_id) — sempre um dos dois, nunca os dois (constraint XOR).
    public function uploaderName(): ?string
    {
        return $this->uploadedBy?->name ?? $this->uploadedByContact?->name;
    }

    public function url(): string
    {
        if ($this->disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->disk_path, now()->addHours(24));
        }
        return Storage::disk($this->disk)->url($this->disk_path);
    }

    // URL de download forçado — diferente de url() (que só exibe), essa manda o R2
    // devolver Content-Disposition: attachment, pro navegador baixar em vez de abrir
    // numa aba (imagem/PDF antes só "abriam" e confundiam quem queria só baixar).
    public function downloadUrl(): string
    {
        if ($this->disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->disk_path, now()->addHours(24), [
                'ResponseContentDisposition' => 'attachment; filename="' . addslashes($this->filename) . '"',
            ]);
        }
        return $this->url();
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
