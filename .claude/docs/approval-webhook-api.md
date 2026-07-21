# Aprovação de Entregáveis — disparo pro n8n

## Objetivo

Quando uma tarefa é enviada para aprovação do cliente, o App dispara um webhook HTTP (`POST`) para o n8n, que é responsável por notificar os contatos do cliente (WhatsApp e/ou e-mail) com o link tokenizado de aprovação.

**Atualizado (2026-07-21): unificado com o webhook genérico de notificações.** O disparo não usa mais uma URL/payload próprios — `TaskApprovalService::dispatchWebhook()` chama `NotificationDispatchService::dispatch('aprovacao', ...)`, o mesmo serviço usado pelos outros gatilhos (chamado aberto, lembrete de reunião, etc.). Isso significa: mesma URL (`N8N_NOTIFICATION_WEBHOOK_URL`), mesmo formato de payload, e o texto vem do template `aprovacao` cadastrado em Configurações > Mensagens Padrão (com `{{tarefa}}` e `{{link_aprovacao}}` já substituídos). **O formato completo do payload está em [notification-webhook-api.md](notification-webhook-api.md)** — este documento aqui só explica a lógica de negócio específica de aprovação (quando dispara, tokens, resolução da rodada), que continua igual.

**Status atual (2026-07):** o lado do App está pronto — o disparo já funciona em produção. **O workflow do n8n que efetivamente envia a mensagem ainda não existe** — depende da definição do provedor de WhatsApp/e-mail que a agência vai usar. Até lá, o webhook simplesmente não é chamado (`N8N_NOTIFICATION_WEBHOOK_URL` não configurado → `dispatch()` retorna sem fazer nada, silenciosamente).

## Quando o webhook é disparado

**Atualizado (2026-07):** criar a rodada de aprovação e notificar o cliente são dois passos separados — `TaskApprovalService::submitForApproval()` só cria a `TaskApprovalRound` e gera um `TaskApprovalToken` por contato, **sem** disparar nada ainda. O webhook só sai quando alguém chama `TaskApprovalService::sendToClient(TaskApprovalRound $round)` — um POST por contato/token daquela rodada, e a rodada é marcada com `sent_at = now()` (idempotente: chamar de novo numa rodada já enviada não reenvia nada).

**Atualizado de novo (2026-07):** quem recebe aprovação não é mais um booleano único (`client_contacts.receives_approvals`, removido) — agora é uma assinatura por contato em `client_contact_subscriptions` (`type = 'aprovacao'`), com um conjunto de canais próprio por contato (`channels`: `whatsapp` e/ou `email`). Isso permite múltiplos aprovadores por cliente, cada um com seu próprio canal — gerenciado na aba "Contatos" da ficha do cliente. `submitForApproval()` lê essa tabela (`TaskApprovalService::getApprovalRecipients()`) e grava os `channels` de cada contato direto no `TaskApprovalToken` (snapshot no momento da criação, não recalculado depois) — `dispatchWebhook()` itera esses canais e faz um `NotificationDispatchService::dispatch()` por canal (não é mais um POST só com `channels` num array — cada canal vira sua própria request, igual todo o resto do sistema de notificações).

`submitForApproval()` é acionado por dois caminhos, e nenhum dos dois envia sozinho:

1. **Automático** (`TaskApprovalService::maybeAutoSubmitOnApprovalTransition()`, chamado pelo `TaskObserver` em todo `$task->update()` individual): cria a rodada sozinho sempre que `status = 'aprovacao'` **e** `situation = 'Enviar para o cliente'` ficam verdadeiros ao mesmo tempo — não importa se a mudança veio do board de Sprint, de Filas, de drag-and-drop ou de um formulário. Só cria se não houver rodada `pending` já aberta pra tarefa, e só se houver anexos não marcados como entregáveis ainda.
2. **Manual** (`TaskApprovalController::store()`): botão "Enviar para aprovação" na tela da tarefa, com seleção manual de anexos — cria a rodada, mas também não notifica.
3. **Atualização em massa (`bulkUpdate`) NÃO cria rodada** — decisão consciente, não uma limitação técnica: `Builder::update()` pula os eventos do Eloquent (e o `TaskObserver` com ele), e a regra de negócio é que aprovação sempre vai uma tarefa por vez pro cliente, nunca em lote.

`sendToClient()` só é chamado de um único lugar: o botão **"Enviar pro Cliente"** na Central de Aprovações (`/aprovacoes`, `ApprovalDashboardController::send()`) — uma ação manual e deliberada, separada da criação da rodada. Enquanto a rodada não foi enviada, ela aparece como "Aguardando Envio" (`TaskApprovalRound::displayStatusLabel()`).

## Endpoint e payload

Ver [notification-webhook-api.md](notification-webhook-api.md) — mesma URL (`N8N_NOTIFICATION_WEBHOOK_URL`), mesmo formato (`event`, `channel`, `client`, `contact`, `message.subject`/`body`, `fired_at`). Pra aprovação, `event = "aprovacao"` e o `link_aprovacao` (tokenizado, `route('approval.show', $token)`) já vem embutido no texto de `message.body` — não é mais um campo separado no payload (`link`/`expires_at`/`deliverables_count` do formato antigo saíram; se precisar dessa info no n8n, inclua no próprio texto do template via `{{link_aprovacao}}`).

Após o disparo, o App marca `task_approval_tokens.notified_at = now()` — não há confirmação de entrega vinda do n8n de volta pro App nesse fluxo (é fire-and-forget).

## O que falta para o fluxo ficar 100% funcional

1. Definir o provedor de WhatsApp (ex: API oficial da Meta, Twilio, Z-API etc.) e de e-mail.
2. Montar o workflow no n8n que recebe esse payload e dispara as mensagens (o link tokenizado já vem no texto).
3. Cadastrar `N8N_NOTIFICATION_WEBHOOK_URL` no ambiente de produção (Portainer) — mesma variável usada por todos os gatilhos.

## Fluxo de resposta do cliente (não depende do n8n)

O cliente responde direto no App, pelo link tokenizado (`route('approval.show', $token)`, público, sem login) — aprovação/pedido de ajuste por entregável é gravado em `deliverable_feedbacks`, e quando todos os contatos responderem (unanimidade), a rodada se resolve sozinha via `TaskApprovalService::tryResolveRound()`:
- Se qualquer contato pediu ajuste → rodada `changes_requested`, tarefa volta para `status = 'ajuste_alteracao'`.
- Se todos aprovaram → rodada `approved`, tarefa avança para `status = 'despacho_agendamento'`.

Isso é 100% App-to-App (o cliente acessa um link do próprio App) — não precisa de nenhum webhook do n8n nesse sentido.
