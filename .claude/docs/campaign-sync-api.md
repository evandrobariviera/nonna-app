# API de Sincronização de Campanhas (n8n → App)

## Objetivo

O App **não integra diretamente** com Meta Ads / Google Ads (regra de ouro do projeto — ver `CLAUDE.md`). Quem consulta as APIs de anúncios e envia os dados para cá é o **n8n**. Este documento é o contrato que o workflow do n8n precisa seguir.

Fluxo esperado (diário, por conta de anúncio):

```
n8n (Schedule Trigger diário)
  → GET /api/ad-accounts                    (descobre quais contas sincronizar)
  → para cada conta: chama Meta Graph API / Google Ads API
  → POST /api/sync/campaigns                (estrutura: campanhas → adsets → ads)
  → POST /api/sync/snapshots                (métricas do dia anterior, por campanha/adset/ad)
```

## Pré-requisito: cadastro das contas de anúncio

As contas de anúncio **não são descobertas automaticamente** — o time interno cadastra manualmente cada conta real (Meta Business Manager / Google Ads) na aba "Contas de Anúncios" do cliente, dentro do App. O n8n só lê o que já foi cadastrado ali via `GET /api/ad-accounts`.

## Autenticação

Todos os endpoints (exceto os de importação do ClickUp) usam **Bearer token via Laravel Sanctum, vinculado à Organização** — não há conceito de subdomínio, o tenant é resolvido pelo próprio token.

- Gerar o token: Configurações → aba "API" → gerar novo token (rota `settings.create-token`, já implementada). O token só é exibido uma vez.
- Enviar em toda requisição: `Authorization: Bearer {token}`.
- Erro de autenticação: `401 Unauthorized`.

## `GET /api/ad-accounts`

Retorna as contas de anúncio ativas (`status = 'ativo'`) da organização do token.

**Resposta 200:**
```json
{
  "data": [
    {
      "id": "uuid-da-conta-no-app",
      "client_id": "uuid-do-cliente",
      "client_name": "Nome do Cliente",
      "platform": "meta_ads",
      "account_id": "123456789012345",
      "account_name": "Conta Principal",
      "status": "ativo"
    }
  ]
}
```

**Atenção:** `platform` aqui vem no formato do cadastro (`meta_ads`, `google_ads`, `tiktok_ads`, `outros`) — é só informativo. Nos endpoints de sync abaixo, `platform` é enviado no formato **curto** (`meta` ou `google`), independente do valor acima.

## `POST /api/sync/campaigns`

Cria ou atualiza a estrutura de campanhas → adsets → ads de uma conta. Idempotente — pode ser chamado quantas vezes for preciso; a chave de identidade é `(client_ad_account_id, external_id)`.

**Payload:**
```json
{
  "client_ad_account_id": "uuid-da-conta-retornado-no-GET-anterior",
  "platform": "meta",
  "campaigns": [
    {
      "external_id": "120210000000001",
      "name": "Conversão - Remarketing",
      "status": "active",
      "objective": "conversions",
      "start_date": "2026-06-01",
      "end_date": null,
      "raw_data": { "...payload bruto da API, opcional, guardado como está" },
      "adsets": [
        {
          "external_id": "120210000000002",
          "name": "Adset - Interesses",
          "status": "active",
          "daily_budget": 150.00,
          "lifetime_budget": null,
          "targeting": { "...segmentação, opcional" },
          "raw_data": {},
          "ads": [
            {
              "external_id": "120210000000003",
              "name": "Anúncio - Vídeo 01",
              "status": "active",
              "creative_type": "video",
              "creative_url": "https://...",
              "raw_data": {}
            }
          ]
        }
      ]
    }
  ]
}
```

- `platform`: só `"meta"` ou `"google"` — **não** usar `meta_ads`/`google_ads` aqui (validação rejeita com 422).
- `adsets` e `ads` são opcionais — pode enviar só campanhas, se o nível de detalhe do adset/ad não estiver disponível ainda.
- `status` de campanha/adset/ad: string livre vinda da própria plataforma (ex: `active`, `paused`, `deleted`, `archived` no Meta).

**Resposta 200:**
```json
{ "message": "Campanhas sincronizadas.", "synced": { "campaigns": 1, "adsets": 1, "ads": 1 } }
```

## `POST /api/sync/snapshots`

Envia as métricas de um dia específico. Deve ser chamado **uma vez por dia por conta**, com os números do dia anterior (D-1) — as plataformas de anúncio costumam consolidar os dados do dia só algumas horas depois da meia-noite.

**Payload:**
```json
{
  "client_ad_account_id": "uuid-da-conta",
  "platform": "meta",
  "snapshot_date": "2026-07-03",
  "snapshots": [
    {
      "entity_level": "campaign",
      "entity_id": "120210000000001",
      "entity_name": "Conversão - Remarketing",
      "parent_entity_id": null,
      "spend": 245.50,
      "revenue": 890.00,
      "impressions": 18400,
      "clicks": 320,
      "conversions": 12,
      "reach": 9800,
      "raw_data": {}
    }
  ]
}
```

- `entity_level`: **obrigatoriamente** `"campaign"`, `"adset"` ou `"ad"` — nunca um rollup de conta inteira. Se quiser granularidade só de campanha, envie apenas os snapshots com `entity_level = "campaign"`.
- `entity_id`: precisa bater com o `external_id` já sincronizado em `/sync/campaigns` (é assim que o App casa métrica com campanha).
- `parent_entity_id`: preenchido quando `entity_level` é `adset` (id da campanha) ou `ad` (id do adset) — ajuda em relatórios futuros, mas não é obrigatório.
- Idempotente — chave `(client_ad_account_id, entity_level, entity_id, snapshot_date)`. Pode reenviar o mesmo dia para corrigir números.
- O App calcula automaticamente CPM/CPC/CTR/CPA/ROAS a partir de spend/revenue/impressions/clicks/conversions — não precisa calcular isso no n8n.

**Resposta 200:**
```json
{ "message": "Snapshots salvos.", "upserted": 1 }
```

## Erros de validação

Payload fora do formato esperado retorna `422` no formato padrão do Laravel:
```json
{ "message": "The campaigns.0.external_id field is required.", "errors": { "campaigns.0.external_id": ["..."] } }
```

## O que acontece depois de sincronizar

- O **Dashboard interno de Campanhas** (`/campanhas`) e o **Portal do Cliente** (`/portal/campanhas`) leem direto dessas tabelas — nenhuma ação extra necessária.
- O comando agendado `campaigns:generate-insights` (roda todo dia às 8h) analisa os snapshots sincronizados e gera alertas automáticos (orçamento estourado, CPA em alta, ROAS em queda) — ver `.claude/docs/architecture.md` ou o histórico de commits do recurso "Inteligência de Campanhas" para detalhes.
