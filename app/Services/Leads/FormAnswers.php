<?php

namespace App\Services\Leads;

use Illuminate\Support\Str;

// Normaliza o conteúdo de um formulário (site, Lead Ad do Meta, etc.) numa lista
// [{label, value}] pra exibição. Também sabe extrair isso do raw_payload de leads
// que chegaram antes do campo form_answers existir no contrato.
class FormAnswers
{
    private const MESSAGE_PATTERN = '/assunto|subject|mensagem|message|msg|observa|comentario|comment|duvida|descri|detalhe|conte/';

    // Chaves técnicas, de atribuição (já exibidas em Atribuição) ou de identidade
    // (já exibidas no card de Contato) — nunca entram como "resposta".
    private const SKIP_PATTERN = '/^(id|_id|client|client_id|cliente|business|source_channel|source_identifier|event_id|received_at|created_time|created_at|timestamp|headers|params|query|cookies?|webhook_?url|execution_?mode|page|form|form_id|form_name|ad|ad_id|adset|adset_id|campaign|campaign_id|platform|is_organic|landing_page_url|url|page_url|referrer|referer|user_agent|ip|ip_address|token|secret|password|senha|nome|nome_completo|name|full_name|first_name|last_name|e_?mail|telefone|celular|whatsapp|phone|phone_number|fone|cidade|city|estado|state|uf)$|^(utm_|gtm|_ga|_fb|fbclid|gclid|ctwa|wbraid|gbraid|msclkid|x_)/';

    private const SKIP_CONTAINER_PATTERN = '/^(headers|params|query|cookies?|utms?|tracking|attribution|client|cliente|business|page|ad|adset|campaign|device|geo|session)$/';

    // Chaves técnicas que o plugin de site/widget manda junto do formulário — não dizem nada
    // sobre o lead (versão do plugin, cookie do Pixel, nome do evento...).
    private const TECHNICAL_PATTERN = '/^(site|plugin|widget|fbp|fbc|event_name|country_code|consent_text)$/';

    // Chaves em inglês que os formulários mandam (plugin nonna-leads, Elementor, Lead Ads)
    // → rótulo em português. O plugin costuma mandar o mesmo campo duas vezes (chave em
    // inglês + rótulo em português, mesmo valor); nesse caso a versão em inglês some.
    private const LABELS_PT = [
        'page_title'       => 'Página',
        'consent'          => 'Consentimento',
        'terms'            => 'Aceitou os termos',
        'privacy'          => 'Privacidade',
        'subject'          => 'Assunto',
        'message'          => 'Mensagem',
        'comment'          => 'Comentário',
        'comments'         => 'Comentários',
        'company'          => 'Empresa',
        'company_name'     => 'Razão social',
        'trade_name'       => 'Nome fantasia',
        'address'          => 'Endereço',
        'zip'              => 'CEP',
        'zip_code'         => 'CEP',
        'due_day'          => 'Dia de vencimento',
        'contract_accept'  => 'Aceite do contrato',
        'contract_version' => 'Versão do contrato',
        'curriculum'       => 'Currículo',
        'resume'           => 'Currículo',
        'mark'             => 'Marcas que já representa',
        'brands'           => 'Marcas',
        'region'           => 'Região',
        'arrived'          => 'Como chegou até nós',
        'contact'          => 'Melhor forma de contato',
        'network'          => 'Rede',
        'pharmacy'         => 'Farmácia',
        'role'             => 'Cargo',
        'job_title'        => 'Cargo',
        'stores'           => 'Lojas',
        'ads_today'        => 'Anuncia hoje',
        'ads_budget'       => 'Investimento em anúncios',
        'budget'           => 'Orçamento',
        'capital'          => 'Capital disponível',
        'interest'         => 'Interesse',
        'product'          => 'Produto',
        'service'          => 'Serviço',
        'country'          => 'País',
        'investment'       => 'Investimento',
        'resale'           => 'Revenda',
        'enterprise'       => 'Empresa',
        'inbox_url'        => 'Conversa no Facebook',
    ];

    // Lista [{label, value}] pronta pra tela: sem campos técnicos, rótulos em português,
    // sem a cópia em inglês do mesmo campo. Pode rodar de novo no resultado (idempotente).
    public static function present(array $answers): array
    {
        $answers = array_values(array_filter(
            $answers,
            fn ($a) => isset($a['label'], $a['value']) && !preg_match(self::TECHNICAL_PATTERN, self::key($a['label']))
        ));

        $nativeValues = [];
        foreach ($answers as $a) {
            if (!isset(self::LABELS_PT[self::key($a['label'])])) {
                $nativeValues[(string) $a['value']] = true;
            }
        }

        $out = [];
        $seen = [];
        foreach ($answers as $i => $a) {
            $key = self::key($a['label']);

            // Mesmo valor no campo seguinte = a cópia em português que o plugin manda logo
            // depois da chave em inglês (fica a segunda). Sim/Não ficam: duas perguntas
            // seguidas podem ter a mesma resposta curta.
            $next = $answers[$i + 1]['value'] ?? null;
            if ($next !== null && (string) $next === (string) $a['value'] && mb_strlen((string) $a['value']) > 3) {
                continue;
            }
            $value = html_entity_decode((string) $a['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if (isset(self::LABELS_PT[$key])) {
                if (isset($nativeValues[(string) $a['value']])) {
                    continue;
                }
                $label = self::LABELS_PT[$key];
            } elseif (preg_match('/^field_[a-z0-9]+$/', $key)) {
                $label = 'Campo sem nome';
            } else {
                $label = Str::ucfirst(trim(str_replace('_', ' ', $a['label'])));
            }

            $value = match (Str::lower($value)) {
                'on', 'yes', 'true' => 'Sim',
                'off', 'no', 'false' => 'Não',
                default => $value,
            };

            if (isset($seen[$label."\0".$value])) {
                continue;
            }
            $seen[$label."\0".$value] = true;
            $out[] = ['label' => $label, 'value' => $value];
        }

        return $out;
    }

    public static function normalize(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $out = [];
        foreach ($input as $key => $item) {
            if (is_array($item) && (isset($item['label']) || isset($item['question']))) {
                $label = (string) ($item['label'] ?? $item['question']);
                $value = self::stringify($item['value'] ?? $item['answer'] ?? null);
            } elseif (is_string($key)) {
                $label = $key;
                $value = self::stringify($item);
            } else {
                continue;
            }

            if ($value !== null && trim($label) !== '') {
                $out[] = ['label' => trim($label), 'value' => $value];
            }
        }

        return $out;
    }

    public static function fromRawPayload(?array $raw): array
    {
        if (!$raw) {
            return [];
        }

        // Facebook Lead Ads Trigger do n8n: {id, data: {pergunta: resposta}, form, page, ad, adset}
        if (isset($raw['data']) && is_array($raw['data']) && (isset($raw['form']) || isset($raw['page']))) {
            return self::withoutIdentity(self::normalize($raw['data']));
        }

        // Item cru de Webhook do n8n: {headers, params, query, body}
        if (isset($raw['body']) && is_array($raw['body'])) {
            $raw = $raw['body'];
        }

        $out = [];
        self::flatten($raw, $out, 0);

        return $out;
    }

    public static function isMessage(string $label): bool
    {
        return (bool) preg_match(self::MESSAGE_PATTERN, self::key($label));
    }

    public static function withoutIdentity(array $answers): array
    {
        return array_values(array_filter($answers, fn ($a) => !preg_match(self::SKIP_PATTERN, self::key($a['label']))));
    }

    private static function flatten(array $data, array &$out, int $depth): void
    {
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value) && !array_is_list($value)) {
                if ($depth < 3 && !preg_match(self::SKIP_CONTAINER_PATTERN, self::key($key))) {
                    self::flatten($value, $out, $depth + 1);
                }
                continue;
            }

            if (preg_match(self::SKIP_PATTERN, self::key($key))) {
                continue;
            }

            $str = self::stringify($value);
            if ($str !== null) {
                $out[] = ['label' => Str::ucfirst(str_replace(['_', '-'], ' ', $key)), 'value' => $str];
            }
        }
    }

    private static function stringify(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        if (is_array($value)) {
            $scalars = array_filter($value, fn ($v) => is_scalar($v) && trim((string) $v) !== '');
            return $scalars ? implode(', ', $scalars) : null;
        }
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return null;
    }

    private static function key(string $label): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($label))), '_');
    }
}
