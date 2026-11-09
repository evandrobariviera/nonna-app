<?php

namespace App\Services\AdSync;

use App\Models\AdAccountConnection;
use App\Models\AdCampaign;
use App\Models\CampaignLog;
use App\Models\ClientAdAccount;
use App\Models\MetaAdCreation;
use App\Models\OrganizationIntegration;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Arte aprovada → anúncio na Meta (etapa 3 da Central de Campanhas).
 *
 * O destino do anúncio (formulário de cadastro, WhatsApp, link do site) é
 * COPIADO de um anúncio que já existe no conjunto escolhido — assim vale para
 * qualquer tipo de campanha que o gestor já montou, e o App só troca arte,
 * texto e título. Sem anúncio modelo, só conjunto de site (link digitado).
 *
 * Regras: todo anúncio nasce PAUSADO; leva a UTM padrão da Nonna; criação e
 * ativação ficam no diário da campanha. Conjunto de múltiplos destinos
 * (Direct + WhatsApp) usa outro formato de criativo e ainda não é suportado.
 * Validado em 09/11/2026 com execution_options=validate_only (WhatsApp,
 * formulário de cadastro).
 */
class MetaAdCreator
{
    /** Conjuntos com mais de um destino pedem asset_feed_spec — fora da v1. */
    private const UNSUPPORTED_DESTINATIONS = ['MESSAGING_INSTAGRAM_DIRECT_WHATSAPP', 'MESSAGING_MESSENGER_WHATSAPP', 'MESSAGING_INSTAGRAM_DIRECT_MESSENGER', 'MESSAGING_INSTAGRAM_DIRECT_MESSENGER_WHATSAPP'];

    public const CTA_OPTIONS = [
        'LEARN_MORE' => 'Saiba mais', 'SHOP_NOW' => 'Comprar agora', 'SIGN_UP' => 'Cadastre-se',
        'CONTACT_US' => 'Fale conosco', 'GET_QUOTE' => 'Pedir orçamento', 'BOOK_TRAVEL' => 'Reservar',
        'WHATSAPP_MESSAGE' => 'Enviar mensagem (WhatsApp)', 'MESSAGE_PAGE' => 'Enviar mensagem',
    ];

    private ?string $token = null;

    /**
     * Modo conferência: manda o pedido real à Meta com validate_only e não
     * cria nem grava nada (imagem: usa uma que já está na biblioteca da conta).
     * Usado para testar contra contas reais sem gastar nem poluir.
     */
    public bool $validateOnly = false;

    /** Conjuntos ativos/pausados da conta, cada um com o destino que o anúncio vai copiar. */
    public function adsets(ClientAdAccount $account): array
    {
        $rows = $this->get("act_{$account->account_id}/adsets", [
            'fields'           => 'id,name,status,end_time,destination_type,optimization_goal,campaign{id,name,status,objective,stop_time}',
            'effective_status' => json_encode(['ACTIVE', 'PAUSED', 'CAMPAIGN_PAUSED']),
            'limit'            => 100,
        ])['data'] ?? [];

        $templates = $this->templatesByAdset($account);

        return collect($rows)->map(function ($s) use ($templates) {
            $destination = $s['destination_type'] ?? null;
            $template = in_array($destination, self::UNSUPPORTED_DESTINATIONS, true) ? null : ($templates[$s['id']] ?? null);
            $endsAt = $s['end_time'] ?? ($s['campaign']['stop_time'] ?? null);
            $ended = $endsAt && strtotime($endsAt) < time();
            $supported = !$ended && !in_array($destination, self::UNSUPPORTED_DESTINATIONS, true)
                && ($template !== null || in_array($destination, [null, 'WEBSITE', 'UNDEFINED'], true));

            return [
                'id'            => $s['id'],
                'name'          => $s['name'],
                'status'        => $s['status'] ?? null,
                'campaign_id'   => $s['campaign']['id'] ?? null,
                'campaign_name' => $s['campaign']['name'] ?? null,
                'destination'   => $this->destinationLabel($destination, $template),
                'supported'     => $supported,
                'why_not'       => $supported ? null : ($ended
                    ? 'O conjunto terminou em ' . date('d/m/Y', strtotime($endsAt)) . ': escolha outro ou prorrogue a data no Gerenciador.'
                    : (in_array($destination, self::UNSUPPORTED_DESTINATIONS, true)
                    ? 'Conjunto com mais de um destino (Direct + WhatsApp): por enquanto, crie no Gerenciador.'
                    : 'Conjunto sem nenhum anúncio para copiar o destino (formulário/WhatsApp): crie o primeiro no Gerenciador.')),
                'needs_link'    => $template === null || !empty($template['link_editable']),
                'link'          => $template['link'] ?? null,
                'cta'           => $template['cta']['type'] ?? 'LEARN_MORE',
                'template_ad'   => $template['ad_id'] ?? null,
            ];
        })->sortBy(fn ($s) => [$s['supported'] ? 0 : 1, $s['campaign_name'], $s['name']])->values()->all();
    }

    /** Prévia do feed sem criar nada (generatepreviews com a URL da imagem). */
    public function preview(ClientAdAccount $account, array $input, TaskAttachment $image): array
    {
        $spec = $this->spec($account, $input, ['picture' => $image->url()]);
        $res = $this->get("act_{$account->account_id}/generatepreviews", [
            'creative'  => json_encode(['object_story_spec' => $spec]),
            'ad_format' => $input['format'] ?? 'MOBILE_FEED_STANDARD',
        ]);

        return isset($res['error'])
            ? ['ok' => false, 'error' => $this->errorText($res)]
            : ['ok' => true, 'html' => $res['data'][0]['body'] ?? ''];
    }

    /**
     * Sobe a imagem e cria o anúncio PAUSADO no conjunto.
     *
     * @param array{adset_id: string, message: string, headline: string, link?: ?string, cta?: ?string} $input
     */
    public function create(Task $task, ClientAdAccount $account, TaskAttachment $image, array $input, int $userId, bool $captionChanged): MetaAdCreation
    {
        $adset = collect($this->adsets($account))->firstWhere('id', $input['adset_id']);
        abort_unless($adset && $adset['supported'], 422, 'Conjunto não suportado.');

        $creation = new MetaAdCreation([
            'organization_id'      => $task->organization_id,
            'task_id'              => $task->id,
            'task_attachment_id'   => $image->id,
            'client_ad_account_id' => $account->id,
            'campaign_external_id' => $adset['campaign_id'],
            'campaign_name'        => $adset['campaign_name'],
            'adset_external_id'    => $adset['id'],
            'adset_name'           => $adset['name'],
            'template_ad_id'       => $adset['template_ad'],
            'message'              => $input['message'],
            'headline'             => $input['headline'],
            'link'                 => $input['link'] ?? $adset['link'],
            'cta_type'             => $input['cta'] ?? $adset['cta'],
            'caption_changed'      => $captionChanged,
            'created_by'           => $userId,
        ]);

        $hash = $this->uploadImage($account, $image);
        $template = $adset['template_ad'] ? $this->template($adset['id']) : null;
        $creative = array_filter([
            'object_story_spec'       => $this->spec($account, $input, ['image_hash' => $hash], $template),
            'url_tags'                => AdAccountConnectionChecker::META_UTM,
            'degrees_of_freedom_spec' => $template['dof'] ?? null,
        ]);

        $name = mb_substr('[App] ' . $task->title, 0, 120);
        $res = $this->post("act_{$account->account_id}/ads", array_filter([
            'name'              => $name,
            'adset_id'          => $adset['id'],
            'status'            => 'PAUSED',
            'creative'          => json_encode($creative),
            'execution_options' => $this->validateOnly ? json_encode(['validate_only']) : null,
        ]));

        if ($this->validateOnly) {
            $creation->fill(isset($res['error']) ? ['status' => 'erro', 'error' => $this->errorText($res)] : ['status' => 'pausado', 'ad_name' => $name]);

            return $creation; // conferência: nada é gravado
        }

        if (isset($res['error'])) {
            $creation->fill(['status' => 'erro', 'error' => $this->errorText($res)])->save();

            return $creation;
        }

        $ad = $this->get($res['id'], ['fields' => 'id,name,creative{id}']);
        $creation->fill(['status' => 'pausado', 'ad_id' => $res['id'], 'creative_id' => $ad['creative']['id'] ?? null, 'ad_name' => $name])->save();

        $this->log($creation, 'creative', "Anúncio criado pelo App a partir da arte aprovada \"{$task->title}\" (nasceu pausado). Conjunto: {$adset['name']}.");

        return $creation;
    }

    /** Liga o anúncio. Só gasta se o conjunto e a campanha também estiverem ativos. */
    public function activate(MetaAdCreation $creation, int $userId): ?string
    {
        $res = $this->post($creation->ad_id, ['status' => 'ACTIVE']);
        if (isset($res['error'])) {
            return $this->errorText($res);
        }

        $creation->update(['status' => 'ativo', 'activated_by' => $userId, 'activated_at' => now()]);
        $this->log($creation, 'resume', "Anúncio \"{$creation->ad_name}\" ativado pelo App.");

        return null;
    }

    /** Página/Instagram da conta (Contas e conexões). */
    public function connection(ClientAdAccount $account): ?AdAccountConnection
    {
        return AdAccountConnection::where('client_ad_account_id', $account->id)->first();
    }

    /** Destino copiado do anúncio mais recente do conjunto (CTA, link, formulário e recursos criativos). */
    private function template(string $adsetId): ?array
    {
        return $this->parseTemplate($this->get("{$adsetId}/ads", ['fields' => 'id,creative{object_story_spec,degrees_of_freedom_spec}', 'limit' => 1])['data'][0] ?? null);
    }

    /** Modelo de cada conjunto numa chamada só (anúncio mais recente por conjunto). */
    private function templatesByAdset(ClientAdAccount $account): array
    {
        $ads = $this->get("act_{$account->account_id}/ads", [
            'fields'           => 'id,adset_id,created_time,creative{object_story_spec,degrees_of_freedom_spec}',
            'effective_status' => json_encode(['ACTIVE', 'PAUSED', 'ADSET_PAUSED', 'CAMPAIGN_PAUSED']),
            'limit'            => 100,
        ])['data'] ?? [];

        return collect($ads)->sortByDesc('created_time')->groupBy('adset_id')
            ->map(fn ($group) => $group->map(fn ($ad) => $this->parseTemplate($ad))->filter()->first())
            ->filter()->all();
    }

    private function parseTemplate(?array $ad): ?array
    {
        $oss = $ad['creative']['object_story_spec'] ?? [];
        $src = $oss['link_data'] ?? $oss['video_data'] ?? null;
        if (!$ad || !$src || empty($src['call_to_action'])) {
            return null;
        }

        $cta = $src['call_to_action'];
        $link = $src['link'] ?? ($cta['value']['link'] ?? null);

        // "Aprimoramentos padrão" foi descontinuado pela Meta; os recursos
        // individuais que o gestor escolheu no anúncio modelo são mantidos.
        $dof = $ad['creative']['degrees_of_freedom_spec'] ?? null;
        if ($dof) {
            unset($dof['creative_features_spec']['standard_enhancements']);
            if (empty($dof['creative_features_spec'])) {
                $dof = null;
            }
        }

        $isForm = isset($cta['value']['lead_gen_form_id']);
        $isMessaging = in_array($cta['type'], ['WHATSAPP_MESSAGE', 'MESSAGE_PAGE', 'INSTAGRAM_MESSAGE'], true);

        return [
            'ad_id'         => $ad['id'],
            'cta'           => $cta,
            'link'          => $link,
            'link_editable' => !$isForm && !$isMessaging,
            'kind'          => $isForm ? 'form' : ($isMessaging ? 'message' : 'site'),
            'dof'           => $dof,
        ];
    }

    private function spec(ClientAdAccount $account, array $input, array $media, ?array $template = null): array
    {
        $conn = $this->connection($account);
        abort_unless($conn?->page_id, 422, 'Esta conta não tem Página liberada para o App (veja Contas e conexões).');

        $template ??= !empty($input['adset_id']) ? $this->template($input['adset_id']) : null;

        $cta = $template['cta'] ?? ['type' => $input['cta'] ?? 'LEARN_MORE'];
        $link = ($template && !$template['link_editable']) ? $template['link'] : ($input['link'] ?? $template['link'] ?? null);
        if ($template && $template['link_editable'] && !empty($input['link'])) {
            $cta['value']['link'] = $input['link'];
        }

        return array_filter([
            'page_id'           => $conn->page_id,
            'instagram_user_id' => $conn->instagram_id,
            'link_data'         => array_filter($media + [
                'link'           => $link ?: 'https://www.facebook.com/' . $conn->page_id,
                'message'        => $input['message'] ?? '',
                'name'           => $input['headline'] ?? null,
                'call_to_action' => $cta,
            ]),
        ]);
    }

    private function uploadImage(ClientAdAccount $account, TaskAttachment $image): string
    {
        if ($this->validateOnly) {
            return $this->get("act_{$account->account_id}/adimages", ['fields' => 'hash', 'limit' => 1])['data'][0]['hash'] ?? '';
        }

        $bytes = Storage::disk($image->disk)->get($image->disk_path);
        $res = $this->post("act_{$account->account_id}/adimages", ['bytes' => base64_encode($bytes)]);
        $hash = collect($res['images'] ?? [])->first()['hash'] ?? null;
        if (!$hash) {
            throw new \RuntimeException('A Meta recusou a imagem: ' . $this->errorText($res));
        }

        return $hash;
    }

    private function log(MetaAdCreation $creation, string $type, string $text): void
    {
        $campaign = AdCampaign::where('client_ad_account_id', $creation->client_ad_account_id)
            ->where('external_id', $creation->campaign_external_id)->first();

        CampaignLog::create([
            'organization_id'      => $campaign?->organization_id ?? $creation->organization_id,
            'client_ad_account_id' => $creation->client_ad_account_id,
            'entity_level'         => 'campaign',
            'entity_id'            => $creation->campaign_external_id,
            'entity_name'          => $creation->campaign_name,
            'platform'             => 'meta',
            'logged_by'            => $creation->activated_by ?? $creation->created_by,
            'type'                 => $type,
            'description'          => $text,
            'metadata'             => ['meta_ad_creation_id' => $creation->id, 'ad_id' => $creation->ad_id],
        ]);
    }

    private function destinationLabel(?string $destination, ?array $template): string
    {
        return match ($template['kind'] ?? null) {
            'form'    => 'Formulário de cadastro',
            'message' => 'Mensagem (WhatsApp/Direct)',
            'site'    => 'Site: ' . ($template['link'] ?? ''),
            default   => match ($destination) {
                'WHATSAPP'                                       => 'WhatsApp',
                'ON_AD'                                          => 'Formulário de cadastro',
                null, 'WEBSITE', 'UNDEFINED'                     => 'Site',
                default                                          => $destination,
            },
        };
    }

    private function get(string $path, array $query = []): array
    {
        return Http::timeout(30)->get('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . '/' . $path, $query + ['access_token' => $this->token()])->json() ?? [];
    }

    private function post(string $path, array $data): array
    {
        return Http::timeout(60)->asForm()->post('https://graph.facebook.com/' . MetaAdsFetcher::API_VERSION . '/' . $path, $data + ['access_token' => $this->token()])->json() ?? [];
    }

    private function token(): string
    {
        return $this->token ??= OrganizationIntegration::where('provider', 'meta')->where('status', 'connected')->first()?->credentials['access_token']
            ?? throw new \RuntimeException('Integração com a Meta não está conectada.');
    }

    private function errorText(array $res): string
    {
        return (string) ($res['error']['error_user_msg'] ?? $res['error']['message'] ?? 'erro desconhecido');
    }
}
