# Business Context — Nonna Agência Digital

## O negócio

A Nonna Agência Digital é uma agência de marketing digital que atende empresas como parceiro estratégico de crescimento. Entrega tráfego pago (Meta/Google Ads), social media, sites/landing pages, SEO, e-mail marketing, automação e consultoria. Atua com modelo de fee mensal (MRR) e ciclos de planejamento de 60 a 90 dias (Macroplanejamento/Roadmap).

## Glossário

| Termo | Significado |
|-------|-------------|
| Roadmap / Macroplanejamento | Plano estratégico de 60–90 dias criado pelo Evandro para o cliente. Origem de todos os Projetos. |
| Projeto | Grande campanha ou iniciativa que nasce a partir de um Roadmap. Pai das tarefas de execução. |
| Sprint / Ciclo | A semana de produção da equipe. Tarefas executadas naquela semana. Travado toda sexta-feira pelo COO. |
| Backlog / Fila | Tarefas estruturadas, prontas, aguardando entrar em Sprint. |
| Ticket / Chamado | Pedido pontual ou urgência do cliente fora do planejamento. |
| Sistema Puxado | O executor vai buscar a próxima tarefa do topo da fila quando termina a atual. O líder não empurra. |
| Situação | Sub-status que detalha o gargalo exato da tarefa (ex: "Em Redação", "Aguardando Resposta"). |
| Bastão (Regra do) | Uma tarefa tem apenas um responsável por vez. Ao concluir sua etapa, passa o bastão ao próximo. |
| Despacho | Após reunião, o Dono da Reunião transforma as decisões em tarefas/projetos no ClickUp. |
| ATA | Registro obrigatório de cada reunião (decisões, próximos passos, pendências) — salvo na tarefa do ClickUp. |
| Fee | Mensalidade paga pelo cliente à agência. |
| Onboarding | Processo de entrada de novo cliente: setup técnico, acessos, kick-off. |
| Offboarding | Processo de saída: desconexões técnicas, jurídico, inativação no CRM. |
| NPS / CSAT / CES | Pesquisas de satisfação disparadas pelo CS a cada 90 dias. |
| COO | Alisson — Diretor de Operações, dono das Sprints e da Governança. |
| CEO / Head Estratégico | Evandro — Diretor Estratégico, cria Roadmaps, fecha negócios. |
| Head Criativo | Alessandra — aprovação final de artes e copy antes de ir ao cliente. |
| Head de Tráfego | Marlon — estratégia de mídia paga, lideranças de otimizações. |
| Head de Web / Tech | Patrick — code review, tecnologias, sites e integrações. |

## Equipe atual

| Pessoa | Função |
|--------|--------|
| Evandro | CEO / Diretor Estratégico / Head de Setup (temporário) |
| Alisson | COO / Governança / Atendimento Operacional / Backoffice |
| Marlon | Responsável Estratégico de Conta (carteira complexa) + Head Tráfego |
| Marielen | Responsável Estratégica de Conta (carteira padronizada) |
| Alessandra | Head Criativa (Design + Copy) |
| Patrick | Head de Tecnologia / Web |
| Vitor | Designer / Executor |

## Fluxo de vida de um cliente

```
Lead → Diagnóstico → Proposta → Ganho (contrato)
       ↓
   Onboarding (setup, acessos, kick-off)
       ↓
   Roadmap 60/90 dias → Projetos → Backlog → Sprint → Entrega
       ↓                                              ↓
   Macroplanejamento periódico ←── Prestação de Contas
       ↓
   Offboarding (cancelamento) → Inativo no CRM
```

## Fluxo de produção de uma tarefa (Sistema Puxado)

```
Backlog (A Fazer)
  → Em Redação/Copy (Alessandra)
  → A Fazer — Pronta para Produção (Designer pega)
  → Em Produção (Vitor/Dev)
  → Revisão Interna (Alessandra/Patrick/Marlon)
  → Aguardando Cliente — Enviar para o Cliente (Alisson)
  → Aguardando Cliente — Aguardando Resposta
  → [se aprovado] Concluído
  → [se alteração] Ajuste/Refatoração → volta para Produção
```

## Regras de negócio críticas

- **Nenhuma tarefa entra em produção sem briefing completo** — executor tem autoridade para recusar.
- **Responsável único por vez** — uma tarefa nunca tem dois executores simultâneos de setores diferentes.
- **Nunca deletar cliente ou contato** — ao cancelar contrato, muda status para Inativo. Histórico preservado.
- **Origem obrigatória** — toda tarefa deve informar se nasceu de Onboarding, Projeto, Roadmap ou Ticket.
- **Vínculo CRM obrigatório** — toda tarefa de produção deve estar linkada ao `client_task_id` do cliente.
- **ATA obrigatória** — toda reunião gera registro no campo de descrição da tarefa no ClickUp.
- **Sprint travada às sextas** — pedidos novos entram na próxima Sprint, nunca no meio da semana (exceto urgências nível vermelho).
- **Prazo de aprovação ≠ prazo de publicação** — sempre existe margem entre os dois.
- **SLA de resposta ao cliente** — 3 dias sem resposta do cliente → automação aciona o Atendimento para follow-up.

## Lei das Cores (Status visual)

| Cor | Significado |
|-----|-------------|
| ⚪ Branco/Cinza | Fila de espera, não iniciado (Backlog, A Fazer, Novo Lead) |
| 🔵 Azul | Ação interna / mão na massa (Em Produção, Ativo) |
| 🟣 Roxo | Controle de qualidade / validação interna / etapa estratégica |
| 🟠 Laranja | Dependência externa / pausado (Aguardando Cliente, Proposta Enviada) |
| 🟢 Verde | Sucesso / fim de linha positivo (Concluído, Ganho) |
| 🔴 Vermelho | Cancelamento / fim de linha negativo (Cancelado, Inativo, Perdido) |

## Serviços oferecidos

Tráfego Pago (Meta Ads, Google Ads), Social Media, Site / Landing Page, SEO, E-mail Marketing, Automação, Consultoria.

## Tipos de tarefas

1. Criação & Audiovisual
2. Setup & Tracking
3. Tráfego Pago & Performance
4. Web & Desenvolvimento
5. Estratégia & Planejamento
6. Reuniões & Atendimento
7. Administrativo & Financeiro

## Tipos de reunião

**Com cliente:** Comercial/Vendas, Boas-Vindas (Onboarding), Kick-off Estratégico, Macroplanejamento Periódico, Alinhamento de Projeto, Captação de Material.

**Interna:** Sprint Planning (semanal, mais importante), Sync de Qualidade/Code Review, Alinhamento Estratégico Interno.

## Integrações do ecossistema

- **ClickUp** → gestão operacional completa (tarefas, reuniões, CRM, contratos, Sprints)
- **PostgreSQL (Hetzner)** → espelho do ClickUp + dados de tráfego + RAG + gamificação
- **n8n** → orquestração, sync ClickUp→Postgres, automações, agentes de IA, webhooks
- **Meta Ads / Google Ads** → fonte de dados de `traffic_metrics_daily`
- **Google Drive** → arquivos e materiais de clientes
- **WhatsApp** → comunicação diária com clientes (via grupos por cliente)
- **pgvector (OpenAI)** → RAG de knowledge base dos clientes para agentes de IA
- **Looker Studio** → dashboards de resultados para clientes
