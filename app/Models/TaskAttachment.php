<?php

namespace App\Models;

use App\Traits\TenantViaParent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    use HasUuids, TenantViaParent;

    // Filtro de organização herdado do pai (ver TenantViaParent).
    public function tenantParentRelation(): string
    {
        return 'task';
    }

    protected $connection = 'pgsql';

    protected $fillable = [
        'task_id', 'filename', 'disk_path', 'disk', 'mime_type', 'size', 'uploaded_by',
        'uploaded_by_contact_id', 'is_deliverable', 'round_number', 'kind',
        'sort_order', 'thumb_path', 'approval_status', 'replaces_id', 'version',
    ];

    // Situação de cada peça na aprovação do cliente (ver migration 2026_11_23_210000).
    // null = ainda não foi para o cliente.
    public static array $approvalLabels = [
        'pending'           => 'Com o cliente',
        'approved'          => 'Aprovada',
        'changes_requested' => 'Ajuste pedido',
        'superseded'        => 'Versão anterior',
    ];

    public function approvalLabel(): string
    {
        return self::$approvalLabels[$this->approval_status] ?? 'Não enviada';
    }

    public function isSuperseded(): bool
    {
        return $this->approval_status === 'superseded';
    }

    // Versão anterior desta peça (quem foi "Substituído" por este arquivo).
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(TaskAttachment::class, 'replaces_id');
    }

    // Retorno de cada aprovador sobre esta peça específica.
    public function feedbacks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeliverableFeedback::class, 'attachment_id');
    }

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

    // Ordem que o time arrastou na tarefa (sort_order) — é a ordem que o cliente vê
    // na aprovação. Sem ordem definida (vazio), vale a ordem de envio, no fim da fila.
    public function scopeInDisplayOrder($query)
    {
        return $query->orderByRaw('sort_order is null')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    // Miniatura leve (ver App\Support\ImageThumbnail); sem ela, o arquivo original.
    public function thumbUrl(): string
    {
        if (!$this->thumb_path) {
            return $this->url();
        }
        if ($this->disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->thumb_path, now()->addHours(24));
        }
        return Storage::disk($this->disk)->url($this->thumb_path);
    }

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

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
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
