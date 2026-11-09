<?php

namespace App\Services\AdSync;

use App\Models\AdCampaign;
use App\Models\AiAgent;
use App\Models\CampaignLog;
use App\Models\GoogleAdCreation;
use App\Services\AiService;
use Illuminate\Support\Str;

/**
 * Anúncio de Pesquisa (responsivo) no Google — Central de Campanhas, etapa 5.
 *
 * O gestor escolhe um grupo de anúncios que já existe numa campanha de
 * Pesquisa; a IA sugere 15 títulos e 4 descrições (briefing + palavras-chave
 * + anúncios atuais); o gestor revisa e o App cria o anúncio PAUSADO, com o
 * mesmo endereço de destino dos anúncios do grupo. A UTM vem do sufixo da
 * conta (Contas e conexões). "Ativar" é separado.
 * Validado em 09/11/2026 com validateOnly.
 */
class GoogleSearchAdCreator
{
    public const MAX_HEADLINE = 30;
    public const MAX_DESCRIPTION = 90;
    public const MAX_PATH = 15;

    public bool $validateOnly = false;

    public function __construct(private GoogleAdsApi $google, private AiService $ai) {}

    public static function isSearch(AdCampaign $campaign): bool
    {
        return str_starts_with($campaign->platform, 'google') && strtoupper((string) $campaign->objective) === 'SEARCH';
    }

    /**
     * Grupos da campanha com os anúncios atuais e as palavras-chave principais.
     *
     * @return array<int, array{id: string, name: string, status: string, final_url: ?string, path1: ?string, path2: ?string, ads: array, keywords: array}>
     */
    public function adGroups(AdCampaign $campaign): array
    {
        $cid = GoogleAdsApi::cid($campaign->adAccount);
        $id = (int) $campaign->external_id;

        $groups = collect($this->google->search($cid, "SELECT ad_group.id, ad_group.name, ad_group.status FROM ad_group WHERE campaign.id = {$id} AND ad_group.status != 'REMOVED' ORDER BY ad_group.name"))
            ->mapWithKeys(fn ($r) => [(string) $r['adGroup']['id'] => [
                'id' => (string) $r['adGroup']['id'], 'name' => $r['adGroup']['name'], 'status' => $r['adGroup']['status'],
                'final_url' => null, 'path1' => null, 'path2' => null, 'ads' => [], 'keywords' => [],
            ]])->all();

        $ads = $this->google->search($cid, 'SELECT ad_group.id, ad_group_ad.status, ad_group_ad.ad_strength, ad_group_ad.ad.final_urls, '
            . 'ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions, '
            . 'ad_group_ad.ad.responsive_search_ad.path1, ad_group_ad.ad.responsive_search_ad.path2 '
            . "FROM ad_group_ad WHERE campaign.id = {$id} AND ad_group_ad.status != 'REMOVED' AND ad_group_ad.ad.type = 'RESPONSIVE_SEARCH_AD'");
        foreach ($ads as $r) {
            $gid = (string) $r['adGroup']['id'];
            if (!isset($groups[$gid])) {
                continue;
            }
            $ad = $r['adGroupAd']['ad'] ?? [];
            $rsa = $ad['responsiveSearchAd'] ?? [];
            $groups[$gid]['ads'][] = [
                'status'       => $r['adGroupAd']['status'] ?? null,
                'strength'     => $r['adGroupAd']['adStrength'] ?? null,
                'headlines'    => array_column($rsa['headlines'] ?? [], 'text'),
                'descriptions' => array_column($rsa['descriptions'] ?? [], 'text'),
            ];
            // Destino: o do anúncio ativo tem preferência.
            if (!$groups[$gid]['final_url'] || ($r['adGroupAd']['status'] ?? '') === 'ENABLED') {
                $groups[$gid]['final_url'] = $ad['finalUrls'][0] ?? $groups[$gid]['final_url'];
                $groups[$gid]['path1'] = $rsa['path1'] ?? $groups[$gid]['path1'];
                $groups[$gid]['path2'] = $rsa['path2'] ?? $groups[$gid]['path2'];
            }
        }

        try {
            $kw = $this->google->search($cid, 'SELECT ad_group.id, ad_group_criterion.keyword.text, metrics.impressions FROM keyword_view '
                . "WHERE campaign.id = {$id} AND ad_group_criterion.status != 'REMOVED' AND segments.date DURING LAST_30_DAYS ORDER BY metrics.impressions DESC LIMIT 200");
            foreach ($kw as $r) {
                $gid = (string) $r['adGroup']['id'];
                if (isset($groups[$gid]) && count($groups[$gid]['keywords']) < 15) {
                    $groups[$gid]['keywords'][] = $r['adGroupCriterion']['keyword']['text'] ?? '';
                }
            }
        } catch (\Throwable) {
            // sem palavras-chave (ex.: grupo novo) a IA trabalha só com o briefing
        }

        return array_values($groups);
    }

    /**
     * Sugestão da IA para um grupo.
     *
     * @return array{headlines: array<int, string>, descriptions: array<int, string>, path1: string, path2: string, note: ?string}
     */
    public function suggest(AdCampaign $campaign, array $group, string $request, int $userId): array
    {
        $agent = AiAgent::bySlug(AiAgent::SLUG_GOOGLE_SEARCH_WRITER)
            ?? throw new \RuntimeException('O agente "Redator de Anúncio de Pesquisa" não está cadastrado em Agentes & IA.');

        $campaign->loadMissing('adAccount.client', 'project');
        $client = $campaign->adAccount->client;
        $cut = fn ($v, $n = 2500) => $v ? Str::limit(trim(strip_tags((string) $v)), $n) : null;

        $input = [
            'cliente' => array_filter([
                'nome' => $client?->company_name, 'segmento' => $client?->segment, 'site' => $client?->website,
                'endereco' => $client?->address, 'briefing' => $cut($client?->briefing), 'regua_de_lead' => $cut($client?->lead_rule, 1200),
            ]),
            'campanha' => array_filter(['nome' => $campaign->name, 'descricao' => $cut($campaign->description, 1500)]),
            'projeto'  => $campaign->project ? array_filter(['titulo' => $campaign->project->title, 'objetivo' => $cut($campaign->project->objective, 1000), 'briefing_trafego' => $cut($campaign->project->briefing_trafego)]) : null,
            'grupo'    => ['nome' => $group['name'], 'palavras_chave' => $group['keywords']],
            'anuncios_atuais' => array_map(fn ($a) => ['forca' => $a['strength'], 'titulos' => $a['headlines'], 'descricoes' => $a['descriptions']], array_slice($group['ads'], 0, 3)),
            'pagina'   => $group['final_url'],
            'pedido'   => trim($request),
        ];

        $answer = $this->ai->runStructured($agent, json_encode($input, JSON_UNESCAPED_UNICODE), [], $userId, $client?->id, 'google_search_ad');

        $clean = fn ($list, $max) => collect($answer[$list] ?? [])->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->map(fn ($t) => trim(preg_replace('/\s+/', ' ', $t)))->unique(fn ($t) => mb_strtolower($t))->values();

        return [
            'headlines'    => $clean('titulos', self::MAX_HEADLINE)->take(15)->all(),
            'descriptions' => $clean('descricoes', self::MAX_DESCRIPTION)->take(4)->all(),
            'path1'        => self::slugPath($answer['caminho1'] ?? ''),
            'path2'        => self::slugPath($answer['caminho2'] ?? ''),
            'note'         => is_string($answer['observacao'] ?? null) ? $answer['observacao'] : null,
        ];
    }

    /**
     * Confere as regras antes de mandar para o Google.
     *
     * @return array<int, string> problemas (vazio = ok)
     */
    public static function problems(array $headlines, array $descriptions, ?string $path1, ?string $path2): array
    {
        $p = [];
        if (count($headlines) < 3 || count($headlines) > 15) {
            $p[] = 'Precisa de 3 a 15 títulos (tem ' . count($headlines) . ').';
        }
        if (count($descriptions) < 2 || count($descriptions) > 4) {
            $p[] = 'Precisa de 2 a 4 descrições (tem ' . count($descriptions) . ').';
        }
        foreach ($headlines as $i => $h) {
            if (mb_strlen($h) > self::MAX_HEADLINE) {
                $p[] = 'Título ' . ($i + 1) . ' passa de 30 caracteres.';
            }
            if (str_contains($h, '!')) {
                $p[] = 'Título ' . ($i + 1) . ' tem "!" (o Google não aceita em título).';
            }
        }
        foreach ($descriptions as $i => $d) {
            if (mb_strlen($d) > self::MAX_DESCRIPTION) {
                $p[] = 'Descrição ' . ($i + 1) . ' passa de 90 caracteres.';
            }
        }
        if (count(array_unique(array_map('mb_strtolower', $headlines))) !== count($headlines)) {
            $p[] = 'Há títulos repetidos.';
        }
        foreach ([$path1, $path2] as $path) {
            if ($path && mb_strlen($path) > self::MAX_PATH) {
                $p[] = "Caminho \"{$path}\" passa de 15 caracteres.";
            }
        }
        if (!$path1 && $path2) {
            $p[] = 'Para usar o caminho 2, preencha o caminho 1.';
        }

        return $p;
    }

    public function create(AdCampaign $campaign, array $group, array $input, bool $aiSuggested, int $userId): GoogleAdCreation
    {
        $cid = GoogleAdsApi::cid($campaign->adAccount);
        $rsa = array_filter([
            'headlines'    => array_map(fn ($t) => ['text' => $t], $input['headlines']),
            'descriptions' => array_map(fn ($t) => ['text' => $t], $input['descriptions']),
            'path1'        => $input['path1'] ?: null,
            'path2'        => $input['path1'] ? ($input['path2'] ?: null) : null,
        ]);

        $res = $this->google->mutate($cid, 'adGroupAds', [[
            'create' => [
                'adGroup' => "customers/{$cid}/adGroups/{$group['id']}",
                'status'  => 'PAUSED',
                'ad'      => ['finalUrls' => [$input['final_url']], 'responsiveSearchAd' => $rsa],
            ],
        ]], $this->validateOnly);

        $resource = $res['results'][0]['resourceName'] ?? null;
        $creation = new GoogleAdCreation([
            'organization_id'      => $campaign->organization_id,
            'ad_campaign_id'       => $campaign->id,
            'client_ad_account_id' => $campaign->client_ad_account_id,
            'ad_group_id'          => $group['id'],
            'ad_group_name'        => $group['name'],
            'ad_resource'          => $resource,
            'ad_id'                => $resource ? Str::afterLast($resource, '~') : null,
            'status'               => $res['ok'] ? 'pausado' : 'erro',
            'headlines'            => $input['headlines'],
            'descriptions'         => $input['descriptions'],
            'path1'                => $input['path1'] ?: null,
            'path2'                => $input['path2'] ?: null,
            'final_url'            => $input['final_url'],
            'ai_suggested'         => $aiSuggested,
            'error'                => $res['error'],
            'created_by'           => $userId,
        ]);

        if ($this->validateOnly) {
            return $creation; // conferência: nada gravado
        }

        $creation->save();
        if ($res['ok']) {
            $this->log($campaign, 'creative', "Anúncio de Pesquisa criado pelo App no grupo \"{$group['name']}\" (pausado). "
                . count($input['headlines']) . ' títulos, ' . count($input['descriptions']) . ' descrições' . ($aiSuggested ? ', textos sugeridos pela IA e revisados.' : '.'), $userId);
        }

        return $creation;
    }

    /** @return ?string erro, ou null se ativou */
    public function activate(GoogleAdCreation $creation, int $userId): ?string
    {
        $campaign = $creation->campaign()->with('adAccount')->firstOrFail();
        $res = $this->google->mutate(GoogleAdsApi::cid($campaign->adAccount), 'adGroupAds', [[
            'update' => ['resourceName' => $creation->ad_resource, 'status' => 'ENABLED'], 'updateMask' => 'status',
        ]], $this->validateOnly);

        if (!$res['ok'] || $this->validateOnly) {
            return $res['error'];
        }

        $creation->update(['status' => 'ativo', 'activated_by' => $userId, 'activated_at' => now()]);
        $this->log($campaign, 'resume', "Anúncio de Pesquisa do grupo \"{$creation->ad_group_name}\" ativado pela Central.", $userId);

        return null;
    }

    public static function slugPath(string $v): string
    {
        return mb_substr(trim(Str::slug($v), '-'), 0, self::MAX_PATH);
    }

    private function log(AdCampaign $campaign, string $type, string $text, int $userId): void
    {
        CampaignLog::create([
            'organization_id'      => $campaign->organization_id,
            'client_ad_account_id' => $campaign->client_ad_account_id,
            'entity_level'         => 'campaign',
            'entity_id'            => $campaign->external_id,
            'entity_name'          => $campaign->name,
            'platform'             => $campaign->platform,
            'logged_by'            => $userId,
            'type'                 => $type,
            'description'          => $text,
            'metadata'             => ['via' => 'central', 'kind' => 'google_search_ad'],
        ]);
    }
}
