# API de Importação do ClickUp

## Objetivo

O n8n lê o ClickUp (API própria ou as tabelas `clickup_*` espelhadas no Postgres), **valida e normaliza os campos** — é o n8n quem lida com custom fields mal preenchidos, texto solto onde deveria ter estrutura, etc. — e envia o resultado já limpo para os 3 endpoints abaixo.

Direção do fluxo: **ClickUp → App**. Isso é o inverso da "regra de ouro" (App escreve primeiro, n8n sincroniza pro ClickUp) — aqui é o caminho de leitura/espelhamento do que já existe/mudou no ClickUp. O App nunca consulta a API do ClickUp diretamente (ver `CLAUDE.md` § O que NÃO fazer).

Os comandos artisan `clickup:import-planejamentos` / `clickup:import-projetos` / `clickup:import-chamados` fazem algo parecido, mas lendo direto do jsonb bruto da tabela `clickup_tasks` via SQL — ficam como caminho alternativo/legado (útil pra backfill pontual), não o caminho principal. **O caminho recomendado é sempre via n8n + estes 3 endpoints**, porque o n8n consegue tratar inconsistência de dado de origem de forma mais robusta que uma query SQL contra JSON bruto.

## Autenticação

Não usa Sanctum. Autenticação simples por header, comparado em tempo constante (`hash_equals`):

```
X-Import-Secret: {valor de IMPORT_SECRET no .env}
```

Sem esse header (ou com valor errado, quando `IMPORT_SECRET` está configurado): `401 Unauthorized`.

## `POST /api/clickup/import` — Tarefas (execução E tickets avulsos)

Endpoint **genérico** — não é só para chamados/tickets. `is_ticket` é apenas mais um campo do payload; o mesmo endpoint recebe tanto tarefa de execução dentro de um Projeto quanto ticket avulso.

**Payload:**
```json
{
  "tasks": [
    {
      "clickup_task_id": "86adp72n3",
      "project_id": null,
      "list_id": "901326341887",
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

**Resolução de `project_id`:** se vier `project_id` direto no payload, usa ele. Senão, resolve por `list_id` batendo contra `projects.clickup_list_id` já cadastrado no App. Se nenhum dos dois resolver, a tarefa fica com `project_id = null` (avulsa/ticket).

**Resolução de `client_id`:** por `client_clickup_id` (bate contra `clients.clickup_task_id`) ou, se ausente, herda do `client_id` do projeto resolvido.

**Tarefa de execução (dentro de um Projeto)** — o caso comum: enviar `list_id` (a `clickup_list_id` do projeto), `is_ticket: false`, `origin: "projeto"`.

**Ticket avulso** — o caso do antigo `clickup:import-chamados`: enviar `is_ticket: true`, `origin: "ticket"`, e os campos `requester_*` preenchidos (quem solicitou fora da equipe).

**`clickup_status` aceitos** (case-insensitive, mapeados internamente — outros valores caem em `backlog`):
`backlog`/`a fazer`/`to do`/`em planejamento`/`triagem` → `backlog` · `em atendimento`/`em criação` → `em_producao` · `aprovação` → `aguardando_aprovacao` · `alteração`/`ajuste` → `ajuste` · `em copy`/`copy` → `em_copy` · `pronto p/ produção` → `pronto_producao` · `em produção`/`in progress`/`em andamento` → `em_producao` · `em revisão`/`review` → `revisao` · `aguardando envio` → `aguardando_envio` · `aguardando resposta`/`aguardando cliente` → `aguardando_resposta` · `concluído`/`done`/`complete` → `concluido` · `aprovado`/`approved` → `aprovado` · `cancelado`/`cancelled` → `cancelado`

**`clickup_priority` aceitos:** `urgent`/`1` → `urgente` · `high`/`2` → `medio` · `normal`/`3`/`low`/`4` → `normal`

**`task_type`:** precisa bater com uma chave de `Task::$types` (`app/Models/Task.php`) — senão cai em `criacao`.

**Exclusão/arquivamento no ClickUp:** enviar `"deleted": true` (só precisa de `clickup_task_id` junto). O App **não apaga a linha** — atualiza `status` para `cancelado` e preserva o histórico. Se a tarefa não existir ainda no App, é apenas contada como `skipped`.

**Resposta 200:**
```json
{ "imported": 1, "updated": 0, "skipped": 0, "errors": [] }
```

## `POST /api/clickup/import-macroplans` — Macroplanejamentos (Roadmaps)

**Payload:**
```json
{
  "macroplans": [
    {
      "clickup_task_id": "86adp99xx",
      "client_clickup_id": "86ax9y2ab",
      "title": "[ROADMAP] Sulfibra",
      "clickup_status": "em execução",
      "description": "Contexto e estratégia do ciclo...",
      "period_start": null,
      "period_end": null,
      "creator_email": "evandro@nonna.com",
      "responsible_email": "estrategista@nonna.com",
      "attachments": [],
      "deleted": false
    }
  ]
}
```

- `client_clickup_id` é **obrigatório** (bate contra `clients.clickup_task_id`) — sem ele, a linha vira `skipped` com erro em `errors[]`, não é criada com cliente nulo.
- `period_start`/`period_end`: se ausentes (comum — Roadmap no ClickUp raramente tem datas), o App usa `hoje` → `hoje + 90 dias` como fallback.
- `description` vira o campo `bloco2.conteudo` do macroplano (bloco "Contexto e Estratégia").
- `clickup_status` aceitos: `em planejamento`/`rascunho`/`draft` → `draft` · `ativo`/`active`/`in progress`/`em andamento` → `active` · `concluído`/`done`/`complete`/`encerrado`/`closed` → `closed`. **Não existe status "cancelado" neste model** — o mirror de exclusão usa `closed`.
- `deleted: true` → marca `status: closed`, não apaga a linha.

**Resposta 200:** mesmo formato de `import`.

## `POST /api/clickup/import-projects` — Projetos (= Campanhas)

No vocabulário de negócio da Nonna, **"Projeto" e "campanha" são a mesma coisa** — "grande campanha ou iniciativa que nasce a partir de um Roadmap" (`.claude/docs/business-context.md`). Não confundir com um nível hierárquico à parte.

**Payload:**
```json
{
  "projects": [
    {
      "clickup_task_id": "86adp88yy",
      "client_clickup_id": "86ax9y2ab",
      "macro_plan_clickup_id": "86adp99xx",
      "clickup_list_id": "901326341887",
      "title": "{Sulfibra} — Aquecimento Julho",
      "objective": "Aquecer a base antes do lançamento de agosto",
      "clickup_status": "em execução",
      "attachments": [],
      "deleted": false
    }
  ]
}
```

- `client_clickup_id` obrigatório, mesma regra do macroplano.
- `macro_plan_clickup_id` é **opcional** — se não vier ou não resolver, o projeto entra com `macro_plan_id = null` e pode ser linkado manualmente depois no App.
- `clickup_list_id`: importante gravar este campo — é ele que permite ao endpoint `/clickup/import` resolver `project_id` automaticamente a partir do `list_id` de uma tarefa de execução (ver acima). Sem isso, tarefas dessa lista não conseguem ser auto-linkadas ao projeto.
- `clickup_status` aceitos: `em planejamento`/`rascunho`/`draft` → `draft` · `ativo`/`active`/`in progress`/`em andamento` → `active` · `contínuo`/`continuous` → `continua` · `concluído`/`done`/`complete` → `completed` · `cancelado`/`cancelled`/`canceled` → `cancelled`.
- `deleted: true` → marca `status: cancelled`, não apaga a linha.

**Resposta 200:** mesmo formato de `import`.

## Erros

- Falha ao montar os lookups iniciais (conexão, etc.): `500` com `error` e `file` (arquivo:linha).
- Erro ao processar uma linha específica (ex: cliente não resolvido): não interrompe o lote — a linha entra em `errors[]` com `index`/`clickup_task_id`/`error`, e o processamento continua para as próximas linhas.

## Cuidado com qualidade do dado de origem

Antes de rodar uma carga em massa, vale checar manualmente se não há **cards fora de lugar** nas listas do ClickUp (ex.: encontramos dois cards `BLOCO 1: VISÃO GERAL E METAS` / `BLOCO 2: CONTEXTO E ESTRATÉGIA` soltos dentro da lista "Projetos (Projects)" — nomes que baten com os blocos internos de um Macroplanejamento, sugerindo que foram criados na lista errada por engano). Esse tipo de card viraria um "Projeto" fantasma no App se importado sem filtro.
