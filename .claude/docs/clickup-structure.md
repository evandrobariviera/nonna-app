# ClickUp Structure — Nonna Agência Digital

## Arquitetura: Plana (sem pastas por cliente)

A Nonna usa **Arquitetura Plana** — as tarefas são organizadas por natureza do trabalho e fluxo de vida, não por cliente. O vínculo com o cliente é feito exclusivamente pelo campo relacional `Cliente` (linked task para `###Clientes`), que mapeia para `client_task_id` no PostgreSQL.

## Estrutura Oficial

```
#CRM
  ##Cadastros
    ###Contatos        ← pessoas físicas (decisores, gerentes, parceiros)
    ###Clientes        ← empresas/CNPJs — FONTE DO client_task_id
  ##Comercial
    ###Leads e Oportunidades   ← funil de vendas
    ###Contratos (Jurídico)    ← vigências, renovações, distratos

#OPERACIONAL
  ##Atendimento
    ###Agenda          ← reuniões, eventos, captações
    ###Chamados (Tickets) ← urgências e pedidos pontuais do cliente
  ##Gestão de Clientes
    ###Entrada (Onboarding)    ← setup técnico, acessos, kick-off
    ###Sucesso do Cliente (CS) ← NPS, CSAT, CES, pesquisas
    ###Saída (Offboarding)     ← desligamento técnico e jurídico
  ##Fluxo
    ###Planejamentos (Roadmaps) ← macroplanejamento 60–90 dias
    ###Projetos (Projects)      ← grandes campanhas/iniciativas
    ###Filas (Backlog)          ← tarefas prontas aguardando Sprint
    ###Ciclos (Sprints)         ← execução semanal da equipe
  ##Rotina
    ###Campanhas        ← painel de controle de tráfego pago ativo
    ###Orçamentos       ← controle de saldo e verba de Ads
    ###Prestação de Contas ← relatórios e dashboards de resultados
    ###Suporte/Manutenção ← ajustes recorrentes e pequenas correções
```

## Campos Personalizados (Custom Fields)

### Rastreabilidade
| Campo | Tipo | Opções |
|-------|------|--------|
| Cliente | Relationship | Link para ###Clientes (→ `client_task_id`) |
| Origem | Dropdown | Onboarding, Projeto, Roadmap, Ticket |

### Execução
| Campo | Tipo | Opções |
|-------|------|--------|
| Tipo de Tarefa | Dropdown | Criação/Audiovisual, Setup/Tracking, Tráfego/Performance, Web/Dev, Estratégia, Reuniões/Atendimento, Administrativo |
| Situação | Dropdown | ver lista abaixo |

### Prazos (SLA)
| Campo | Tipo | Descrição |
|-------|------|-----------|
| Data de Aprovação | Date | Limite para revisão interna pelo Head |
| Data de Publicação / Go-Live | Date (Due Date) | Dia real de publicação/entrega |

### Comunicação Interna
| Campo | Tipo | Opções |
|-------|------|--------|
| Destino | Dropdown | Cliente Final, Tráfego Pago, Desenvolvimento Web, Produção de Conteúdo, Diretoria |

## Situações (Sub-status)

| Cor | Situação | Quando usar |
|-----|----------|-------------|
| ⚪ | Aguardando Copy/Redação | A Fazer — designer ainda não pode puxar |
| ⚪ | Pronta para Produção | A Fazer — redação concluída, designer pode puxar |
| 🔵 | Em Redação | Em Produção — texto sendo escrito |
| 🔵 | Em Design | Em Produção — arte sendo criada |
| 🔵 | Em Setup/Dev | Em Produção — código ou configuração |
| 🔵 | Agendado na Plataforma | Concluído mas publicação futura |
| 🟠 | Pendente de Informação | Bloqueado — falta senha, verba ou material do cliente |
| 🟠 | Enviar para o Cliente | Aguardando Cliente — equipe terminou, atendimento precisa enviar |
| 🟠 | Aguardando Resposta | Aguardando Cliente — material enviado, esperando "OK" |
| 🔴 | Alteração do Cliente | Ajuste/Refatoração — cliente pediu mudança |
| 🔴 | Alteração Interna | Ajuste/Refatoração — Head reprovou antes de ir ao cliente |
| 🔴 | Atraso Sinalizado | Prazo vai estourar — aciona COO |

## Fluxos de Status por Lista

### Produção (Backlog, Sprints, Rotina)
`⚪ A Fazer` → `🔵 Em Redação/Copy` → `⚪ A Fazer (Pronta)` → `🔵 Em Produção` → `🟣 Revisão Interna` → `🟠 Aguardando Cliente` → `🟢 Concluído` / `🔴 Cancelado`

### CRM — Clientes e Contatos
`🟢 Ativo` | `🔴 Inativo` (nunca deletar — apenas inativar)

### Comercial — Leads
`⚪ Novo Lead` → `🔵 Contato Inicial/Qualificação` → `🟣 Diagnóstico/Reunião` → `🟠 Proposta Enviada` → `🔵 Em Negociação` → `🟢 Ganho` / `🔴 Perdido/Desqualificado`

### Onboarding
`⚪ Novo Cliente` → `🔵 Setup Interno` → `🟠 Aguardando Acessos` → `🟣 Kick-off Agendado` → `🟢 Onboarding Concluído`

### Projetos e Roadmaps
`⚪ Em Planejamento` → `🟠 Aguardando Aprovação` → `🔵 Ativo/Em Execução` → `🟢 Entregue/Finalizado`

### Agenda (Reuniões)
`⚪ A Agendar` → `🔵 Agendada` → `🟣 Em Despacho/Pós-Reunião` → `🟢 Realizada` / `🔴 Cancelada`

### Chamados (Tickets)
`⚪ Triagem` → `🔵 Em Atendimento/Análise` → `🟠 Aguardando Resposta` → `🟢 Resolvido`

### Campanhas de Tráfego
`⚪ Em Configuração/Setup` → `🔵 Monitoramento/Aprendizado` → `🟢 Ativa/Otimizada` | `🟠 Otimização Necessária` | `🔴 Pausada`

### Orçamentos de Ads
`🟢 Saldo Ativo/OK` → `🟠 Adição Necessária` → `🔴 Aguardando Pagamento (Bloqueado)`

### Contratos
`⚪ Em Elaboração` → `🟠 Em Processo de Assinatura` → `🟢 Vigente` → `🔵 Em Processo de Renovação` / `🔴 Vencido/Distrato`

### Offboarding
`⚪ Aviso de Cancelamento` → `🔵 Desligamento Técnico` → `🟣 Fechamento Jurídico/Financeiro` → `🟠 Aguardando Cliente` → `🟢 Offboarding Concluído`

## Automações chave (n8n/ClickUp)

| Gatilho | Ação |
|---------|------|
| Situação → Pronta para Produção | Remove redator, atribui designer, notifica |
| Status → Revisão Interna | Atribui Head do setor, notifica para aprovação técnica |
| Head aprova → Status Aguardando Cliente | Atribui Alisson (atendimento), Situação → Enviar para o Cliente |
| Status → Ajuste + Situação Alteração do Cliente | Volta ao executor original com alta prioridade |
| Status Concluído + Destino = Setor Interno | Comenta marcando equipe do setor destino |
| Due Date vencida + Status ≠ Concluído | Situação → Atraso Sinalizado, Flag urgente, marca Alisson |
| Aguardando Cliente há +3 dias | Comenta marcando atendimento para follow-up |
| Lead → Ganho | Ativa cliente no CRM, cria tarefa de Onboarding |
| Projeto Onboarding → Concluído | Marca cliente como Ativo |
| Contrato vence em 30 dias | Status → Em Renovação, notifica Evandro |
| Cliente 90 dias ativo | Cria tarefa de NPS no CS |
| Orçamento → Adição Necessária | Aciona atendimento para cobrar recarga |

## Requisitos obrigatórios de uma tarefa (checklist de qualidade)

1. Título padronizado e específico
2. Cliente Relacionado (link para ###Clientes)
3. Origem da Tarefa (Onboarding / Projeto / Roadmap / Ticket)
4. Projeto Relacionado (se origem for Projeto ou Roadmap)
5. Tipo da Tarefa
6. Situação Atual
7. Objeto da tarefa (o que será feito)
8. Contexto da tarefa (por que existe)
9. Objetivo da tarefa (resultado esperado)
10. Responsável único pela execução
11. Prazos: Início, Aprovação, Go-Live, Vencimento final
12. Materiais necessários disponíveis
13. Critério de conclusão definido

**Regra:** Qualquer executor pode recusar iniciar uma tarefa que não atenda esses 13 pontos.
