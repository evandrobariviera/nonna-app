# Arquitetura Interna do Nonna App

## Visão Geral

O Nonna App é a fonte da verdade de tudo relacionado a clientes, diagnósticos, planejamentos e projetos. O ClickUp é apenas a camada operacional de execução — recebe tarefas via n8n e devolve o `clickup_task_id`.

## Pipeline CRM

O App é CRM completo. O funil:
```
contacts → opportunities → clients → client_onboardings → (active) → macro_plans → ...
```

Quando uma oportunidade é marcada como `ganho`:
1. App cria o `client` com os dados preenchidos na oportunidade
2. Vincula o `contact` via junction `client_contacts`
3. n8n dispara automações (WhatsApp, link de cadastro, Drive, etc.)
4. App cria o registro `client_onboardings` em fase 1

## Tabelas internas (todas em PostgreSQL, connection `pgsql`)

---

### `contacts` — Contatos / Leads (topo do funil)
Toda pessoa física entra aqui. Existe antes do cliente. Um contato pode virar cliente (via oportunidade ganha) ou permanecer apenas como ponto de contato.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | HasUuids |
| name | string | Nome completo |
| email | string nullable | |
| phone | string nullable | |
| whatsapp | string nullable | |
| job_title | string nullable | Cargo (Sócio, Gestor, Financeiro...) |
| company_name | string nullable | Nome da empresa (texto livre, antes de ter client_id) |
| source | enum: whatsapp/instagram/indicacao/site/linkedin/evento/outros | Canal de origem do lead |
| status | enum: novo/qualificado/em_negociacao/ganho/perdido/inativo | Status no funil |
| notes | text nullable | Observações internas |
| assigned_to | uuid (FK users) nullable | Responsável comercial |
| created_by | uuid (FK users) | |
| created_at / updated_at | timestamps | |

---

### `opportunities` — Oportunidades (Kanban Comercial)
Uma oportunidade é criada para um contato e percorre 7 estágios. Quando ganha, cria um `client`.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | HasUuids |
| contact_id | uuid (FK contacts) | Contato principal da oportunidade |
| client_id | uuid (FK clients) nullable | Preenchido quando o negócio é ganho |
| title | string | Ex: "Studio Aura — Tráfego + Social" |
| stage | enum: novo_lead/qualificacao/diagnostico_reuniao/proposta_enviada/negociando/ganho/perdido | Estágio no Kanban |
| services_interest | jsonb nullable | Array de serviços: ["trafego","social","site",...] |
| proposed_fee | decimal(10,2) nullable | Valor mensal proposto |
| proposed_ad_budget | decimal(10,2) nullable | Verba de mídia estimada |
| notes | text nullable | Observações da negociação |
| lost_reason | string nullable | Motivo da perda (quando `perdido`) |
| won_at | timestamp nullable | Quando o negócio foi fechado |
| lost_at | timestamp nullable | |
| assigned_to | uuid (FK users) nullable | Responsável comercial |
| created_by | uuid (FK users) | |
| created_at / updated_at | timestamps | |

---

### `client_contacts` — Junction: Clientes ↔ Contatos
Muitos-para-muitos. Um contato pode estar vinculado a múltiplos clientes; um cliente pode ter múltiplos contatos.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| client_id | uuid (FK clients) | |
| contact_id | uuid (FK contacts) | |
| role | string nullable | Papel: Sócio, Gestor, Financeiro, Ponto de Contato... |
| is_primary | boolean default false | Contato principal do cliente |
| created_at / updated_at | timestamps | |

---

### `client_onboardings` — Onboarding (5 fases)
Um registro por cliente. Gerenciado pela equipe interna. Baseado no Manual Operacional.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | HasUuids |
| client_id | uuid (FK clients) unique | Um onboarding por cliente |
| current_phase | enum: fase1/fase2/fase3/fase4/fase5/concluido | Fase atual |
| fase1_checklist | jsonb | {whatsapp_boas_vindas, link_cadastro_enviado, contrato_gerado, pasta_drive_criada, grupo_whatsapp_criado, clickup_notificado} |
| fase1_completed_at | timestamp nullable | |
| fase1_notes | text nullable | |
| fase2_checklist | jsonb | {welcome_call_realizada, regras_apresentadas, acessos_coletados, contrato_enviado, manual_enviado, kickoff_agendado} |
| fase2_completed_at | timestamp nullable | |
| fase2_notes | text nullable | |
| fase3_checklist | jsonb | {bm_auditado, pixel_configurado, gtm_instalado, ga4_configurado, api_conversoes_configurada, looker_studio_criado} |
| fase3_completed_at | timestamp nullable | |
| fase3_notes | text nullable | |
| fase4_checklist | jsonb | {kickoff_realizado, imersao_negocio_feita} |
| fase4_completed_at | timestamp nullable | |
| fase4_notes | text nullable | |
| fase5_checklist | jsonb | {macroplanejamento_criado, projetos_criados, backlog_preenchido, sprint_iniciado} |
| fase5_completed_at | timestamp nullable | |
| fase5_notes | text nullable | |
| completed_at | timestamp nullable | Data em que o onboarding foi concluído e cliente virou Ativo |
| responsible_id | uuid (FK users) nullable | Gestor do onboarding |
| created_at / updated_at | timestamps | |

---

### `clients` — CRM de Clientes
Tabela principal. Já existe com migrations.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | HasUuids |
| clickup_task_id | string nullable | Vínculo com ClickUp CRM (legado) |
| company_name | string | Razão Social |
| tax_id | string nullable | CPF/CNPJ |
| website | string nullable | |
| segment | string nullable | |
| status | enum: lead/active/inactive | |
| monthly_ad_budget | string nullable | Verba global mensal de mídia |
| contracted_services | jsonb | Array de serviços contratados |
| contact_email | string nullable | E-mail da empresa |
| contact_phone | string nullable | |
| address | text nullable | |
| zip_code | string nullable | |
| responsible_name | string nullable | Nome do responsável legal |
| responsible_birthdate | date nullable | |
| responsible_rg | string nullable | |
| responsible_cpf | string nullable | |
| responsible_address | text nullable | |
| responsible_marital_status | string nullable | |
| payment_method | enum: pix/cartao/boleto nullable | |
| billing_day | int nullable | 10, 15 ou 20 |
| billing_email | string nullable | |
| billing_whatsapp | string nullable | |
| billing_notes | text nullable | |
| notes | text nullable | Notas internas |
| registration_token | string nullable | Token para cadastro público |
| registration_completed_at | timestamp nullable | |

---

### `client_contacts` — Contatos do Cliente
CRUD próprio no App. Desconectado do ClickUp.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| client_id | uuid (FK clients) | |
| name | string | Nome completo |
| email | string nullable | |
| phone | string nullable | |
| job_title | string nullable | Cargo (Sócio, Gestor, Financeiro...) |
| is_primary | boolean default false | Contato principal |
| notes | text nullable | |
| created_at / updated_at | timestamps | |

---

### `client_ad_accounts` — Contas de Anúncios
Cadastro manual. Usado pelo n8n para consultar campanhas periodicamente.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| client_id | uuid (FK clients) | |
| platform | enum: meta/google/tiktok/linkedin/outros | |
| account_id | string | ID da conta na plataforma |
| name | string nullable | Apelido/nome da conta |
| status | enum: active/inactive/paused | |
| created_at / updated_at | timestamps | |

---

### `client_diagnostics` — Diagnósticos Estratégicos
Versionado. Um cliente pode ter múltiplos diagnósticos ao longo do tempo.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| client_id | uuid (FK clients) | |
| version | int default 1 | Versão do diagnóstico |
| title | string nullable | Ex: "Diagnóstico Inicial Jun/2026" |
| status | enum: draft/complete | |
| sec01_briefing | jsonb | História, produto, diferencial, desafio, aquisição, visão, obj_mkt, sucesso, checklist_coverage |
| sec02_marketing | jsonb | URLs canais digitais, histórico tráfego pago, processo venda, checklist |
| sec03_audit | jsonb | Avaliações por canal (site, instagram, facebook, linkedin, youtube, gmb) — cada canal: {score: ok/mediano/ruim, notes: text} |
| sec04_competition | jsonb | Espaço em aberto no mercado (texto estratégico) |
| sec05_persona | jsonb | Checklist de comportamento geral |
| sec06_synthesis | jsonb | Forças, problema_central, territorio_comunicacao, hipotese_posicionamento |
| created_by | uuid (FK users) | |
| completed_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

---

### `diagnostic_competitors` — Concorrentes (ilimitado por diagnóstico)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| diagnostic_id | uuid (FK client_diagnostics) | |
| position | int | Ordem de exibição |
| name | string | Nome do concorrente |
| main_channels | string nullable | Instagram, Google, indicação... |
| strengths | text nullable | Pontos fortes |
| vulnerability | text nullable | Vulnerabilidade estratégica |
| created_at / updated_at | timestamps | |

---

### `diagnostic_personas` — Personas (ilimitado por diagnóstico)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| diagnostic_id | uuid (FK client_diagnostics) | |
| position | int | Ordem de exibição |
| name | string | Nome fictício da persona |
| age_range | string nullable | Ex: "28-40 anos" |
| profession | string nullable | |
| income | string nullable | Faixa de renda |
| location | string nullable | |
| what_they_seek | text nullable | O que busca de verdade (motivação profunda) |
| behaviors | jsonb nullable | Checklist de comportamentos marcados |
| created_at / updated_at | timestamps | |

---

### `client_meeting_notes` — Atas de Reuniões

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| client_id | uuid (FK clients) | |
| macro_plan_id | uuid (FK macro_plans) nullable | Ata que originou um macroplanejamento |
| meeting_type | enum: comercial/kickoff/macroplanejamento/alinhamento/interna/captacao_material | |
| meeting_date | date | |
| raw_text | text | Texto corrido da ata (input do usuário) |
| processed_content | jsonb nullable | Estrutura processada pela IA (futuro) |
| created_by | uuid (FK users) | |
| created_at / updated_at | timestamps | |

---

### `macro_plans` — Macroplanejamentos (ciclos 60-90 dias)

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| client_id | uuid (FK clients) | |
| meeting_note_id | uuid (FK client_meeting_notes) nullable | Ata que originou |
| title | string | Ex: "Macroplanejamento Q2 2026 — Gomes" |
| period_start | date | |
| period_end | date | |
| responsible_id | uuid (FK users) nullable | |
| status | enum: draft/active/closed | |
| bloco1 | jsonb | Visão geral: foco_principal, verba_anuncios, metas_indicadores |
| bloco2 | jsonb | Contexto: desafio_atual, estrategia, pilares_comunicacao |
| bloco4 | jsonb | Tarefas isoladas e rotina (texto livre) |
| bloco5 | jsonb | Checklist de infraestrutura e acessos |
| launched_at | timestamp nullable | Quando foi lançado no ClickUp |
| created_by | uuid (FK users) | |
| created_at / updated_at | timestamps | |

*Bloco 3 = projetos vinculados (tabela `projects`)*

---

### `projects` — Projetos Multidisciplinares

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| macro_plan_id | uuid (FK macro_plans) | |
| client_id | uuid (FK clients) | |
| position | int | Ordem no macroplanejamento |
| title | string | Nome do projeto |
| objective | text | Objetivo do projeto |
| disciplines | jsonb | Array: ["criacao", "web", "trafego", "setup", "social", "seo", "email"] |
| briefing_criacao | text nullable | Briefing para Head Criativo |
| briefing_web | text nullable | Briefing para Head Web/Tech |
| briefing_trafego | text nullable | Briefing para Head de Tráfego |
| briefing_setup | text nullable | Briefing para Setup/Tracking |
| briefing_social | text nullable | Briefing para Social Media |
| briefing_seo | text nullable | Briefing para SEO |
| briefing_email | text nullable | Briefing para E-mail Marketing |
| briefing_estrategia | text nullable | Briefing para Estratégia |
| briefing_relacionamento | text nullable | Briefing para Relacionamento/CS |
| status | enum: em_planejamento/aprovacao/em_execucao/stand_by/concluido/cancelado | |
| clickup_task_id | string nullable | Preenchido após lançamento no ClickUp |
| launched_at | timestamp nullable | |
| created_at / updated_at | timestamps | |

---

### `tasks` — Tarefas

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| project_id | uuid (FK projects) nullable | Null se for tarefa avulsa/ticket |
| macro_plan_id | uuid (FK macro_plans) nullable | |
| client_id | uuid (FK clients) | |
| title | string | |
| description | text nullable | |
| task_type | enum: criacao/setup/trafego/web/estrategia/reunioes/administrativo | |
| executor_id | uuid (FK users) nullable | Quem executa |
| status | enum: backlog/em_copy/pronto_producao/em_producao/revisao/aguardando_envio/aguardando_resposta/concluido/ajuste/cancelado | |
| situation | string nullable | Sub-status detalhado (ex: "Em Redação", "Aguardando Aprovação") |
| due_date | date nullable | Vencimento |
| approval_date | date nullable | Data de aprovação esperada |
| publish_date | date nullable | Data de publicação |
| approval_location | string nullable | Onde será aprovado (ClickUp, WhatsApp, e-mail...) |
| origin | enum: onboarding/projeto/roadmap/ticket | |
| is_ticket | boolean default false | Se é chamado/urgência fora do planejamento |
| custom_fields | jsonb nullable | Valores dos campos customizados parametrizáveis |
| clickup_task_id | string nullable | Preenchido após lançamento |
| launched_at | timestamp nullable | |
| created_by | uuid (FK users) | |
| created_at / updated_at | timestamps | |

---

### `custom_field_definitions` — Campos Parametrizáveis

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | uuid (PK) | |
| entity_type | enum: task/project/macro_plan | Onde o campo aparece |
| name | string | Nome exibido |
| field_key | string | Chave no JSONB (ex: "sentimento_gestor") |
| field_type | enum: text/select/date/checkbox/number | |
| options | jsonb nullable | Array de opções para select |
| is_required | boolean default false | |
| position | int | Ordem de exibição |
| is_active | boolean default true | |
| created_at / updated_at | timestamps | |

---

## Fluxo de lançamento no ClickUp

```
1. App cria: MacroPlan → Projects → Tasks (status: draft)
2. Usuário clica "Lançar no ClickUp"
3. App monta payload JSON com todo o macroplanejamento
4. POST webhook → n8n (Tool_CreateTask ou orquestrador do Planejamento)
5. n8n cria tarefas no ClickUp com campos personalizados
6. n8n retorna clickup_task_id para cada tarefa
7. App atualiza tasks.clickup_task_id + launched_at
```

**Tickets individuais:** mesmo fluxo, mas com payload de tarefa única. Informar client_id e opcionalmente project_id.

## Relações Eloquent principais

```
Contact
  hasMany Opportunity
  belongsToMany Client (via client_contacts)

Opportunity
  belongsTo Contact
  belongsTo Client (nullable — preenchido quando ganho)

Client
  hasOne ClientOnboarding
  belongsToMany Contact (via client_contacts)
  hasMany ClientAdAccount
  hasMany ClientDiagnostic
  hasMany ClientMeetingNote
  hasMany MacroPlan
  belongsTo Opportunity (nullable)

ClientOnboarding
  belongsTo Client

ClientDiagnostic
  hasMany DiagnosticCompetitor
  hasMany DiagnosticPersona
  belongsTo Client

MacroPlan
  belongsTo Client
  belongsTo ClientMeetingNote (nullable)
  hasMany Project
  hasMany Task (tarefas isoladas do bloco4)

Project
  belongsTo MacroPlan
  belongsTo Client
  hasMany Task

Task
  belongsTo Project (nullable)
  belongsTo MacroPlan (nullable)
  belongsTo Client
```
