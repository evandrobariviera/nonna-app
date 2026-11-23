<?php

namespace App\Models;

use App\Services\Documents\DocumentSchema;
use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Documento padrão guardado em campos (forma = o modelo do tipo; mérito = os valores).
 * Editar só via App\Services\Documents\DocumentEditor (valida o caminho contra o modelo,
 * guarda quem mudou e o histórico).
 */
class Document extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id', 'client_id', 'kind', 'schema_version',
        'documentable_type', 'documentable_id', 'source_document_id', 'source_item_id', 'content', 'provenance', 'agent_state',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'content'    => 'array',
        'provenance' => 'array',
        'agent_state' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderByDesc('updated_at');
    }

    public function schema(): DocumentSchema
    {
        return DocumentSchema::for($this->kind);
    }
}
