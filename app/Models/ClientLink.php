<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLink extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id',
        'label',
        'type',
        'type_custom',
        'url',
        'notes',
        'created_by',
    ];

    public static array $types = [
        'drive'        => 'Google Drive',
        'contrato'     => 'Contrato',
        'briefing'     => 'Briefing',
        'planilha'     => 'Planilha',
        'dossie'       => 'Dossiê de Marca',
        'apresentacao' => 'Apresentação',
        'outros'       => 'Outro',
    ];

    public function typeLabel(): string
    {
        if ($this->type === 'outros' && $this->type_custom) {
            return $this->type_custom;
        }
        return self::$types[$this->type] ?? $this->type;
    }

    // Rótulo é obrigatório em todo link novo (validação no controller) — o fallback aqui
    // é só rede de segurança pra qualquer link antigo que escape do backfill da migration.
    public function displayLabel(): string
    {
        return $this->label ?: $this->typeLabel();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
