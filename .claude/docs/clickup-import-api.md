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

## Incidente 2 (2026-07-09) — tarefas antigas caindo na Fila como "backlog"

Depois de habilitar o fallback de cliente (ver seção de `client_clickup_id` abaixo) e rodar a sincronização contra Listas/Sprints antigas, ~1033 tarefas entraram no App com status `backlog` — a maioria (816) tarefas de 2025, sem cliente relacionado no ClickUp (título tipo "teste", "TESTE ANEXOS"), claramente sobras de antes do workspace do ClickUp ser reestruturado (2026-03). Causa: o `STATUS_MAP` do App tinha fallback `'backlog'` pra qualquer status sem mapeamento — como essas tarefas usavam nomes de status de uma convenção antiga (não existe mais no workspace atual), todas caíram no fallback e foram tratadas como ativas, poluindo a Fila.

**Duas correções (não uma):**
1. **Fallback de status agora é `concluido`, não `backlog`** (`ClickupImportController::importTask()`). Um status sem mapeamento é tratado como legado/encerrado, não como "precisa de atenção agora". Isso corrige retroativamente qualquer tarefa já importada com esse problema — basta rodar o import de novo (idempotente).
2. **Filtro de data nos 3 workflows** (`clickup-import.json`, `clickup-realtime-sync.json`, `clickup-scheduled-resync.json`, node "Build Task(s) Payload"): tarefas com `date_created` anterior a **2025-01-01** são **excluídas antes de montar o payload** — nem chegam a ser enviadas pro App. Tarefa sem `date_created` (não deveria acontecer, mas por segurança) **não é excluída** (fail-open, pra nunca perder tarefa válida por falta desse campo).

## Incidente 3 (2026-07-09) — tarefa em duas telas ao mesmo tempo (Atendimento + Fila)

Uma tarefa que nasceu como Ticket (`origin: 'ticket'`) e depois foi movida no ClickUp da Lista "Chamados (Tickets)" pra "Filas (Backlog)" continuava aparecendo em **/atendimento** (filtra por `is_ticket = true`) **e** em **/filas** (filtra só por `sprint_id` vazio) ao mesmo tempo — porque `is_ticket` era gravado uma única vez na importação (espelho de `origin`) e nunca mais atualizado, mesmo a tarefa tendo mudado de Lista desde então.

**Correção:** `is_ticket` agora é recalculado a cada sync a partir de `list_name` (a Lista *atual* no ClickUp) — só é `true` se a tarefa **ainda estiver** em "Chamados (Tickets)"; qualquer outra Lista (Filas, Sprint) vira `is_ticket = false`, e a tarefa passa a aparecer só em `/filas`. **`origin` não muda** — continua guardando que a tarefa nasceu como ticket, pra relatório/filtro histórico (`origin=ticket` em `/filas`), só não decide mais onde a tarefa aparece.

Sem `list_name` no payload (`clickup-import.json`, que não manda esse campo), mantém o comportamento antigo (`is_ticket` = valor vindo do payload, baseado em `origin`) — o fix é opt-in por workflow, igual ao match de sprint.

## Filtro temporário de migração — cliente por cliente (2026-07-23)

Em 2026-07-23, depois de zerar `tasks`/`projects`/`macro_plans`/`brand_dossiers` em produção (ver histórico de decisão), a estratégia de retomada do sync passou a ser **gradual, cliente por cliente**, em vez de trazer todo o workspace de uma vez: só um cliente por vez tem suas tarefas sincronizadas pro App, os demais continuam de fora até serem migrados explicitamente.

O filtro fica **no n8n, não no App** (decisão explícita — a alternativa de guardar uma flag em `clients` e filtrar no `ClickupImportController` foi considerada e descartada em favor de manter tudo no lado do n8n). Cada um dos 3 workflows (`clickup-import.json`, `clickup-realtime-sync.json`, `clickup-scheduled-resync.json`) tem um campo `clientes_migrados` no node **Config**: string com os `client_clickup_id` liberados, separados por vírgula (mesmo ID usado em `client_clickup_id` do payload — bate contra `clients.clickup_task_id`). O Code node que monta o payload de Tarefas (branch de Tarefa, nos 3 workflows) filtra antes de montar o array: tarefa de cliente fora da lista **nem chega a ser enviada** pro App.

**Vazio = nenhuma tarefa passa.** Migrar mais um cliente é só adicionar o ID dele na lista.

**Precisa manter os 3 workflows com o mesmo valor de `clientes_migrados`** — não há fonte única compartilhada entre eles (decisão consciente, ver acima). Esquecer de atualizar um dos 3 workflows ao migrar um cliente faz esse workflow continuar ignorando as tarefas dele.

**Só filtra a branch de Tarefa.** As branches de Macroplano/Projeto em `clickup-realtime-sync.json` não são afetadas por este filtro — mas ver decisão de 2026-07-30 abaixo, que resolve isso de outro jeito.

**É temporário, por desenho:** remover o filtro (campo `clientes_migrados` no Config + o bloco de filtro no Code node) dos 3 workflows quando todos os clientes já tiverem migrado.

## Macroplano/Projeto no `clickup-realtime-sync.json` — desligados de propósito (2026-07-30)

Confirmado com o usuário: Planejamento e Projeto continuam **100% lançados manualmente no App**, nunca vindos do ClickUp (nem em lote, nem em tempo real). No `clickup-realtime-sync.json`, o node **Route by List** continua identificando corretamente as Listas de Planejamentos (`901326341797`) e Projetos (`901326341887`) — importante pra nunca deixar esses eventos caírem por engano na branch de Tarefa genérica (foi exatamente isso que criou tarefas fantasma "[ROADMAP] ..." durante um teste da migração em lote, ver incidente abaixo) — mas as duas saídas correspondentes do Switch **não estão conectadas a nada**. Os nodes "Build Macroplan Payload"/"POST Import Macroplan" e "Build Project Payload"/"POST Import Project" continuam no workflow, só desconectados — preservados caso a decisão mude no futuro, mas inertes.

**Filtro de cliente ativado de verdade (2026-07-30):** a branch de Tarefa do `clickup-realtime-sync.json` estava rodando sem o campo `clientes_migrados` no Config nem o filtro correspondente no Code node — ou seja, sincronizava o workspace inteiro em tempo real, sem controle de cliente nenhum, apesar do design já estar documentado desde 2026-07-23. Corrigido: `clientes_migrados` agora tem o mesmo valor usado no workflow de migração em lote, e o filtro de data (`>= 2025-01-01`) + cliente foram adicionados ao "Build Task Payload", igual ao `clickup-import.json`. **Precisa reimportar o JSON atualizado no n8n** — a correção só existe na cópia de referência deste repo até isso ser feito.

## Incidente 4 (2026-07-29) — Lista errada selecionada no workflow de migração em lote

O node **Get ClickUp Tasks** do `clickup-import.json` estava configurado com `list: 901326341797` (Planejamentos) em vez da Lista operacional pretendida — provavelmente um erro ao trocar manualmente o ID pra migrar a próxima lista. Resultado: 5 tarefas fantasma `[ROADMAP] {Cliente}` (cards-resumo da Lista de Planejamentos, não a estrutura real de Macroplano) foram importadas como `tasks` soltas (`project_id` null). Bônus: o `clickup_status` recebido ("em execução") não bate com nenhum valor do mapeamento e caiu no fallback `concluido` — por isso ficaram invisíveis até na Fila (que esconde `concluido`/`cancelado` por padrão). As 5 tarefas foram apagadas.

**Lição:** confira sempre o valor de `list` no node antes de rodar — o seletor nativo do ClickUp é mais seguro que editar o ID à mão.

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

**`list_name` (opcional, 2026-07-08; passou a ser enviado também pelo workflow de migração em lote em 2026-07-30):** nome da Lista de origem no ClickUp. Serve **exclusivamente** para vincular `sprint_id` automaticamente (ver seção abaixo); não tem nenhum outro efeito. Antes de 2026-07-30 só a sincronização em tempo real mandava esse campo — o workflow de migração em lote (`clickup-import.json`) nunca vinculava a nenhuma Sprint (aberta ou fechada), o que gerou confusão ao importar Listas de Sprints antigas já encerradas no App (as tarefas caíam soltas na Fila em vez de entrar na Sprint correspondente). Corrigido adicionando o mesmo campo (`t.list ? t.list.name : null`) ao "Build Tasks Payload".

**Vínculo automático de Sprint via `list_name`:** se `list_name` vier preenchido e a tarefa **ainda não tiver `sprint_id`**, o App compara (case-insensitive, com `trim`) contra o `title` de todas as Sprints cadastradas e, se bater, vincula. **Só vincula na primeira vez** — nunca sobrescreve uma organização manual já feita no App (mesma cautela já aplicada a `project_id`). Sem match: `sprint_id` continua `null`, sem erro. Depende de usar os mesmos nomes dos dois lados (Sprint no App = nome da Lista no ClickUp) — é o mecanismo confirmado com o usuário, não uma convenção nova.

**Resolução de `client_id`:** por `client_clickup_id` (bate contra `clients.clickup_task_id`) ou, se ausente, herda do `client_id` do projeto resolvido (só relevante se `project_id` vier preenchido). `client_id` é `NOT NULL` em `tasks` (regra de negócio — toda tarefa tem cliente) — **se nenhuma das duas resolver, cai no fallback do cliente interno "Nonna Agência Digital"** (2026-07-09), pra tarefas administrativas/internas ou tarefas antigas sem `cliente_relacionado` preenchido no ClickUp.

**`clickup_status` aceitos** (case-insensitive, mapeados internamente — outros valores caem em `concluido`, não em `backlog`, ver Incidente 2 acima):
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

## Workflow n8n de resync agendado — stopgap temporário (2026-07-08)

[`.claude/docs/n8n-workflows/clickup-scheduled-resync.json`](n8n-workflows/clickup-scheduled-resync.json) existe porque a sincronização em tempo real (seção acima) ainda não foi validada/ativada, e isso já deixou o App um dia inteiro sem receber nenhuma atualização do ClickUp. Enquanto isso não é resolvido, esse workflow roda num **Schedule Trigger** (padrão: a cada 30min) e repete a mesma busca já validada na migração manual, mas cobrindo várias Listas conhecidas de uma vez (não só uma por execução):

```
Schedule Trigger (30min) / Trigger Manual (teste) → Config
    ├─ Get Tasks: Chamados (Tickets)        (900701505618)
    ├─ Get Tasks: Filas (Backlog)           (901326341944)
    └─ Get Tasks: Sprint Ativa (editar Lista) — vem em branco, precisa selecionar manualmente
         ↓ (3 ramos) → Merge (append) → Build Tasks Payload → POST /api/clickup/import
```

**`incluir_fechados: true` sempre** — mesmo princípio da sincronização em tempo real: resync deve sempre refletir o status atual do ClickUp.

**Manutenção manual da Lista de Sprint:** como Sprints no ClickUp são Listas que mudam com frequência (nova sprint aberta = nova Lista), o node "Get Tasks: Sprint Ativa" não vem pré-preenchido — é preciso abrir e selecionar a Lista certa pelo seletor nativo antes de ativar, e atualizar sempre que uma nova sprint abrir (ou duplicar o node, ajustando `numberInputs` do Merge, se houver mais de uma sprint aberta ao mesmo tempo).

**⚠️ Limitação conhecida — sprint_id só vincula na 1ª vez:** o match de sprint por `list_name` (ver seção anterior) nunca sobrescreve um `sprint_id` já preenchido. Isso cobre bem "tarefa entrou numa Sprint", mas não cobre "tarefa saiu de uma Sprint de volta pra Filas/Chamados" nem "tarefa mudou de uma Sprint pra outra" — nesses casos o App fica desatualizado até um ajuste manual. Mudar esse comportamento é uma decisão de design que afeta também a sincronização em tempo real — não foi alterado aqui de propósito.

**É temporário:** quando a sincronização em tempo real estiver validada e ativa, desative (ou apague) este workflow — ele existe só como ponte até lá.

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

### Filtro de data — `date_created >= 2025-01-01` (2026-07-09)

Presente nos 3 workflows (`clickup-import.json`, `clickup-realtime-sync.json`, `clickup-scheduled-resync.json`), dentro do Code node "Build Task(s) Payload", **antes** do filtro de status. Tarefas com `date_created` anterior a `2025-01-01` são excluídas e **nem chegam a ser enviadas** pro App (não é uma questão de status — mesmo com `incluir_fechados: true` elas não passam). Motivo: ver Incidente 2 acima. Tarefa sem `date_created` (não deveria acontecer, campo nativo do ClickUp) não é excluída — fail-open, pra nunca perder tarefa válida por um campo faltando.

Se um dia precisar trazer histórico anterior a 2025 de propósito, é só remover/ajustar o `DATE_CUTOFF_MS` no Code node — não tem toggle no Config pra isso ainda (foi pensado como filtro permanente de higiene, não um modo liga/desliga por execução como o `incluir_fechados`).

### Limitações conhecidas (ver Sticky Notes no próprio workflow)
- **Detecção de `deleted` não implementada** — o endpoint já sabe tratar `deleted: true` (cancela em vez de apagar), mas o workflow não envia isso ainda. Detectar exclusão exigiria comparar os IDs retornados contra os já conhecidos no App — fica pra depois, quando a migração inicial estiver estável.
- **Vínculo com Projeto é 100% manual** — por desenho, depois do Incidente acima. Ver roadmap da funcionalidade de edição em massa no App.

## Cuidado com qualidade do dado de origem

Vale checar manualmente se não há **cards fora de lugar** nas listas do ClickUp antes de importar uma lista (ex.: encontramos dois cards `BLOCO 1: VISÃO GERAL E METAS` / `BLOCO 2: CONTEXTO E ESTRATÉGIA` soltos dentro da lista "Projetos (Projects)" — nomes que batem com os blocos internos de um Macroplanejamento, sugerindo que foram criados na lista errada por engano).
