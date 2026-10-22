<?php

namespace App\Console\Commands;

use App\Models\AppUpdate;
use App\Models\HelpArticle;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Publica Novidades (app_updates) e artigos da Central de Ajuda (help_articles) a partir de
 * um arquivo JSON versionado em .claude/conteudo/ — é o passo final de toda entrega
 * importante (ver CLAUDE.md, "Novidades e Central de Ajuda").
 *
 * Formato:
 * {
 *   "novidades": [{"title", "summary", "body"?, "kind": novidade|melhoria|correcao, "area", "published_at"?}],
 *   "ajuda":     [{"title", "category", "slug"?, "body_file": "arquivo.html" (relativo ao JSON)}]
 * }
 *
 * Pode rodar de novo sem duplicar: novidade com o mesmo título é pulada; artigo com o
 * mesmo slug é atualizado (título, categoria e texto).
 */
class PublishAppContent extends Command
{
    protected $signature = 'conteudo:publicar {arquivo : JSON com novidades e/ou artigos}
        {--org= : Organização (id) — padrão: a única ativa}
        {--user=1 : Quem aparece como autor}
        {--dry : Só mostra o que faria}';

    protected $description = 'Publica Novidades e artigos da Central de Ajuda a partir de um JSON';

    public function handle(): int
    {
        $path = $this->argument('arquivo');
        $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;
        if (!is_array($data)) {
            $this->error("Arquivo não encontrado ou JSON inválido: {$path}");
            return self::FAILURE;
        }

        $orgs = Organization::whereIn('status', ['trial', 'active'])->pluck('id');
        $orgId = $this->option('org') ?: ($orgs->count() === 1 ? $orgs->first() : null);
        if (!$orgId) {
            $this->error('Mais de uma organização ativa — informe --org.');
            return self::FAILURE;
        }

        $userId = (int) $this->option('user');
        $dry = (bool) $this->option('dry');

        foreach ($data['novidades'] ?? [] as $item) {
            $exists = AppUpdate::withoutGlobalScopes()->where('organization_id', $orgId)->where('title', $item['title'])->exists();
            if ($exists) {
                $this->line("  = novidade já existe: {$item['title']}");
                continue;
            }
            if (!isset(AppUpdate::$kinds[$item['kind'] ?? ''])) {
                $this->error("Tipo inválido em \"{$item['title']}\": " . ($item['kind'] ?? '—'));
                return self::FAILURE;
            }
            $this->info("  + novidade [{$item['kind']}/{$item['area']}] {$item['title']}");
            if (!$dry) {
                AppUpdate::withoutGlobalScopes()->create([
                    'organization_id' => $orgId,
                    'title'           => $item['title'],
                    'summary'         => $item['summary'] ?? null,
                    'body'            => $item['body'] ?? null,
                    'kind'            => $item['kind'],
                    'area'            => $item['area'] ?? null,
                    'published_at'    => isset($item['published_at']) ? Carbon::parse($item['published_at']) : now(),
                    'created_by'      => $userId,
                ]);
            }
        }

        foreach ($data['ajuda'] ?? [] as $item) {
            $bodyPath = dirname($path) . DIRECTORY_SEPARATOR . $item['body_file'];
            if (!is_file($bodyPath)) {
                $this->error("Texto do artigo não encontrado: {$bodyPath}");
                return self::FAILURE;
            }
            $body = trim(file_get_contents($bodyPath));

            $article = !empty($item['slug'])
                ? HelpArticle::withoutGlobalScopes()->where('organization_id', $orgId)->where('slug', $item['slug'])->first()
                : null;

            if ($article) {
                $this->info("  ~ artigo atualizado: {$item['title']} (/ajuda/{$article->slug})");
                if (!$dry) {
                    $article->update([
                        'title'      => $item['title'],
                        'category'   => $item['category'] ?? $article->category,
                        'body'       => $body,
                        'updated_by' => $userId,
                    ]);
                }
                continue;
            }

            $slug = HelpArticle::uniqueSlugFor($item['slug'] ?? $item['title'], $orgId);
            $this->info("  + artigo novo: {$item['title']} (/ajuda/{$slug})");
            if (!$dry) {
                HelpArticle::withoutGlobalScopes()->create([
                    'organization_id' => $orgId,
                    'title'           => $item['title'],
                    'slug'            => $slug,
                    'category'        => $item['category'] ?? null,
                    'body'            => $body,
                    'created_by'      => $userId,
                    'updated_by'      => $userId,
                ]);
            }
        }

        $this->info($dry ? 'Simulação — nada gravado.' : 'Publicado.');

        return self::SUCCESS;
    }
}
