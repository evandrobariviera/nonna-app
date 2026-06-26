<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AiApiKey extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'provider_id', 'label', 'api_key_encrypted', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Nunca expõe a chave em arrays/JSON — só via getApiKey()
    protected $hidden = ['api_key_encrypted'];

    public function getApiKey(): string
    {
        return decrypt($this->api_key_encrypted);
    }

    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key_encrypted'] = encrypt($value);
    }

    // Retorna chave mascarada para exibição: sk-...abc123 → sk-•••••••••••abc123
    public function maskedKey(): string
    {
        $raw = $this->getApiKey();
        $prefix = substr($raw, 0, 6);
        $suffix = substr($raw, -6);
        return $prefix . str_repeat('•', 12) . $suffix;
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
