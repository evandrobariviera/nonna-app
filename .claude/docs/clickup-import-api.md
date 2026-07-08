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
      "publish_date": null,
      "approval_method": null,
      "internal_approval": false,
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

**`list_name` (opcional, 2026-07-08):** nome da Lista de origem no ClickUp — só enviado pela sincronização em tempo real (`clickup-realtime-sync.json`), o workflow de migração em lote não manda esse campo. Serve **exclusivamente** para vincular `sprint_id` automaticamente (ver seção abaixo); não tem nenhum outro efeito.

**Vínculo automático de Sprint via `list_name`:** se `list_name` vier preenchido e a tarefa **ainda não tiver `sprint_id`**, o App compara (case-insensitive, com `trim`) contra o `title` de todas as Sprints cadastradas e, se bater, vincula. **Só vincula na primeira vez** — nunca sobrescreve uma organização manual já feita no App (mesma cautela já aplicada a `project_id`). Sem match: `sprint_id` continua `null`, sem erro. Depende de usar os mesmos nomes dos dois lados (Sprint no App = nome da Lista no ClickUp) — é o mecanismo confirmado com o usuário, não uma convenção nova.

**Resolução de `client_id`:** por `client_clickup_id` (bate contra `clients.clickup_task_id`) ou, se ausente, herda do `client_id` do projeto resolvido (só relevante se `project_id` vier preenchido).

**`clickup_status` aceitos** (case-insensitive, mapeados internamente — outros valores caem em `backlog`):
`backlog`/`a fazer`/`to do`/`em planejamento`/`triagem` → `backlog` · `em atendimento`/`em criação` → `em_producao` · `aprovação` → `aguardando_aprovacao` · `alteração`/`ajuste` → `ajuste` · `em copy`/`copy` → `em_copy` · `pronto p/ produção` → `pronto_producao` · `em produção`/`in progress`/`em andamento` → `em_producao` · `em revisão`/`review` → `revisao` · `aguardando envio` → `aguardando_envio` · `aguardando resposta`/`aguardando cliente` → `aguardando_resposta` · `concluído`/`done`/`complete` → `concluido` · `aprovado`/`approved` → `aprovado` · `cancelado`/`cancelled` → `cancelado`

**`clickup_priority` aceitos:** `urgent`/`1` → `urgente` · `high`/`2` → `medio` · `normal`/`3`/`low`/`4` → `normal`

**`task_type`:** precisa bater com uma chave de `Task::$types` (`app/Models/Task.php`) — senão cai em `criacao`.

**`publish_date`** (`YYYY-MM-DD` ou vazio): data de publicação, coluna própria (`tasks.publish_date`), separada de `due_date`/`approval_date`.

**`approval_method`:** precisa bater com uma chave de `Task::$approvalMethods` (`aprovaaí`, `whatsapp`, `email`) — senão vira `null` (não quebra o import).

**`internal_approval`** (boolean): `false` se ausente.

**Exclusão/arquivamento no ClickUp:** enviar `"deleted": true` (só precisa de `clickup_task_id` junto). O App **não apaga a linha** — atualiza `status` para `cancelado` e preserva o histórico. Se a tarefa não existir ainda no App, é apenas contada como `skipped`.

**Resposta 200:**
```json
{ "imported": 1, "updated": 0, "skipped": 0, "errors": [] }
```

## Workflow n8n de sincronização em tempo real (webhook, 2026-07-08)

Separado do workflow de migração em lote (manual, lista por lista): [`.claude/docs/n8n-workflows/clickup-realtime-sync.json`](n8n-workflows/clickup-realtime-sync.json) reage a eventos de criação/atualização de tarefa no ClickUp inteiro, em tempo real, via **ClickUp Trigger** (webhook nativo, eventos Task Created + Task Updated no Team todo).

```
ClickUp Trigger (webhook) → Config → Get Task by ID → Route by List (Switch)
    ├─ list.id = 901326341797 (Planejamentos)  → Build Macroplan Payload → POST /api/clickup/import-macroplans
    ├─ list.id = 901326341887 (Projetos)       → Build Project Payload   → POST /api/clickup/import-projects
    └─ qualquer outra Lista (Tarefa/Sprint/…) → Build Task Payload      → POST /api/clickup/import
```

**Por que precisa de "Get Task by ID":** o payload do webhook do ClickUp só traz `task_id` + tipo de evento, não os `custom_fields` completos — por isso todo evento dispara uma busca da tarefa inteira antes de decidir a branch.

**Roteamento por List ID fixo:** os mesmos IDs usados desde os comandos artisan originais (`901326341797` = Planejamentos/Roadmaps, `901326341887` = Projetos). Qualquer outra lista (Filas, Chamados, qualquer Sprint) cai na branch de Tarefa — de propósito, já que sincronização em tempo real precisa cobrir o workspace inteiro, não só as listas já migradas manualmente.

**`incluir_fechados: true` por padrão** neste workflow (diferente do workflow de migração, que é `false` por padrão) — sincronização contínua deve sempre refletir o status atual da tarefa no ClickUp, incluindo concluído/cancelado.

**Branch Tarefa:** reaproveita exatamente a mesma lógica de mapeamento de campos já validada contra amostras reais (ver tabela na seção anterior), só adicionando `list_name` ao payload.

**Branches Macroplano/Projeto: ⚠️ nunca validadas contra um JSON real** de tarefa dessas listas — só o padrão do custom field `cliente_relacionado` foi confirmado (é reaproveitado em todo o workspace). Antes de confiar, rode manualmente criando/editando um Planejamento e um Projeto de teste no ClickUp e confira o resultado, mesma cautela já aplicada à branch de Tarefa quando ela ainda era nova.

**`clickup_list_id` e `macro_plan_clickup_id` do Projeto ficam sempre `null`**, nunca adivinhados via custom field — foi tentar adivinhar exatamente esses dois campos que causou o Incidente descrito no topo deste documento. Vínculo com lista de execução e com Macroplano continuam manuais no App (lápis de edição rápida em `/projetos`).

**Parâmetros do node ClickUp Trigger não testados contra uma instância real** — depois de importar o workflow, abra o node e confirme que o Team e os eventos (Task Created, Task Updated) carregaram certo antes de ativar.

## `POST /api/clickup/import-macroplans` e `POST /api/clickup/import-projects` — não usados na migração em lote atual, usados pela sincronização em tempo real

Contrato inalterado (ver histórico deste arquivo se precisar). Não fazem parte do workflow de **migração em lote** (`clickup-import.json`) — Macroplanos e Projetos já foram lançados manualmente no App e só precisam ser conferidos, não reimportados em massa. São, no entanto, chamados pelo workflow de **sincronização em tempo real** (`clickup-realtime-sync.json`, ver seção acima) sempre que um evento do ClickUp cai nas listas de Planejamentos/Projetos — com as mesmas cautelas do Incidente acima (nada de resolução automática de vínculo sem dupla checagem).

## Erros

- Falha ao montar os lookups iniciais (conexão, etc.): `500` com `error` e `file` (arquivo:linha).
- Erro ao processar uma linha específica (ex: cliente não resolvido): não interrompe o lote — a linha entra em `errors[]` com `index`/`clickup_task_id`/`error`, e o processamento continua para as próximas linhas.

## Workflow n8n de referência

Existe um workflow de referência em [`.claude/docs/n8n-workflows/clickup-import.json`](n8n-workflows/clickup-import.json) — **deliberadamente simples**: um único fluxo, trigger manual, uma Lista por execução (selecionada à mão no seletor nativo do node ClickUp), sem nenhum vínculo automático de Projeto. Usa o **node nativo `ClickUp`** (Resource: Task, Operation: Get All, `Return All` ligado) — resolve paginação sozinho.

**Mapeamento de custom fields confirmado (2026-07-07)** contra um JSON real de tarefa da lista "Chamados (Tickets)" — não é mais suposição. Descobertas que corrigiram a v1 (que nunca tinha sido validada contra dado real):

| Campo do App | Origem no ClickUp | Como resolver |
|---|---|---|
| `client_clickup_id` | custom field `🤝 cliente_relacionado` (tipo `tasks`) | `value[0].id` |
| `due_date` | campo **nativo** `due_date` da tarefa (não é custom field — "Deadline" não existe) | epoch ms → `YYYY-MM-DD` |
| `approval_date` | custom field "Data de aprovação" (tipo `date`) | epoch ms → `YYYY-MM-DD` |
| `publish_date` | custom field "Data de publicação" (tipo `date`) | epoch ms → `YYYY-MM-DD` |
| `task_type` | custom field "Tipo de Tarefa" (tipo `drop_down`) | `value` é **índice numérico** em `type_config.options` → `options[value].name`, mapeado por tabela pro key do App |
| `origin` | custom field "Origem" (tipo `drop_down`) | mesma mecânica de índice; valores batem quase 1:1 com `Task::$origins` |
| `destination` | custom field "Destino" (tipo `labels`) | `value` é **array de IDs de option** → resolvido via `options.find(o => o.id === id).label` (repare: `.label`, não `.name` — `labels` e `drop_down` usam propriedades diferentes), mapeado por tabela |
| `situation` | custom field "Situação" (tipo `drop_down`) | resolvido do mesmo jeito, mas guardado **como texto livre** (coluna `situation` não é enum fechado) — sem mapeamento pra chave |
| `approval_method` | custom field "Método de Aprovação" (tipo `labels`) | mesma mecânica de `destination`, mapeado por tabela |
| `internal_approval` | custom field "Aprovação Interna" (tipo `checkbox`) | `!!value` (ausente = `false`) |
| `requester_name` | custom field contendo "Solicitante" **com `type === 'short_text'`** | há 3 campos diferentes com "Solicitante" no nome (e-mail, nome, whats) — só dá pra diferenciar pelo `type` |
| `requester_whatsapp` | custom field contendo "Solicitante" **com `type === 'phone'`** | idem |

Se for migrar uma Lista diferente de "Chamados", **confira de novo antes de confiar** — nomes de campo podem variar entre listas mesmo para o mesmo tipo de dado (execute só o node **Get ClickUp Tasks** e inspecione `custom_fields` de uma tarefa real antes de deixar rodar até o POST).

### Como importar
1. No n8n: **Workflows → Import from File**.
2. Criar a credencial nativa **ClickUp API** `ClickUp API Token` (Credentials → New → ClickUp API): cole seu personal API token do ClickUp.
3. Criar a credencial **HTTP Header Auth** `Nonna App Import Secret` (`X-Import-Secret: {IMPORT_SECRET do Portainer}`).
4. Abrir o node **Config** (roda logo depois do trigger), conferir `app_url` e definir `incluir_fechados` (ver seção abaixo).
5. Abrir o node **Get ClickUp Tasks**, conectar a credencial e selecionar a Lista pelo seletor nativo — **uma lista de cada vez**.
6. Rodar só esse node primeiro, conferir os `custom_fields` do resultado, ajustar o Code node se os nomes reais forem diferentes.
7. Só então rodar o workflow completo pra aquela lista.

### Filtro de status — `incluir_fechados` (2026-07-07)

Por padrão (`incluir_fechados: false` no node **Config**) o workflow traz **só o que está ativo** — concluído/cancelado/finalizado/encerrado ficam de fora, com dupla proteção: `Include Closed` no filtro do node nativo (agora uma expressão lendo o Config) e um filtro explícito por nome de status dentro do Code node.

**Sprints e Listas já encerradas precisam de `incluir_fechados: true`.** Na primeira tentativa de migrar uma Sprint antiga, o workflow rodou sem erro mas **não importou nada** — o node "Get ClickUp Tasks" trouxe 82 itens, mas "Build Tasks Payload" devolveu `tasks: []`, porque a Sprint inteira já estava com status fechado e o filtro "só ativas" removeu tudo. Pra trazer o histórico de sprints encerradas, mude `incluir_fechados` pra `true` no Config antes de rodar aquela lista — isso desliga os dois filtros (o da API do ClickUp e o do código) só para aquela execução.

### Limitações conhecidas (ver Sticky Notes no próprio workflow)
- **Detecção de `deleted` não implementada** — o endpoint já sabe tratar `deleted: true` (cancela em vez de apagar), mas o workflow não envia isso ainda. Detectar exclusão exigiria comparar os IDs retornados contra os já conhecidos no App — fica pra depois, quando a migração inicial estiver estável.
- **Vínculo com Projeto é 100% manual** — por desenho, depois do Incidente acima. Ver roadmap da funcionalidade de edição em massa no App.

## Cuidado com qualidade do dado de origem

Vale checar manualmente se não há **cards fora de lugar** nas listas do ClickUp antes de importar uma lista (ex.: encontramos dois cards `BLOCO 1: VISÃO GERAL E METAS` / `BLOCO 2: CONTEXTO E ESTRATÉGIA` soltos dentro da lista "Projetos (Projects)" — nomes que batem com os blocos internos de um Macroplanejamento, sugerindo que foram criados na lista errada por engano).
