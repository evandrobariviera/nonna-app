# Webhook de Notificações Padrão (`NotificationDispatchService`)

## Objetivo

Disparo genérico de mensagens padrão (WhatsApp/e-mail) pro n8n, com o texto já resolvido a partir de `notification_templates` — o n8n só recebe o payload pronto e decide como enviar (WhatsApp/e-mail, qual provedor).

**Status atual (2026-07-22):** o lado do App está pronto e já dispara de verdade pra **três gatilhos**: `chamado_aberto` (quando um cliente abre um chamado pelo Portal, `Portal\TicketController::store()`), `aprovacao` (unificado com o antigo webhook exclusivo de aprovação — ver [approval-webhook-api.md](approval-webhook-api.md) pra lógica de negócio específica: tokens, expiração, resolução de rodada) e `financeiro` (quando o time sobe um boleto/PIX de uma conta de anúncios, `ClientAdBillingDocumentController::store()` — ver `.claude/docs/*` do feature de Orçamentos). Os demais tipos já têm template cadastrado (`Configurações > Mensagens Padrão`) e já podem ter contatos assinados (`client_contact_subscriptions`), mas **ninguém no App ainda chama o serviço pra eles** — ligar cada um é um passo separado, deliberadamente não feito ainda.

**O workflow do n8n que recebe esse payload e efetivamente manda a mensagem também não existe ainda** — até lá, o webhook simplesmente não é chamado (`N8N_NOTIFICATION_WEBHOOK_URL` não configurado → `send()` retorna sem fazer nada, silenciosamente).

## Quando dispara

Duas formas de chamar, mesmo mecanismo por baixo (`dispatch()`):

- **`NotificationDispatchService::send(string $type, Client $client, array $variables = [], ?string $attachmentUrl = null)`** — fan-out automático: busca todos os `client_contacts` do cliente com assinatura (`client_contact_subscriptions`) pro `$type`, e dispara pra cada (contato, canal) assinado. Usado por `chamado_aberto` e `financeiro`.
- **`NotificationDispatchService::dispatch(string $type, string $channel, Client $client, Contact $contact, array $variables = [], ?string $attachmentUrl = null)`** — dispara pra um (contato, canal) já conhecido de antemão, sem reconsultar assinatura. Usado por `aprovacao` (`TaskApprovalService::dispatchWebhook()`), que já sabe exatamente quem notificar e por qual canal via `TaskApprovalToken` (snapshot feito na submissão, não na hora do envio).

`$attachmentUrl` é opcional (nullable) — quando informado, vira `message.attachment_url` no payload (ex: link temporário do R2 de um boleto em PDF). Quando não há arquivo (ex: só um código PIX copia-e-cola), fica `null` e o conteúdo entra direto no `body` via `$variables`.

Em ambos os casos: busca o `NotificationTemplate` da organização pra aquele tipo+canal, troca as variáveis (`{{chave}}` → valor) no `subject`/`body` — `cliente` e `contato` são preenchidos automaticamente, o resto vem do array `$variables` passado por quem chama — e faz um `POST` fire-and-forget, sem fila.

Se não tiver `N8N_NOTIFICATION_WEBHOOK_URL` configurado, ou ninguém assinado naquele tipo pro cliente, ou o template estiver vazio pro canal — não dispara nada, sem erro.

## Endpoint

- **Método:** `POST`
- **URL:** valor de `config('services.n8n.notification_webhook_url')` (env `N8N_NOTIFICATION_WEBHOOK_URL`)
- **Timeout:** 10s

## Payload

```json
{
  "event": "chamado_aberto",
  "channel": "whatsapp",
  "client": {
    "id": "uuid",
    "company_name": "Nome Fantasia do Cliente"
  },
  "contact": {
    "id": "uuid",
    "name": "Nome do Contato",
    "email": "contato@cliente.com",
    "whatsapp": "+55 51 99999-9999"
  },
  "message": {
    "subject": null,
    "body": "Oi Nome do Contato! Recebemos seu chamado \"Ajuste no banner\" e nosso time já está por dentro...",
    "attachment_url": null
  },
  "fired_at": "2026-07-21T18:00:00-03:00"
}
```

`message.subject` só vem preenchido quando `channel = "email"` (WhatsApp não tem assunto). `message.body`/`subject` já chegam com as variáveis substituídas — o n8n não precisa saber nada sobre `{{cliente}}`/`{{contato}}`/etc.

## Tipos (`event`) já com template cadastrado

`onboarding_boas_vindas`, `chamado_aberto` (disparando via `send()`), `chamado_concluido`, `reuniao_lembrete`, `aprovacao` (disparando via `dispatch()`, ver [approval-webhook-api.md](approval-webhook-api.md) pra lógica de negócio), `financeiro`, `cobranca`, `cs_survey`, `offboarding`.

## O que falta para o fluxo ficar 100% funcional

1. Cadastrar `N8N_NOTIFICATION_WEBHOOK_URL` no ambiente de produção (Portainer) — a URL do webhook node do n8n que vai receber esse payload (única variável agora, substituiu a antiga `N8N_APPROVAL_WEBHOOK_URL`).
2. Montar o workflow no n8n que recebe o payload e decide como enviar (WhatsApp/e-mail, qual provedor) — pode ramificar internamente pelo campo `event`.
3. Ligar os outros gatilhos que já têm dado de origem pronto (`chamado_concluido` quando a tarefa vira `concluido`; `reuniao_lembrete` via um comando agendado novo — nenhum dos dois está ligado ainda).
4. Construir a funcionalidade de base que falta pros gatilhos que ainda não têm nada por trás (`cobranca`/`cs_survey`/`offboarding`).
