# Webhook de Notificações Padrão (`NotificationDispatchService`)

## Objetivo

Disparo genérico de mensagens padrão (WhatsApp/e-mail) pro n8n, com o texto já resolvido a partir de `notification_templates` — o n8n só recebe o payload pronto e decide como enviar (WhatsApp/e-mail, qual provedor).

**Status atual (2026-07):** o lado do App está pronto e já dispara de verdade para **um gatilho**: `chamado_aberto` (quando um cliente abre um chamado pelo Portal, `Portal\TicketController::store()`). Os outros 8 tipos já têm template cadastrado (`Configurações > Mensagens Padrão`) e já podem ter contatos assinados (`client_contact_subscriptions`), mas **ninguém no App ainda chama `NotificationDispatchService::send()` pra eles** — ligar cada um é um passo separado, deliberadamente não feito ainda. `financeiro`/`cobranca`/`cs_survey`/`offboarding` nem têm de onde disparar (não existe tabela de nota/boleto, ciclo de CS ou fluxo de offboarding implementado).

**O workflow do n8n que recebe esse payload e efetivamente manda a mensagem também não existe ainda** — até lá, o webhook simplesmente não é chamado (`N8N_NOTIFICATION_WEBHOOK_URL` não configurado → `send()` retorna sem fazer nada, silenciosamente).

## Quando dispara

`NotificationDispatchService::send(string $type, Client $client, array $variables = [])`:

1. Busca todos os `client_contacts` do cliente com assinatura (`client_contact_subscriptions`) pro `$type` informado.
2. Pra cada contato assinado, pra cada canal marcado na assinatura (`whatsapp`/`email`), busca o `NotificationTemplate` da organização pra aquele tipo+canal.
3. Troca as variáveis (`{{chave}}` → valor) no `subject`/`body` do template — `cliente` e `contato` são preenchidos automaticamente, o resto vem do array `$variables` passado por quem chama.
4. Um `POST` por (contato, canal) — fire-and-forget, sem fila (mesmo padrão síncrono do `TaskApprovalService::dispatchWebhook()`).

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
    "body": "Oi Nome do Contato! Recebemos seu chamado \"Ajuste no banner\" e nosso time já está por dentro..."
  },
  "fired_at": "2026-07-21T18:00:00-03:00"
}
```

`message.subject` só vem preenchido quando `channel = "email"` (WhatsApp não tem assunto). `message.body`/`subject` já chegam com as variáveis substituídas — o n8n não precisa saber nada sobre `{{cliente}}`/`{{contato}}`/etc.

## Tipos (`event`) já com template cadastrado

`onboarding_boas_vindas`, `chamado_aberto` (único disparando de verdade), `chamado_concluido`, `reuniao_lembrete`, `aprovacao` (continua no fluxo separado de `approval-webhook-api.md`, não usa esse serviço), `financeiro`, `cobranca`, `cs_survey`, `offboarding`.

## O que falta para o fluxo ficar 100% funcional

1. Cadastrar `N8N_NOTIFICATION_WEBHOOK_URL` no ambiente de produção (Portainer) — a URL do webhook node do n8n que vai receber esse payload.
2. Montar o workflow no n8n que recebe o payload e decide como enviar (WhatsApp/e-mail, qual provedor).
3. Ligar os outros gatilhos que já têm dado de origem pronto (`chamado_concluido` quando a tarefa vira `concluido`; `reuniao_lembrete` via um comando agendado novo — nenhum dos dois está ligado ainda).
4. Construir a funcionalidade de base que falta pros gatilhos que ainda não têm nada por trás (`financeiro`/`cobranca`/`cs_survey`/`offboarding`).
