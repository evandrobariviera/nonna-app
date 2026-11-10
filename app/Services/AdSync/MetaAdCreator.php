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
 * (Direct + WhatsApp) copia a lista de destinos (asset_feed_spec) do modelo;
 * a mensagem de boas-vindas da conversa também é copiada.
 * Validado em 09/11/2026 com execution_options=validate_only (WhatsApp,
 * formulário de cadastro).
 */
class MetaAdCreator
{
    /**
     * Conjuntos com mais de um destino (Direct + WhatsApp…): o anúncio leva a
     * lista de destinos (asset_feed_spec) copiada do anúncio modelo. Sem modelo
     * com essa lista, não dá para montar.
     */
    private const MULTI_DESTINATIONS = ['MESSAGING_INSTAGRAM_DIRECT_WHATSAPP', 'MESSAGING_MESSENGER_WHATSAPP', 'MESSAGING_INSTAGRAM_DIRECT_MESSENGER', 'MESSAGING_INSTAGRAM_DIRECT_MESSENGER_WHATSAPP'];

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
    public function adsets(ClientAdAccount $account, bool $fresh = false): array
    {
        // Conta grande (190+ conjuntos) leva ~20 s na Meta: guarda 10 min.
        $key = "meta_adsets:{$account->id}";
        if ($fresh) {
            \Illuminate\Support\Facades\Cache::forget($key);
        }

        return \Illuminate\Support\Facades\Cache::remember($key, 600, fn () => $this->fetchAdsets($account));
    }

    private function fetchAdsets(ClientAdAccount $account): array
    {
        // Todas as páginas de conjuntos, cada um já com seus anúncios mais
        // recentes (de onde sai o modelo). Antes: só 100 conjuntos e 100
        // anúncios por conta — em 10/11/2026, 298 conjuntos apareciam "sem
        // anúncio para copiar" tendo anúncio.
        $rows = [];
        $query = [
            'fields'           => 'id,name,status,end_time,destination_type,optimization_goal,campaign{id,name,status,objective,stop_time},'
                . 'ads.limit(3){id,created_time,creative{object_story_spec,degrees_of_freedom_spec,asset_feed_spec}}',
            'effective_status' => json_encode(['ACTIVE', 'PAUSED', 'CAMPAIGN_PAUSED']),
            'limit'            => 100,
        ];
        for ($page = 0; $page < 5; $page++) {
            $res = $this->get("act_{$account->account_id}/adsets", $query);
            $rows = array_merge($rows, $res['data'] ?? []);
            if (empty($res['paging']['next']) || empty($res['paging']['cursors']['after'])) {
                break;
            }
            $query['after'] = $res['paging']['cursors']['after'];
        }

        $templates = collect($rows)->mapWithKeys(fn ($s) => [$s['id'] => collect($s['ads']['data'] ?? [])
            ->sortByDesc('created_time')->map(fn ($ad) => $this->parseTemplate($ad))->filter()->first()])->filter()->all();

        return collect($rows)->map(function ($s) use ($templates) {
            $destination = $s['destination_type'] ?? null;
            $multi = in_array($destination, self::MULTI_DESTINATIONS, true);
            $template = $templates[$s['id']] ?? ($destination === 'WHATSAPP' ? self::whatsappTemplate() : null);
            if ($multi && empty($template['feed'])) {
                $template = null;
            }
            $endsAt = $s['end_time'] ?? ($s['campaign']['stop_time'] ?? null);
            $ended = $endsAt && strtotime($endsAt) < time();
            $supported = !$ended && ($template !== null || (!$multi && in_array($destination, [null, 'WEBSITE', 'UNDEFINED'], true)));

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
                    : ($multi
                    ? 'Conjunto com mais de um destino (Direct + WhatsApp) sem nenhum anúncio para copiar os destinos: crie o primeiro no Gerenciador.'
                    : match ($destination) {
                        'INSTAGRAM_PROFILE' => 'Conjunto de visitas ao perfil do Instagram: ele impulsiona um post; faça pelo Instagram ou pelo Gerenciador.',
                        'ON_POST', 'ON_VIDEO' => 'Conjunto de engajamento com post/vídeo: impulsione o post pelo Gerenciador.',
                        default => 'Nenhum anúncio do conjunto serve de modelo (são posts impulsionados ou criativo dinâmico): crie o primeiro no Gerenciador.',
                    })),
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
        if (self::isVideo($image)) {
            return ['ok' => false, 'error' => 'Para vídeo, a prévia da Meta só existe depois que o vídeo sobe. Confira o vídeo e o texto aqui; o anúncio nasce pausado e dá para ver a prévia no Gerenciador antes de ativar.'];
        }

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

        if (self::isVideo($image)) {
            return $this->createFromVideo($task, $account, $adset, $image, $input, $creation);
        }

        $hash = $this->uploadImage($account, $image);

        return $this->postAd($task->title, $account, $adset, $input, ['image_hash' => $hash], $creation);
    }

    public static function isVideo(TaskAttachment $file): bool
    {
        return str_starts_with((string) $file->mime_type, 'video/');
    }

    /**
     * Vídeo: a Meta baixa o arquivo direto do R2 e processa por alguns
     * minutos. O anúncio só pode ser criado depois — fica "processando" e
     * FinishMetaVideoAdJob termina (conferindo a cada 20 s).
     */
    private function createFromVideo(Task $task, ClientAdAccount $account, array $adset, TaskAttachment $video, array $input, MetaAdCreation $creation): MetaAdCreation
    {
        if ($this->validateOnly) {
            // Conferência: usa um vídeo que já está na biblioteca da conta.
            $videoId = $this->get("act_{$account->account_id}/advideos", ['fields' => 'id', 'limit' => 1])['data'][0]['id'] ?? '';

            return $this->postAd($task->title, $account, $adset, $input, ['video_id' => $videoId, 'image_url' => $this->videoThumbnail($videoId)], $creation);
        }

        $res = $this->post("act_{$account->account_id}/advideos", [
            'file_url' => $video->url(),
            'name'     => mb_substr('[App] ' . $task->title, 0, 120),
        ]);
        if (empty($res['id'])) {
            $creation->fill(['status' => 'erro', 'error' => 'A Meta recusou o vídeo: ' . $this->errorText($res)])->save();

            return $creation;
        }

        $creation->fill(['status' => 'processando', 'meta_video_id' => $res['id']])->save();
        \App\Jobs\FinishMetaVideoAdJob::dispatch($creation->id)->delay(now()->addSeconds(20));

        return $creation;
    }

    /**
     * Segunda parte do vídeo: se a Meta terminou de processar, cria o anúncio.
     *
     * @return string 'pronto' | 'aguardando' | 'erro'
     */
    public function finishVideo(MetaAdCreation $creation): string
    {
        if ($creation->status !== 'processando') {
            return 'pronto';
        }

        $status = $this->get($creation->meta_video_id, ['fields' => 'status'])['status']['video_status'] ?? null;
        if ($status === 'error') {
            $creation->update(['status' => 'erro', 'error' => 'A Meta não conseguiu processar o vídeo (formato ou arquivo com problema).']);

            return 'erro';
        }
        if ($status !== 'ready') {
            return 'aguardando';
        }

        $account = $creation->adAccount;
        $adset = collect($this->adsets($account))->firstWhere('id', $creation->adset_external_id);
        if (!$adset || !$adset['supported']) {
            $creation->update(['status' => 'erro', 'error' => 'O conjunto não está mais disponível para receber o anúncio.']);

            return 'erro';
        }

        $input = ['adset_id' => $adset['id'], 'message' => $creation->message, 'headline' => $creation->headline, 'link' => $creation->link, 'cta' => $creation->cta_type];
        $this->postAd($creation->task?->title ?? $creation->ad_name ?? 'Vídeo', $account, $adset, $input,
            ['video_id' => $creation->meta_video_id, 'image_url' => $this->videoThumbnail($creation->meta_video_id)], $creation);

        return $creation->status === 'erro' ? 'erro' : 'pronto';
    }

    /** Capa do vídeo: a que a própria Meta escolheu como preferida. */
    private function videoThumbnail(string $videoId): ?string
    {
        $thumbs = $this->get("{$videoId}/thumbnails")['data'] ?? [];

        return collect($thumbs)->firstWhere('is_preferred', true)['uri'] ?? ($thumbs[0]['uri'] ?? null);
    }

    /** Cria o anúncio PAUSADO (imagem ou vídeo) e grava o resultado. */
    private function postAd(string $title, ClientAdAccount $account, array $adset, array $input, array $media, MetaAdCreation $creation): MetaAdCreation
    {
        $template = $this->template($adset['id']);
        $dof = $template['dof'] ?? null;
        if ($dof && isset($media['video_id'])) {
            // recursos de imagem do anúncio modelo não valem para vídeo
            $dof['creative_features_spec'] = array_filter($dof['creative_features_spec'] ?? [], fn ($k) => !str_starts_with($k, 'image_'), ARRAY_FILTER_USE_KEY);
            $dof = $dof['creative_features_spec'] ? $dof : null;
        }
        $creative = array_filter([
            'object_story_spec'       => $this->spec($account, $input, $media, $template),
            'url_tags'                => AdAccountConnectionChecker::META_UTM,
            'degrees_of_freedom_spec' => $dof,
            'asset_feed_spec'         => $template['feed'] ?? null,
        ]);

        $name = mb_substr('[App] ' . $title, 0, 120);
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

        $kind = isset($media['video_id']) ? 'o vídeo aprovado' : 'a arte aprovada';
        $this->log($creation, 'creative', "Anúncio criado pelo App a partir d{$kind} \"{$title}\" (nasceu pausado). Conjunto: {$adset['name']}.");

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

    /**
     * Destino copiado de um anúncio recente do conjunto (CTA, link, formulário,
     * boas-vindas, vários destinos e recursos criativos). Conjunto de WhatsApp
     * sem anúncio aproveitável recebe o botão padrão de WhatsApp.
     */
    private function template(string $adsetId): ?array
    {
        $s = $this->get($adsetId, ['fields' => 'destination_type,ads.limit(3){id,created_time,creative{object_story_spec,degrees_of_freedom_spec,asset_feed_spec}}']);

        return collect($s['ads']['data'] ?? [])->sortByDesc('created_time')->map(fn ($ad) => $this->parseTemplate($ad))->filter()->first()
            ?? (($s['destination_type'] ?? null) === 'WHATSAPP' ? self::whatsappTemplate() : null);
    }

    /**
     * Botão padrão de WhatsApp (número ligado à Página), para conjunto de
     * WhatsApp cujos anúncios são post impulsionado ou criativo dinâmico.
     * Validado em 10/11/2026 (validate_only).
     */
    private static function whatsappTemplate(): array
    {
        return [
            'ad_id' => null, 'cta' => ['type' => 'WHATSAPP_MESSAGE', 'value' => ['app_destination' => 'WHATSAPP']],
            'link' => 'https://api.whatsapp.com/send', 'link_editable' => false, 'kind' => 'message',
            'dof' => null, 'welcome' => null, 'feed' => null, 'synthetic' => true,
        ];
    }

    /** O que o anúncio novo copia de um anúncio que já existe no conjunto. */
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
            // Mensagem de boas-vindas da conversa (WhatsApp/Direct) — vem do anúncio modelo.
            'welcome'       => $src['page_welcome_message'] ?? null,
            // Vários destinos: só a lista de botões e o tipo de otimização.
            'feed'          => ($ad['creative']['asset_feed_spec']['optimization_type'] ?? null) === 'DOF_MESSAGING_DESTINATION'
                ? array_intersect_key($ad['creative']['asset_feed_spec'], array_flip(['call_to_actions', 'optimization_type', 'additional_data']))
                : null,
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

        $link = $link ?: 'https://www.facebook.com/' . $conn->page_id;

        if (isset($media['video_id'])) {
            // Vídeo não tem campo "link": o destino vai dentro do botão.
            if (!isset($cta['value']['lead_gen_form_id']) && !isset($cta['value']['app_destination'])) {
                $cta['value']['link'] = $cta['value']['link'] ?? $link;
            }

            return array_filter([
                'page_id'           => $conn->page_id,
                'instagram_user_id' => $conn->instagram_id,
                'video_data'        => array_filter($media + [
                    'message'              => $input['message'] ?? '',
                    'title'                => $input['headline'] ?? null,
                    'call_to_action'       => $cta,
                    'page_welcome_message' => $template['welcome'] ?? null,
                ]),
            ]);
        }

        return array_filter([
            'page_id'           => $conn->page_id,
            'instagram_user_id' => $conn->instagram_id,
            'link_data'         => array_filter($media + [
                'link'                 => $link,
                'message'              => $input['message'] ?? '',
                'name'                 => $input['headline'] ?? null,
                'call_to_action'       => $cta,
                'page_welcome_message' => $template['welcome'] ?? null,
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
            'message' => !empty($template['feed']) ? 'Mensagem em vários destinos (' . collect($template['feed']['call_to_actions'] ?? [])->map(fn ($c) => match ($c['value']['app_destination'] ?? '') { 'WHATSAPP' => 'WhatsApp', 'INSTAGRAM_DIRECT' => 'Direct', 'MESSENGER' => 'Messenger', default => $c['type'] ?? '?' })->implode(' + ') . ')' : 'Mensagem (WhatsApp/Direct)',
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
