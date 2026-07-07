# API de Importação do ClickUp

## Objetivo

O n8n lê o ClickUp e envia os dados já limpos/normalizados para os endpoints abaixo — é o n8n quem lida com custom fields mal preenchidos, texto solto onde deveria ter estrutura, etc.

Direção do fluxo: **ClickUp → App**. Isso é o inverso da "regra de ouro" (App escreve primeiro, n8n sincroniza pro ClickUp) — aqui é o caminho de leitura/espelhamento do que já existe/mudou no ClickUp. O App nunca consulta a API do ClickUp diretamente (ver `CLAUDE.md` § O que NÃO fazer).

## Incidente (2026-07-06) — leia antes de rodar qualquer import em massa

Uma primeira tentativa de automatizar a importação (workflow com 4 branches, resolvendo `project_id` automaticamente via `list_id`) deu errado: um custom field mal identificado no ClickUp fez um projeto (`clickup_list_id`) apontar para a própria lista "Projetos (Projects)" em vez de sua lista de execução — o workflow então leu todos os outros cards de Projeto como se fossem tarefas de execução daquele projeto, criando 53 tarefas fantasma linkadas ao cliente errado. Isso também expôs um bug real e independente: a tela de tarefa (`tasks/show.blade.php`) quebrava com 500 sempre que o projeto da tarefa não tinha macroplano vinculado (`route()` com parâmetro obrigatório nulo) — corrigido separadamente, caindo em `projects.showDirect` quando não há macroplano.

**Dados remediados:** as 53 tarefas fantasma foram apagadas e o `clickup_list_id` incorreto foi zerado.

**Mudança de estratégia daqui pra frente:**
- **Migração de baixo pra cima, controlada, lista por lista.** Primeiro só tarefas operacionais — nenhum vínculo automático com Projeto. Projetos e Macroplanos **já foram lançados manualmente no App** (não precisam ser reimportados, só conferidos depois).
- **Nenhuma resolução automática de `project_id` via `list_id`.** O vínculo Tarefa → Projeto (e Projeto → Macroplano) é feito **manualmente, em lote, dentro do App** — ver funcionalidade de edição em massa (roadmap).
- O workflow de referência agora é deliberadamente simples: uma lista por vez, escolhida manualmente, sem automação de agendamento.

## Autenticação

Não usa Sanctum. Autenticação simples por header, comparado em tempo constante (`hash_equals`):

```
X-Import-Secret: {valor de IMPORT_SECRET no .env}
```

Sem esse header (ou com valor errado, quando `IMPORT_SECRET` está configurado): `401 Unauthorized`.

## `POST /api/clickup/import` — Tarefas (execução E tickets avulsos) — endpoint em uso ativo

Endpoint **genérico** — não é só para chamados/tickets. `is_ticket` é apenas mais um campo do payload.

**Payload:**
```json
{
  "tasks": [
    {
      "clickup_task_id": "86adp72n3",
      "project_id": null,
      "list_id": null,
      "client_clickup_id": "86ax9y2ab",
      "title": "Criar 3 criativos para campanha de julho",
      "description": "Briefing: ...",
      "clickup_status": "em produção",
      "clickup_priority": "high",
      "task_type": "criacao",
      "origin": "projeto",
      "destination": null,
      "situation": null,
      "due_date": "2026-07-15",
      "approval_date": null,
      "is_ticket": false,
      "requester_name": null,
      "requester_whatsapp": null,
      "requester_channel": null,
      "creator_email": "estrategista@nonna.com",
      "executor_email": "designer@nonna.com",
      "responsavel_email": "gestor@nonna.com",
      "attachments": [],
      "deleted": false
    }
  ]
}
```

**Na migração controlada atual, `project_id` e `list_id` vão sempre `null`.** O vínculo com Projeto é feito depois, manualmente, em lote, dentro do App — nunca inferido durante o import (ver Incidente acima). `list_id` só deveria voltar a ser enviado se/quando essa resolução automática for reintroduzida com salvaguardas melhores.

**Resolução de `client_id`:** por `client_clickup_id` (bate contra `clients.clickup_task_id`) ou, se ausente, herda do `client_id` do projeto resolvido (só relevante se `project_id` vier preenchido).

**`clickup_status` aceitos** (case-insensitive, mapeados internamente — outros valores caem em `backlog`):
`backlog`/`a fazer`/`to do`/`em planejamento`/`triagem` → `backlog` · `em atendimento`/`em criação` → `em_producao` · `aprovação` → `aguardando_aprovacao` · `alteração`/`ajuste` → `ajuste` · `em copy`/`copy` → `em_copy` · `pronto p/ produção` → `pronto_producao` · `em produção`/`in progress`/`em andamento` → `em_producao` · `em revisão`/`review` → `revisao` · `aguardando envio` → `aguardando_envio` · `aguardando resposta`/`aguardando cliente` → `aguardando_resposta` · `concluído`/`done`/`complete` → `concluido` · `aprovado`/`approved` → `aprovado` · `cancelado`/`cancelled` → `cancelado`

**`clickup_priority` aceitos:** `urgent`/`1` → `urgente` · `high`/`2` → `medio` · `normal`/`3`/`low`/`4` → `normal`

**`task_type`:** precisa bater com uma chave de `Task::$types` (`app/Models/Task.php`) — senão cai em `criacao`.

**Exclusão/arquivamento no ClickUp:** enviar `"deleted": true` (só precisa de `clickup_task_id` junto). O App **não apaga a linha** — atualiza `status` para `cancelado` e preserva o histórico. Se a tarefa não existir ainda no App, é apenas contada como `skipped`.

**Resposta 200:**
```json
{ "imported": 1, "updated": 0, "skipped": 0, "errors": [] }
```

## `POST /api/clickup/import-macroplans` e `POST /api/clickup/import-projects` — não usados na migração atual

Esses dois endpoints continuam existindo e funcionando (contrato inalterado — ver histórico deste arquivo se precisar), mas **não fazem parte do fluxo de migração atual**: Macroplanos e Projetos já foram lançados manualmente no App e só precisam ser conferidos, não reimportados. Só voltam a ser relevantes se um dia for necessário resincronizar Macroplanos/Projetos em massa a partir do ClickUp — nesse caso, revisitar este documento e aplicar as mesmas cautelas do Incidente acima (nada de resolução automática de vínculo sem dupla checagem).

## Erros

- Falha ao montar os lookups iniciais (conexão, etc.): `500` com `error` e `file` (arquivo:linha).
- Erro ao processar uma linha específica (ex: cliente não resolvido): não interrompe o lote — a linha entra em `errors[]` com `index`/`clickup_task_id`/`error`, e o processamento continua para as próximas linhas.

## Workflow n8n de referência

Existe um workflow de referência em [`.claude/docs/n8n-workflows/clickup-import.json`](n8n-workflows/clickup-import.json) — **deliberadamente simples**: um único fluxo, trigger manual, uma Lista por execução (selecionada à mão no seletor nativo do node ClickUp), sem nenhum vínculo automático de Projeto. Usa o **node nativo `ClickUp`** (Resource: Task, Operation: Get All, `Return All` ligado) — resolve paginação sozinho. **Não foi testado contra um ClickUp/n8n real** — os nomes de custom field usados no Code node (`cliente_relacionado`, `deadline`, etc.) são suposições baseadas na convenção dos comandos artisan `clickup:import-*`; confira contra a resposta real antes de confiar no resultado (o próprio workflow orienta rodar só o node de leitura primeiro e inspecionar `custom_fields`).

### Como importar
1. No n8n: **Workflows → Import from File**.
2. Criar a credencial nativa **ClickUp API** `ClickUp API Token` (Credentials → New → ClickUp API): cole seu personal API token do ClickUp.
3. Criar a credencial **HTTP Header Auth** `Nonna App Import Secret` (`X-Import-Secret: {IMPORT_SECRET do Portainer}`).
4. Abrir o node **Get ClickUp Tasks**, conectar a credencial e selecionar a Lista pelo seletor nativo — **uma lista de cada vez**.
5. Rodar só esse node primeiro, conferir os `custom_fields` do resultado, ajustar o Code node se os nomes reais forem diferentes.
6. Só então rodar o workflow completo pra aquela lista.

### Filtro de status (só tarefas ativas)

Traz **só o que está ativo** — concluído/cancelado/finalizado/encerrado ficam de fora, com dupla proteção: `Include Closed = false` no filtro do node nativo, e um filtro explícito por nome de status dentro do Code node (mesma lista de status usada pelos comandos artisan `clickup:import-*`).

### Limitações conhecidas (ver Sticky Notes no próprio workflow)
- **Detecção de `deleted` não implementada** — o endpoint já sabe tratar `deleted: true` (cancela em vez de apagar), mas o workflow não envia isso ainda. Detectar exclusão exigiria comparar os IDs retornados contra os já conhecidos no App — fica pra depois, quando a migração inicial estiver estável.
- **Vínculo com Projeto é 100% manual** — por desenho, depois do Incidente acima. Ver roadmap da funcionalidade de edição em massa no App.

## Cuidado com qualidade do dado de origem

Vale checar manualmente se não há **cards fora de lugar** nas listas do ClickUp antes de importar uma lista (ex.: encontramos dois cards `BLOCO 1: VISÃO GERAL E METAS` / `BLOCO 2: CONTEXTO E ESTRATÉGIA` soltos dentro da lista "Projetos (Projects)" — nomes que batem com os blocos internos de um Macroplanejamento, sugerindo que foram criados na lista errada por engano).
