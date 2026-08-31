# Webhooks de Onboarding (App → n8n) e callback (n8n → App)

Esteira de cliente novo, sem ClickUp. O App é fonte da verdade (Client, Contract,
Ticket, checklist de onboarding); o n8n só executa o que o App não faz sozinho:
mensagem de WhatsApp pro cliente, grupo de WhatsApp, pasta no Drive, contrato no
Google Docs.

**Nenhuma mensagem em grupo interno.** Aviso interno = notificação no sino do App.

---

## Transporte

O App faz **um POST** no `webhook_url` da integração n8n da organização
(`Configurações > Integrações`, provider `n8n`, status `connected`) — o **mesmo**
webhook_url que o `NotificationDispatchService` já usa. O n8n ramifica pelo campo
`event`.

- Sem integração n8n conectada → o App não dispara nada, sem erro (log `info`).
- Timeout de 15s, sem retry (o disparo é "fire and forget").

Todo payload tem:
```json
{ "event": "<nome>", "fired_at": "2026-09-01T13:00:00.000000Z", ...resto }
```

---

## Evento `opportunity_won`

**Quando:** oportunidade do tipo **`novo_cliente`** fechada como ganha
(`OpportunityController::win` → `ClientOnboardingService::start`).
`projeto` / `follow_up` **não** disparam nada externo.

```json
{
  "event": "opportunity_won",
  "fired_at": "...",
  "opportunity": {
    "id": "uuid",
    "title": "Zarpellon (Floresta)",
    "type": "novo_cliente",
    "proposed_fee": "1950.00",
    "proposed_ad_budget": "1000.00",
    "contract_months": 4,
    "services": ["Tráfego Pago", "Consultoria"],
    "notes": "Resumo da negociação digitado na oportunidade..."
  },
  "client": {
    "id": "uuid",
    "company_name": "Zarpellon Supermercado LTDA",
    "registration_url": "https://app.nonna.../cadastro/<token>",
    "app_url": "https://app.nonna.../clientes/<id>"
  },
  "contact": {
    "id": "uuid",
    "name": "Anderson Vargas",
    "whatsapp": "49999199616",
    "email": "dandevargas@hotmail.com"
  }
}
```

**n8n faz:** manda 1 WhatsApp (uazapi) pro `contact.whatsapp` com as boas-vindas +
`client.registration_url`. Só isso.

O App já marcou `whatsapp_boas_vindas` e `link_cadastro_enviado` no checklist
(otimista) assim que disparou.

---

## Evento `client_registration_completed`

**Quando:** cliente enviou o formulário público de cadastro
(`PublicRegistrationController::submit` → `ClientObserver` →
`ClientOnboardingService::onRegistrationComplete`).

Antes de disparar, o App já: criou o `Contract` (status `rascunho`), abriu o
Ticket "Contrato em análise — {cliente}" pro papel **Administrativo e Financeiro**,
notificou o time no sino, e avançou o onboarding pra Fase 2.

```json
{
  "event": "client_registration_completed",
  "fired_at": "...",
  "client": {
    "id": "uuid",
    "company_name": "Zarpellon Supermercado LTDA",
    "tax_id": "12.345.678/0001-90",
    "segment": "Varejo",
    "address": "Av. X, 80 - Centro - Chapecó/SC",
    "zip_code": "89801-000",
    "contact_email": "...", "contact_phone": "...",
    "responsible_name": "Anderson Vargas",
    "responsible_cpf": "...", "responsible_rg": "...",
    "responsible_birthdate": "1985-03-10",
    "responsible_address": "...",
    "responsible_marital_status": "casado",
    "payment_method": "pix|cartao|boleto",
    "billing_day": 10,
    "billing_email": "...", "billing_whatsapp": "...", "billing_notes": "...",
    "app_url": "https://app.nonna.../clientes/<id>"
  },
  "contact": { "id": "uuid", "name": "...", "whatsapp": "...", "email": "..." },
  "contract": {
    "id": "uuid",
    "title": "Contrato — Zarpellon Supermercado LTDA",
    "fee_value": "1950.00",
    "fee_type": "mensal",
    "start_date": "2026-09-01",
    "end_date": "2026-12-01",
    "months": 4,
    "payment_method": "pix",
    "billing_day": 10,
    "app_url": "https://app.nonna.../clientes/<id>/contratos/<id>",
    "doc_callback_url": "https://app.nonna.../api/onboarding/<client_id>/step"
  },
  "services": ["Tráfego Pago", "Consultoria"],
  "negotiation_summary": "Resumo da negociação...",
  "ticket": {
    "id": "uuid",
    "title": "Contrato em análise — Zarpellon",
    "app_url": "https://app.nonna.../tarefas/<id>"
  }
}
```

**n8n faz:**
1. **Grupo de WhatsApp** (uazapi): participantes = número do Alisson (constante no
   workflow) + `contact.whatsapp`. Nome sugerido `[NONNA] {2 primeiras palavras
   do company_name}`.
2. **Pasta no Google Drive** + compartilha (mesmo fluxo antigo).
3. **Contrato no Google Docs**: copia o template, preenche os `%%campos%%` a partir
   deste payload (`client.*`, `contract.*`, `services`), e faz **POST no
   `contract.doc_callback_url`** (ver callback abaixo) com o link do doc.
4. **WhatsApp pro cliente** (`contact.whatsapp`): "cadastro recebido, contrato em
   análise...".

Mapa dos `%%placeholders%%` do template → payload:

| Placeholder | Origem |
|---|---|
| `%%nomeFantasia%%` | `client.company_name` |
| `%%documentoEmpresa%%` | `client.tax_id` |
| `%%enderecoCompleto%%` | `client.address` |
| `%%cep%%` | `client.zip_code` |
| `%%representanteLegal%%` | `client.responsible_name` |
| `%%documentoResponsavel%%` | `client.responsible_cpf` |
| `%%enderecoRepresentante%%` | `client.responsible_address` |
| `%%ID CLIENTE%%` | `client.id` |
| `%%servicos%%` | `services` (join ", ") |
| `%%diaPagamento%%` | `contract.billing_day` |
| `%%metodoPagamento%%` | `contract.payment_method` |
| `%%tempoContrato%%` | `contract.months` |
| `%%dataInicial%%` | `contract.start_date` (formatar dd/MM/yyyy) |
| `%%dataFinal%%` | `contract.end_date` |
| `%%valorMensal%%` | `contract.fee_value` (trocar `.` por `,`) |

---

## Callback n8n → App: `POST /api/onboarding/{client}/step`

Autenticado por **Bearer token Sanctum** da Organização — no n8n é a credencial
Header Auth **"App Nonna"** (`Authorization: Bearer <token>`), a mesma já usada
pelos fluxos de Leads/sync. Se não existir, gerar em `Configurações > API`.

```
POST {contract.doc_callback_url}
Authorization: Bearer <token>
Content-Type: application/json

{ "step": "contrato_gerado", "contract_url": "https://docs.google.com/document/d/..." }
```

- `step`: chave de checklist (ver abaixo). Marca o item como feito.
- `contract_url` (opcional): quando presente, grava em `contracts.document_url` do
  contrato rascunho mais recente do cliente.

Resposta: `200 {"ok":true,"step":"..."}` ou `422` se a chave não existe / cliente
sem onboarding.

**Chaves úteis pro n8n confirmar:**
`grupo_whatsapp_criado`, `pasta_drive_criada`, `contrato_gerado`.

(O App já marca sozinho, na hora: `whatsapp_boas_vindas`, `link_cadastro_enviado`,
`cadastro_preenchido`, `ticket_contrato_aberto`.)

---

## Workflow n8n pronto pra importar

`.claude/docs/n8n/APP-ONBOARDING.json` — um único workflow: Webhook → Switch por
`event` → dois ramos. Precisa:
- re-selecionar credenciais Google (Drive, Docs) nos nós marcados `REVISAR`;
- re-selecionar a credencial Header Auth **App Nonna** nos 3 nós `Callback:`;
- preencher a `CONSTANTES`: `UAZAPI_TOKEN`, `ALISSON_WHATS`;
- conferir o endpoint uazapi de criar grupo (`/group/create` + formato de `participants`);
- apontar o `webhook_url` da integração n8n do App pra esse Webhook node (ou mover
  o Switch pra dentro do workflow que já recebe o `webhook_url` atual).
