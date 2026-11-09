<?php

namespace App\Services\AdSync;

use App\Models\AdCampaign;
use App\Models\CampaignLog;
use App\Models\ClientAdAccount;
use App\Models\GoogleAssetCreation;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Arte aprovada → imagem num grupo de recursos da Performance Max (Central de
 * Campanhas, etapa 5b).
 *
 * O formato vem da proporção da arte: 1,91:1 paisagem, 1:1 quadrada, 4:5
 * vertical (outras proporções o Google não aceita sem recorte). A imagem sobe
 * para a biblioteca da conta e o vínculo com o grupo nasce PAUSADO; "Ativar"
 * troca o vínculo para ativo. Limite do Google: 20 imagens por grupo, 5 MB.
 * Validado em 09/11/2026 com validateOnly (vínculo PAUSED aceito).
 */
class GooglePmaxAssetCreator
{
    public const MAX_IMAGES_PER_GROUP = 20;
    private const MAX_BYTES = 5_000_000;

    /** proporção, tolerância, largura mínima, altura mínima */
    private const FORMATS = [
        'MARKETING_IMAGE'          => [1.91, 0.03, 600, 314],
        'SQUARE_MARKETING_IMAGE'   => [1.0, 0.02, 300, 300],
        'PORTRAIT_MARKETING_IMAGE' => [0.8, 0.02, 480, 600],
    ];

    public bool $validateOnly = false;

    public function __construct(private GoogleAdsApi $google) {}

    /**
     * Grupos de recursos das Performance Max ativas da conta, com quantas
     * imagens de cada formato já têm.
     *
     * @return array<int, array{id: string, resource: string, name: string, status: string, campaign_id: string, campaign_name: string, images: array<string, int>, total: int}>
     */
    public function assetGroups(ClientAdAccount $account): array
    {
        $cid = GoogleAdsApi::cid($account);
        $groups = collect($this->google->search($cid, 'SELECT campaign.id, campaign.name, asset_group.id, asset_group.name, asset_group.status, asset_group.resource_name '
            . "FROM asset_group WHERE campaign.advertising_channel_type = 'PERFORMANCE_MAX' AND campaign.status = 'ENABLED' AND asset_group.status != 'REMOVED' ORDER BY campaign.name, asset_group.name"))
            ->mapWithKeys(fn ($r) => [(string) $r['assetGroup']['id'] => [
                'id' => (string) $r['assetGroup']['id'], 'resource' => $r['assetGroup']['resourceName'], 'name' => $r['assetGroup']['name'],
                'status' => $r['assetGroup']['status'], 'campaign_id' => (string) $r['campaign']['id'], 'campaign_name' => $r['campaign']['name'],
                'images' => array_fill_keys(array_keys(self::FORMATS), 0), 'total' => 0,
            ]])->all();

        if ($groups) {
            $links = $this->google->search($cid, "SELECT asset_group.id, asset_group_asset.field_type FROM asset_group_asset WHERE campaign.advertising_channel_type = 'PERFORMANCE_MAX' "
                . "AND asset_group_asset.status != 'REMOVED' AND asset_group_asset.field_type IN ('MARKETING_IMAGE','SQUARE_MARKETING_IMAGE','PORTRAIT_MARKETING_IMAGE')");
            foreach ($links as $r) {
                $gid = (string) $r['assetGroup']['id'];
                if (isset($groups[$gid])) {
                    $groups[$gid]['images'][$r['assetGroupAsset']['fieldType']]++;
                    $groups[$gid]['total']++;
                }
            }
        }

        return array_values($groups);
    }

    /**
     * Formato que o Google vai usar para a arte (ou por que não dá).
     *
     * @return array{ok: bool, field_type: ?string, width: int, height: int, why_not: ?string}
     */
    public static function inspect(TaskAttachment $image): array
    {
        try {
            $size = @getimagesizefromstring(Storage::disk($image->disk)->get($image->disk_path)) ?: [0, 0];
        } catch (\Throwable) {
            $size = [0, 0];
        }
        [$w, $h] = [(int) $size[0], (int) $size[1]];
        if (!$w || !$h) {
            return ['ok' => false, 'field_type' => null, 'width' => 0, 'height' => 0, 'why_not' => 'Não deu para ler a imagem.'];
        }

        foreach (self::FORMATS as $field => [$ratio, $tol, $minW, $minH]) {
            if (abs($w / $h - $ratio) <= $tol) {
                return $w >= $minW && $h >= $minH
                    ? ['ok' => true, 'field_type' => $field, 'width' => $w, 'height' => $h, 'why_not' => null]
                    : ['ok' => false, 'field_type' => $field, 'width' => $w, 'height' => $h, 'why_not' => "Pequena demais ({$w}×{$h}); mínimo {$minW}×{$minH}."];
            }
        }

        return ['ok' => false, 'field_type' => null, 'width' => $w, 'height' => $h,
            'why_not' => "Proporção {$w}×{$h} não é aceita na Performance Max (só 1,91:1, 1:1 ou 4:5)."];
    }

    /**
     * Sobe as imagens e liga ao grupo, PAUSADAS. Tudo ou nada.
     *
     * @param Collection<int, TaskAttachment> $images
     * @return array{ok: bool, error: ?string, creations: array<int, GoogleAssetCreation>}
     */
    public function create(Task $task, ClientAdAccount $account, array $group, Collection $images, int $userId): array
    {
        $cid = GoogleAdsApi::cid($account);
        $ops = [];
        $plan = [];
        foreach ($images->values() as $i => $image) {
            $info = self::inspect($image);
            if (!$info['ok']) {
                return ['ok' => false, 'error' => "{$image->filename}: {$info['why_not']}", 'creations' => []];
            }
            $temp = "customers/{$cid}/assets/-" . ($i + 1);
            $ops[] = ['assetOperation' => ['create' => [
                'resourceName' => $temp,
                // Nome curto (o Google recusa nome longo): título da arte + id da imagem.
                'name'         => mb_substr("Nonna · {$task->title}", 0, 70) . ' · ' . substr($image->id, 0, 8),
                'type'         => 'IMAGE',
                'imageAsset'   => ['data' => base64_encode(self::bytes($image))],
            ]]];
            $ops[] = ['assetGroupAssetOperation' => ['create' => [
                'assetGroup' => $group['resource'], 'asset' => $temp, 'fieldType' => $info['field_type'], 'status' => 'PAUSED',
            ]]];
            $plan[] = [$image, $info['field_type']];
        }

        $res = $this->google->mutateMany($cid, $ops, $this->validateOnly);

        $creations = [];
        foreach ($plan as $i => [$image, $field]) {
            $creations[] = new GoogleAssetCreation([
                'organization_id'      => $task->organization_id,
                'task_id'              => $task->id,
                'task_attachment_id'   => $image->id,
                'client_ad_account_id' => $account->id,
                'campaign_external_id' => $group['campaign_id'],
                'campaign_name'        => $group['campaign_name'],
                'asset_group_id'       => $group['id'],
                'asset_group_name'     => $group['name'],
                'asset_resource'       => $res['results'][$i * 2]['assetResult']['resourceName'] ?? null,
                'link_resource'        => $res['results'][$i * 2 + 1]['assetGroupAssetResult']['resourceName'] ?? null,
                'field_type'           => $field,
                'status'               => $res['ok'] ? 'pausado' : 'erro',
                'error'                => $res['error'],
                'created_by'           => $userId,
            ]);
        }

        if ($this->validateOnly) {
            return ['ok' => $res['ok'], 'error' => $res['error'], 'creations' => $creations];
        }

        foreach ($creations as $c) {
            $c->save();
        }
        if ($res['ok']) {
            $this->log($account, $group, 'creative', count($creations) . " imagem(ns) da arte \"{$task->title}\" enviada(s) pelo App ao grupo \"{$group['name']}\" (pausadas).", $userId);
        }

        return ['ok' => $res['ok'], 'error' => $res['error'], 'creations' => $creations];
    }

    /**
     * Ativa o vínculo. Se o Google não aceitar trocar o status, remove o
     * vínculo pausado e cria de novo ativo (mesma imagem da biblioteca).
     *
     * @return ?string erro, ou null se ativou
     */
    public function activate(GoogleAssetCreation $creation, int $userId): ?string
    {
        $account = $creation->adAccount;
        $cid = GoogleAdsApi::cid($account);

        $res = $this->google->mutate($cid, 'assetGroupAssets', [[
            'update' => ['resourceName' => $creation->link_resource, 'status' => 'ENABLED'], 'updateMask' => 'status',
        ]], $this->validateOnly);

        if (!$res['ok']) {
            $group = "customers/{$cid}/assetGroups/{$creation->asset_group_id}";
            $retry = $this->google->mutateMany($cid, [
                ['assetGroupAssetOperation' => ['remove' => $creation->link_resource]],
                ['assetGroupAssetOperation' => ['create' => ['assetGroup' => $group, 'asset' => $creation->asset_resource, 'fieldType' => $creation->field_type]]],
            ], $this->validateOnly);
            if (!$retry['ok']) {
                return $res['error'];
            }
            $res = $retry;
            if (!$this->validateOnly) {
                $creation->link_resource = $retry['results'][1]['assetGroupAssetResult']['resourceName'] ?? $creation->link_resource;
            }
        }

        if ($this->validateOnly) {
            return null;
        }

        $creation->fill(['status' => 'ativo', 'activated_by' => $userId, 'activated_at' => now()])->save();
        $this->log($account, ['campaign_id' => $creation->campaign_external_id, 'campaign_name' => $creation->campaign_name], 'resume',
            "Imagem ({$creation->fieldLabel()}) ativada no grupo \"{$creation->asset_group_name}\" pela Central.", $userId);

        return null;
    }

    /** Bytes para o Google; acima de 5 MB vira JPEG (mesmo tamanho em pixels). */
    private static function bytes(TaskAttachment $image): string
    {
        $bytes = Storage::disk($image->disk)->get($image->disk_path);
        if (strlen($bytes) <= self::MAX_BYTES) {
            return $bytes;
        }

        $gd = imagecreatefromstring($bytes);
        foreach ([88, 80, 70] as $quality) {
            ob_start();
            imagejpeg($gd, null, $quality);
            $jpeg = ob_get_clean();
            if (strlen($jpeg) <= self::MAX_BYTES) {
                break;
            }
        }
        imagedestroy($gd);

        return $jpeg;
    }

    private function log(ClientAdAccount $account, array $group, string $type, string $text, int $userId): void
    {
        $campaign = AdCampaign::where('client_ad_account_id', $account->id)->where('external_id', $group['campaign_id'])->first();

        CampaignLog::create([
            'organization_id'      => $campaign?->organization_id ?? $account->client?->organization_id,
            'client_ad_account_id' => $account->id,
            'entity_level'         => 'campaign',
            'entity_id'            => $group['campaign_id'],
            'entity_name'          => $campaign?->name ?? $group['campaign_name'],
            'platform'             => 'google',
            'logged_by'            => $userId,
            'type'                 => $type,
            'description'          => $text,
            'metadata'             => ['via' => 'central', 'kind' => 'google_pmax_image'],
        ]);
    }
}
