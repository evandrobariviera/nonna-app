<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCredentialRequest extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $fillable = [
        'client_id', 'contact_id', 'token', 'requested_by',
        'expires_at', 'first_submitted_at', 'last_submitted_at', 'submissions_count',
    ];

    protected $casts = [
        'expires_at'         => 'datetime',
        'first_submitted_at' => 'datetime',
        'last_submitted_at'  => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ClientCredential::class, 'credential_request_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    // Soma um envio ao contador em vez de "fechar" a solicitação — o link
    // continua válido até expirar, pra o cliente poder voltar e completar aos
    // poucos (decisão de produto, ver plano de "Solicitação de senhas").
    public function registerSubmission(int $count): void
    {
        $this->first_submitted_at ??= now();
        $this->last_submitted_at = now();
        $this->submissions_count += $count;
        $this->save();
    }
}
