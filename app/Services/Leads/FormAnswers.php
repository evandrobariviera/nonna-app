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
