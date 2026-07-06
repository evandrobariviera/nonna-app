# Nonna App

## O que é

O **Nonna App** é o "Sistema Operacional" da Nonna Agência Digital. Centraliza diagnósticos estratégicos, macroplanejamentos, projetos e tarefas — e os lança operacionalmente no ClickUp via n8n. Resolve o gargalo entre planejamento e execução.

## Usuários

- **Equipe Interna:** Estrategistas (Diagnóstico, Macroplanejamento), Gestores de Projetos (Fatiamento), Heads (Qualidade)
- **Clientes:** Portal de acompanhamento de demandas e relatórios (futuro)

## Stack

- **Backend:** Laravel 13.8 (PHP 8.2+)
- **Frontend:** Blade + Tailwind CSS + Alpine.js
- **Build:** Vite
- **Banco local:** SQLite (desenvolvimento)
- **Banco remoto:** PostgreSQL (Hetzner) — Fonte da verdade
- **Autenticação:** Laravel Breeze
- **Automação/orquestração (quando fizer sentido):** n8n (ClickUp, fluxos que se beneficiam de orquestração visual)

## Arquitetura de integrações

**Atualizado (2026-07):** o App pode integrar diretamente com APIs externas quando fizer sentido (ex: Meta Ads, Google Ads) — não é mais regra obrigatória passar tudo pelo n8n. Credenciais de integração ficam guardadas criptografadas em `organization_integrations`; agendamento de sincronizações roda pelo Laravel Scheduler (processo `scheduler` do supervisord).

O n8n continua disponível e é usado (a) pra automações que já funcionam bem assim (ex: ClickUp), e (b) como gatilho/agendador externo quando fizer sentido acionar o App de fora — o App expõe endpoints de API (`routes/api.php`, autenticados por token Sanctum por Organização) exatamente pra isso, "portas abertas" pra ferramentas externas se comunicarem com o App quando necessário.

Fluxo ClickUp (mantido como está):
```
App (fonte da verdade)
        ↓
   PostgreSQL       ← fonte da verdade de tudo (diagnósticos, planos, projetos, tarefas)
        ↓
       n8n          ← orquestração do lançamento/sync com o ClickUp
        ↓
    ClickUp         ← camada operacional de tarefas (espelho do que o App lança)
```

**Regra de ouro (ClickUp):** O App escreve primeiro no banco. O n8n lê o banco e sincroniza com o ClickUp. O ClickUp **não** é fonte da verdade — é camada de execução.

**Integrações de mídia/dados (Meta Ads, Google Ads, etc.):** o App chama essas APIs diretamente, sem n8n no meio, usando as credenciais de `organization_integrations`.

## Pipeline CRM completo

O App é o CRM central da agência. O funil vai de contato até o offboarding:

```
Contato (contacts)                → lead chega aqui (pessoa física)
    ↓
Oportunidade (opportunities)      → Kanban comercial (7 estágios)
    ↓ quando "Ganho"
Cliente criado (clients)          → vinculado ao contato; n8n dispara automações
    ↓
Onboarding (client_onboardings)   → 5 fases do manual operacional
    ↓ Fase 5 concluída
Ativo → Ciclos de Macroplanejamento
    ↓ a cada 90 dias
CS Survey (cs_surveys)            → NPS/CSAT/CES via link tokenizado (futuro)
    ↓ se cancelar
Offboarding (client_offboardings) → 5 etapas de saída (futuro)
```

**Estágios do Kanban de Oportunidades:**
`novo_lead` → `qualificacao` → `diagnostico_reuniao` → `proposta_enviada` → `negociando` → `ganho` → `perdido`

**Onboarding — 5 fases fixas:**
- Fase 1 (Dia 0): automações — WhatsApp boas-vindas, link de cadastro, contrato, Drive, grupo WA
- Fase 2 (Dias 1-2): welcome call, regras, coleta de acessos, contrato, manual, agenda kick-off
- Fase 3 (Dias 3-4): setup técnico — BM audit, Pixel, GTM, GA4, API Conversões, Looker Studio
- Fase 4 (Dia 5): kick-off estratégico — imersão no negócio (Evandro + dono da conta)
- Fase 5: macroplanejamento → projetos → backlog → sprint → cliente vira Ativo

## Hierarquia de dados do negócio

```
Contato (contacts)                → pessoa física (leads, prospects, pontos de contato)
    ↓ Oportunidade Ganha
Cliente (clients)
├── Contatos vinculados   → junction client_contacts (contacts ↔ clients, muitos-para-muitos)
├── Contas de Anúncios    → client_ad_accounts (plataforma + account_id + status)
├── Onboarding            → client_onboardings (5 fases, checklist por fase)
├── Diagnósticos          → client_diagnostics (versionado, base estratégica)
│   ├── Concorrentes      → diagnostic_competitors (ilimitado por diagnóstico)
│   └── Personas          → diagnostic_personas (ilimitado por diagnóstico)
├── Atas de Reuniões      → client_meeting_notes (texto corrido → IA processa futuramente)
└── Macroplanejamentos    → macro_plans (ciclos de 60-90 dias)
    └── Projetos          → projects (multidisciplinares, origem do Macro)
        └── Tarefas       → tasks (criadas no App → lançadas no ClickUp via n8n)

Tarefas avulsas (tickets) → tasks (project_id null, is_ticket = true)
Campos parametrizáveis    → custom_field_definitions (como o ClickUp faz)
```

## Fluxo de lançamento no ClickUp

```
1. Estrategista cria Macro → Projetos → Tarefas no App
2. Clica "Lançar no ClickUp" (lote) → webhook para n8n com payload completo
3. n8n cria tarefas no ClickUp → retorna clickup_task_id → App atualiza tasks.clickup_task_id
4. Tarefas individuais (tickets) também podem ser lançadas avulsamente
```

## Interface do Cliente — TABS

```
[Geral] [Contatos] [Contas de Anúncios] [Diagnósticos] [Atas] [Briefing] [Planejamentos]
```

- **Briefing** = panorama read-only condensado do diagnóstico + observações futuras (base para RAG)
- **Planejamentos** = lista de macroplanejamentos do cliente (link para hierarquia Macro → Projetos → Tarefas)

## Schema das tabelas internas do App (PostgreSQL)

Ver [.claude/docs/architecture.md](.claude/docs/architecture.md) para schema completo.

## Contexto detalhado

- [.claude/docs/architecture.md](.claude/docs/architecture.md) — arquitetura interna do App: tabelas, campos, fluxos
- [.claude/docs/database-schema.md](.claude/docs/database-schema.md) — schema PostgreSQL completo dos 4 ecossistemas ClickUp/IA/Tráfego/Gamificação
- [.claude/docs/business-context.md](.claude/docs/business-context.md) — glossário, equipe, regras de negócio, lei das cores, fluxos operacionais
- [.claude/docs/campaign-sync-api.md](.claude/docs/campaign-sync-api.md) — contrato da API que o n8n usa para sincronizar campanhas/métricas de anúncios (Meta/Google Ads) com o App
- [.claude/docs/templates/](.claude/docs/templates/) — HTMLs de diagnóstico, planejamento e apresentação (referência de UX/campos)

## Convenções

- **Controllers finos:** Lógica em Services ou Actions
- **Eloquent Models:** Mapear tabelas com Migrations (tabelas internas do App)
- **Master Key:** Usar `client_task_id` para relacionamentos com ecossistema ClickUp; usar `client_id` (UUID) para relacionamentos internos do App
- **UI:** Paleta Roxo (`#6A5ACD`) e Laranja (`#FF8C00`) — variáveis CSS `var(--purple)` e `var(--orange)`
- **Alpine.js:** Para interatividade de TABS, campos dinâmicos (adicionar concorrentes/personas), e estados de UI
- **JSONB:** Para seções de texto livre do diagnóstico, briefing multidisciplinar de projetos, campos processados de atas

## O que NÃO fazer

- Não presumir que toda integração externa precisa passar pelo n8n — o App pode integrar diretamente com APIs externas quando fizer sentido (ex: Meta/Google Ads). O n8n continua valendo pra automações que já funcionam bem assim (ex: ClickUp) ou quando orquestração visual/cross-tool realmente ajuda
- Não usar Livewire ou Inertia — o projeto usa Blade puro
- Não deletar clientes ou contatos — usar status `inactive`
- Não buscar dados do ClickUp diretamente — consultar tabelas `clickup_*` do PostgreSQL

## Funcionalidades — roadmap

### Fase 0 — CRM Pipeline (em andamento)
- [ ] `contacts` — cadastro de leads/contatos (topo do funil)
- [ ] `opportunities` — Kanban comercial (7 estágios)
- [ ] `client_onboardings` — 5 fases do manual operacional

### Fase 1 — CRM Base (em andamento)
- [x] Cadastro 360° de Clientes (`clients`)
- [x] Cadastro público via link tokenizado
- [x] TABS na página do cliente (Alpine.js, x-cloak)
- [ ] Contatos vinculados a cliente (`client_contacts` junction)
- [ ] Cadastro de Contas de Anúncios (`client_ad_accounts`)

### Fase 2 — Inteligência Estratégica
- [ ] Diagnóstico multi-seções com concorrentes e personas ilimitados
- [ ] Atas de Reuniões (texto corrido, IA processa futuramente)
- [ ] Briefing/Panorama do cliente (tab read-only)

### Fase 3 — Planejamento e Execução
- [ ] Macroplanejamento (5 blocos + projetos vinculados)
- [ ] Projetos multidisciplinares com briefing por Head
- [ ] Tarefas com campos parametrizáveis (`custom_field_definitions`)
- [ ] Lançamento em lote no ClickUp via n8n (webhook)
- [ ] Lançamento de tickets individuais

### Fase 4 — Portal e Automações
- [ ] Portal do Cliente com métricas de Tráfego
- [ ] Integração com IA para processamento de atas
- [ ] RAG automático alimentado pelo diagnóstico
