<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClientAdBillingDocument extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_ad_account_id',
        'type',
        'amount',
        'due_date',
        'reference_month',
        'pix_code',
        'filename',
        'disk_path',
        'disk',
        'mime_type',
        'size',
        'notes',
        'created_by',
        'notified_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'due_date'    => 'date',
        'reference_month' => 'date',
        'notified_at' => 'datetime',
    ];

    // "nota" = nota fiscal dos anúncios (Prestação de contas). A coluna type
    // tem 10 caracteres, por isso não é "nota_fiscal".
    public static array $types = [
        'boleto' => 'Boleto',
        'pix'    => 'PIX',
        'nota'   => 'Nota fiscal',
    ];

    /** Boleto e PIX são crédito (verba que entra); nota é a prova do gasto. */
    public function isCredit(): bool
    {
        return in_array($this->type, ['boleto', 'pix'], true);
    }

    /** Mês a que o documento se refere (o de envio, quando não informado). */
    public function referenceMonth(): \Illuminate\Support\Carbon
    {
        return ($this->reference_month ?? $this->created_at)->copy()->startOfMonth();
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(ClientAdAccount::class, 'client_ad_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    public function hasFile(): bool
    {
        return !empty($this->disk_path);
    }

    public function url(): ?string
    {
        if (!$this->hasFile()) {
            return null;
        }

        if ($this->disk === 'r2') {
            return Storage::disk('r2')->temporaryUrl($this->disk_path, now()->addHours(24));
        }

        return Storage::disk($this->disk)->url($this->disk_path);
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024)    return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';

        return round($bytes / 1048576, 1) . ' MB';
    }

    public function icon(): string
    {
        $mime = $this->mime_type ?? '';
        if (str_contains($mime, 'pdf'))       return '📄';
        if (str_starts_with($mime, 'image/')) return '🖼';

        return '📎';
    }
}
