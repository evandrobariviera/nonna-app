# Webhook de Aprovação de Entregáveis (`approval_requested`)

## Objetivo

Quando uma tarefa é enviada para aprovação do cliente, o App dispara um webhook HTTP (`POST`) para o n8n, que é responsável por notificar os contatos do cliente (WhatsApp e/ou e-mail) com o link tokenizado de aprovação.

**Status atual (2026-07):** o lado do App está pronto — o disparo e o payload abaixo já funcionam em produção. **O workflow do n8n que efetivamente envia a mensagem ainda não existe** — depende da definição do provedor de WhatsApp/e-mail que a agência vai usar. Até lá, o webhook simplesmente não é chamado (`N8N_APPROVAL_WEBHOOK_URL` não configurado → `dispatchWebhook()` retorna sem fazer nada, silenciosamente).

## Quando o webhook é disparado

`TaskApprovalService::submitForApproval()` cria uma `TaskApprovalRound` e, para cada contato do cliente marcado com `receives_approvals = true`, gera um `TaskApprovalToken` e chama `dispatchWebhook()` — ou seja, **um POST por contato** (não um por rodada).

Esse método é acionado por três caminhos:

1. **Automático** (`TaskApprovalService::maybeAutoSubmitOnApprovalTransition()`, chamado pelo `TaskObserver` em todo `$task->update()` individual): dispara sozinho sempre que `status = 'aprovacao'` **e** `situation = 'Enviar para o cliente'` ficam verdadeiros ao mesmo tempo — não importa se a mudança veio do board de Sprint, de Filas, de drag-and-drop ou de um formulário. Só dispara se não houver rodada `pending` já aberta pra tarefa, e só se houver anexos não marcados como entregáveis ainda.
2. **Manual** (`TaskApprovalController::store()`): botão "Enviar para aprovação" na tela da tarefa, com seleção manual de anexos.
3. **Atualização em massa (`bulkUpdate`) NÃO dispara** — decisão consciente, não uma limitação técnica: `Builder::update()` pula os eventos do Eloquent (e o `TaskObserver` com ele), e a regra de negócio é que aprovação sempre vai uma tarefa por vez pro cliente, nunca em lote.

## Endpoint

- **Método:** `POST`
- **URL:** valor de `config('services.n8n.approval_webhook_url')` (env `N8N_APPROVAL_WEBHOOK_URL`)
- **Timeout:** 5s (`Http::timeout(5)`)

## Payload

```json
{
  "event": "approval_requested",
  "round": {
    "id": "uuid",
    "number": 1,
    "task_title": "Criativos Feed — Campanha Julho",
    "notes": "texto opcional informado no envio, pode ser null"
  },
  "client": {
    "id": "uuid",
    "name": "Nome Fantasia do Cliente"
  },
  "contact": {
    "name": "Nome do Contato",
    "email": "contato@cliente.com",
    "phone": "+55 51 99999-9999"
  },
  "link": "https://app.nonna.../aprovacao/{token}",
  "expires_at": "2026-07-17T00:00:00-03:00",
  "deliverables_count": 3
}
```

Após o disparo, o App marca `task_approval_tokens.notified_at = now()` — não há confirmação de entrega vinda do n8n de volta pro App nesse fluxo (é fire-and-forget).

## O que falta para o fluxo ficar 100% funcional

1. Definir o provedor de WhatsApp (ex: API oficial da Meta, Twilio, Z-API etc.) e de e-mail.
2. Montar o workflow no n8n que recebe esse payload e dispara as mensagens usando o `link` tokenizado.
3. Cadastrar `N8N_APPROVAL_WEBHOOK_URL` no ambiente de produção (Portainer).

## Fluxo de resposta do cliente (não depende do n8n)

O cliente responde direto no App, pelo link tokenizado (`route('approval.show', $token)`, público, sem login) — aprovação/pedido de ajuste por entregável é gravado em `deliverable_feedbacks`, e quando todos os contatos responderem (unanimidade), a rodada se resolve sozinha via `TaskApprovalService::tryResolveRound()`:
- Se qualquer contato pediu ajuste → rodada `changes_requested`, tarefa volta para `status = 'ajuste_alteracao'`.
- Se todos aprovaram → rodada `approved`, tarefa avança para `status = 'despacho_agendamento'`.

Isso é 100% App-to-App (o cliente acessa um link do próprio App) — não precisa de nenhum webhook do n8n nesse sentido.
