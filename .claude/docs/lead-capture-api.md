# API de Captura de Leads (`POST /api/leads/captura`)

## Objetivo

Endpoint único que o n8n chama pra registrar um lead capturado em qualquer canal (Site, Facebook/Instagram Lead Ads, WhatsApp, etc) — o n8n normaliza o payload de origem (GTM/UTM do site, webhook de Lead Ads do Meta, mensagem de WhatsApp...) pro mesmo formato, e o App decide sozinho a qual Cliente aquele lead pertence.

## Endpoint

- **Método:** `POST /api/leads/captura`
- **Auth:** `auth:sanctum` + `SetApiTenant` (`routes/api.php`) — Bearer token por Organização (mesmo token já usado em `/api/ad-accounts` e `/api/sync/*`, não precisa gerar um novo por canal).
- **Controller:** `App\Http\Controllers\Api\LeadCaptureController::store()` → delega pra `App\Services\Leads\LeadCaptureService::capture()`.

## Payload

```json
{
  "source_channel": "facebook_lead_ad",
  "source_identifier": "869511879732680:120211112223334",
  "name": "João Silva",
  "email": "joao@exemplo.com",
  "phone": "+5511999999999",
  "form_name": "Formulário {Cliente}",
  "landing_page_url": "https://cliente.com.br/lp",
  "utm_source": "facebook",
  "utm_medium": "lead_ad",
  "utm_campaign": "...",
  "utm_content": "...",
  "utm_term": null,
  "fbclid": null,
  "gclid": null,
  "ctwa_clid": null,
  "event_id": "120211112223334",
  "city": "Canoinhas",
  "state": "SC",
  "received_at": "2026-09-15T12:00:00-03:00",
  "raw_payload": { "...": "payload cru original, pra auditoria" }
}
```

Validação (`LeadCaptureController::store()`):
- `source_channel` (obrigatório, string, **não é enum fechado na validação** — ver seção "Canais" abaixo pra saber os valores que o App realmente reconhece).
- `source_identifier` (obrigatório, string) — formato varia por canal, ver tabela abaixo.
- `email` OU `phone` — pelo menos um dos dois é obrigatório (`required_without` cruzado).
- `name`, `form_name`, `landing_page_url`, `utm_*`, `city`, `state`, `received_at`, `raw_payload` — todos opcionais.
- `fbclid`, `gclid`, `ctwa_clid`, `event_id` — opcionais, existem desde o desenho do schema pensando em dedup com Meta CAPI/Google Ads.

## Canais (`source_channel`) e formato de `source_identifier`

`source_channel` precisa bater com `LeadChannel.kind` (`app/Models/LeadChannel.php`), já seedado por organização (`database/migrations/2026_08_19_100006_seed_default_lead_channels.php`):

| `source_channel` (kind) | Canal | Formato de `source_identifier` (= `client_lead_sources.external_id`) |
|---|---|---|
| `site` | Site | UUID do Cliente no App (padrão pré-preenchido ao cadastrar a fonte) |
| `facebook_lead_ad` | Facebook/Instagram Lead Ads | `{page_id}:{form_id}` (concatenado com dois-pontos — **não** é só o Form ID) |
| `whatsapp` | WhatsApp | número de WhatsApp |
| `outros` | Outros | livre |

Cadastro da fonte por Cliente: ficha do cliente → aba "Fontes de Lead" (`resources/views/clients/show.blade.php`, `ClientLeadSourceController`) — o placeholder do campo `external_id` já documenta o formato esperado por canal. Unique constraint real é `(lead_channel_id, external_id)`.

## Resolução do Cliente e resposta

Em `LeadCaptureService::capture()`:
1. Busca o `LeadChannel` da organização pelo `kind = source_channel`.
2. Busca `ClientLeadSource` ativa (`is_active = true`) com `(lead_channel_id, external_id = source_identifier)`, do mesmo Cliente/Organização.
3. Se achar → grava o lead **com** `client_id` resolvido, dedup por `(client_id, phone OU email)` dentro da janela de reabertura de 72h (`ClientLeadOpportunity::REOPEN_WINDOW_HOURS`).
4. Se não achar (canal não existe na org, `external_id` sem match, ou fonte inativa) → grava o lead **sem** `client_id`/`client_lead_source_id` (cai em triagem manual na Central de Leads) — **o lead nunca é descartado**.

Resposta:
- **201** — Cliente identificado (`client_id` resolvido).
- **202** — sem match, lead em triagem manual.
- Corpo (ambos os casos): `{ client_lead_id, client_id, opportunity_id, opportunity_is_new, stage }`.

## Onde os dados ficam

- `ClientLead` — pessoa (nome/email/phone/cidade/estado), `client_id` nullable, `first_seen_channel_id` (FK `lead_channels`).
- `ClientLeadOpportunity` — o card da Central de Leads (Kanban): `raw_payload` (jsonb), `utm_*`, `fbclid`/`gclid`/`ctwa_clid`/`event_id`, `form_name`, `landing_page_url`, `received_at`, `lead_channel_id`, `client_lead_source_id` (nullable quando 202).

## Workflows n8n de referência

- `.claude/docs/n8n/Insert Lead (Site) - Central de Leads v2.json` — captura via script de tracking do site (webhook único, `source_identifier` = UUID do cliente mandado pelo próprio script).
- `.claude/docs/n8n/Insert Lead (Facebook Lead Ads) - Central de Leads v1.json` — captura via Lead Ads do Meta. Limitação da própria API do Meta/n8n: **precisa de um node "Facebook Lead Ads Trigger" por Página+Formulário** — o padrão usado é um node "Identificar: `<cliente>`" pequeno logo após cada trigger (carimba `page_id`/`form_id`/`client_label`), todos convergindo pro mesmo par compartilhado "Montar payload" → "Captura Lead (App)", sem duplicar a lógica de transformação/envio por formulário.
