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
  "form_answers": [
    { "label": "Assunto", "value": "Orçamento de reserva" },
    { "label": "Mensagem", "value": "Quero reservar a cabana pro feriado..." },
    { "label": "Qual seu interesse?", "value": "Cabana" }
  ],
  "raw_payload": { "...": "payload cru original, pra auditoria" }
}
```

Validação (`LeadCaptureController::store()`):
- `source_channel` (obrigatório, string, **não é enum fechado na validação** — ver seção "Canais" abaixo pra saber os valores que o App realmente reconhece).
- `source_identifier` (obrigatório, string) — formato varia por canal, ver tabela abaixo.
- `email` OU `phone` — pelo menos um dos dois é obrigatório (`required_without` cruzado).
- `name`, `form_name`, `landing_page_url`, `utm_*`, `city`, `state`, `received_at`, `raw_payload` — todos opcionais.
- `fbclid`, `gclid`, `ctwa_clid`, `event_id` — opcionais, existem desde o desenho do schema pensando em dedup com Meta CAPI/Google Ads.
- `ad_id`, `campaign_id` — opcionais (desde 26/10/2026): ID do anúncio/campanha na plataforma. **No Lead Ads o n8n deve repassar o `ad_id`/`campaign_id` que vêm no aviso `leadgen` da Meta.** Sem eles, o App tenta pela UTM no padrão Nonna (`utm_content` = ID do anúncio, `utm_campaign` = ID da campanha — só vale se for número; nome de campanha não liga). Resolvido por `App\Services\Leads\AdAttribution` contra os anúncios já sincronizados das contas do próprio cliente → grava `ad_external_id`/`campaign_external_id` (sempre) e `ad_ad_id`/`ad_campaign_id` (quando o anúncio/campanha já foi sincronizado).
- Telefone é comparado pela chave DDD + últimos 8 dígitos (`App\Support\PhoneKey`, coluna `client_leads.phone_key`) — `+55 49 9908-2184` e `5549999082184` são a mesma pessoa.
- **Sem cartão duplicado (desde 27/10/2026):** se a pessoa (telefone ou e-mail) já tem cartão em andamento, a nova entrada volta pra ele, não importa quanto tempo passou, e ganha a nota "Entrou de novo pelo {canal}". Cartão novo só quando o último já está Ganho/Perdido. Cartões repetidos antigos: `php artisan leads:merge-duplicate-cards --dry`.

### Padrão de UTM (o número do anúncio no link)

- **Meta** (campo *Parâmetros de URL* de cada anúncio): `utm_source={{site_source_name}}&utm_medium=paid&utm_campaign={{campaign.id}}&utm_term={{adset.id}}&utm_content={{ad.id}}`
- **Google Ads** (*Sufixo do URL final* da conta, marcação automática ligada): `utm_source=google&utm_medium=paid&utm_campaign={campaignid}&utm_term={adgroupid}&utm_content={creative}`
- O GTM do site guarda UTM + `fbclid`/`gclid` da primeira visita e manda junto com o formulário.

### WhatsApp do Atendimento Assistido (sem n8n)

Conversas que chegam pelo webhook uazapi (`UazapiMessageIngestor`) entram na Central sozinhas, via `App\Services\Leads\WhatsappLeadLinker` → `LeadCaptureService::captureForClient()` (canal `whatsapp`, Cliente = dono do número, sem aviso de "Lead Capturado"). **Cartão só para quem veio de anúncio** (clique em anúncio de WhatsApp — `externalAdReply.sourceID` vira `ad_id`, inclusive numa conversa antiga). Qualquer outra conversa em que a pessoa escreveu só liga a pessoa (`service_conversations.client_lead_id`), sem cartão — o WhatsApp do negócio tem aluno, fornecedor e curioso; cartão para conversa orgânica só quando a IA identificar interesse real (etapa de qualificação, ainda não feita). Conversas antigas: `php artisan leads:link-whatsapp --dry` (simula) e sem `--dry` (grava).
- `form_answers` — opcional: **todo o conteúdo do formulário** (assunto, mensagem, perguntas customizadas do Lead Ad). Aceita lista `[{label, value}]` (preferido, mantém a ordem e o texto original da pergunta) ou objeto `{pergunta: resposta}`. Se não vier, o App tenta extrair do `raw_payload` (`data` do trigger do Facebook, `body` de webhook, ou achatando o objeto e ignorando chaves técnicas/UTM/identidade — `App\Services\Leads\FormAnswers`). Campos com rótulo tipo assunto/mensagem/observação/dúvida aparecem em destaque na ficha do lead e como trecho no cartão do Kanban.
- Se a mesma pessoa enviar de novo com cartão em andamento (cartão reaberto), o conteúdo anterior vai pra `previous_submissions` em vez de ser sobrescrito.

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
3. Se achar → grava o lead **com** `client_id` resolvido, dedup da pessoa por `(client_id, phone_key OU email)`; cartão reaproveitado se houver um em andamento.
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
