<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HelpArticle extends Model
{
    use HasUuids, Tenantable;

    protected $connection = 'pgsql';

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'category',
        'body',
        'created_by',
        'updated_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Registra que a pessoa leu o capítulo agora (abrir = ler). Escrita direta, sem
     * timestamps do modelo — é só o "lido até" da pessoa.
     */
    public function markReadBy(?User $user): void
    {
        if (! $user) {
            return;
        }

        \Illuminate\Support\Facades\DB::connection('pgsql')->table('help_article_reads')->upsert(
            [['help_article_id' => $this->id, 'user_id' => $user->id, 'read_at' => now()]],
            ['help_article_id', 'user_id'],
            ['read_at'],
        );
    }

    /**
     * Capítulos que a pessoa ainda não leu na versão atual: nunca abriu, ou foram
     * atualizados depois da última leitura. Alimenta o contador do menu.
     */
    public static function unreadQueryFor(User $user)
    {
        return static::query()->whereNotExists(fn ($q) => $q->from('help_article_reads')
            ->whereColumn('help_article_reads.help_article_id', 'help_articles.id')
            ->where('help_article_reads.user_id', $user->id)
            ->whereColumn('help_article_reads.read_at', '>=', 'help_articles.updated_at'));
    }

    public static function naoLidosPor(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        // Roda no menu de toda página: se a consulta falhar (ex.: tabela ainda não criada no
        // meio de um deploy), o contador some em vez de derrubar a tela.
        try {
            return static::unreadQueryFor($user)->count();
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return 0;
        }
    }

    // Gera um slug único (por organização) a partir do título — igual pro artigo novo
    // e pra quando o título muda numa edição. Sufixo -2/-3/... só entra em caso de
    // colisão de verdade (dois artigos com título igual).
    public static function uniqueSlugFor(string $title, ?string $organizationId, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artigo';
        $slug = $base;
        $i = 2;

        while (
            static::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
