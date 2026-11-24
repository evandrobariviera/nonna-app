# Acompanhamento de campanhas e projetos — escopo (etapa E)

Definido com o Evandro em 24/11/2026. Ponto de partida para implementar; os itens marcados **A decidir** ainda precisam da palavra dele.

## Onde isso se encaixa (o fio condutor)

O Agente Nonna conduz o processo inteiro: **planejamento → campanhas e projetos → materiais → tarefas → acompanhamento → fechamento → próximo planejamento**. Já está no ar (23/11):

- Planejamento, campanha e projeto em documento de campos, com o agente na coluna ao lado (mesa de 3 colunas) e regras do modelo.
- Rascunho pelo agente: planejamento (reunião + plano anterior + briefing); campanha com dados reais, campanhas anteriores, pesquisa na internet e Manual Criativo.
- Plano em Aprovação → "Criar campanhas e projetos" (aviso de leitura para responsável do cliente e Gestor de Projetos).
- Peças e entregas viram tarefas (sem IA).
- Cliente lê planejamento e campanhas no link do aviso de execução e no Portal.

Falta: **E (este documento)** e **F (manuais do agente editáveis no App)**. Também combinado e não feito: (1) rascunho do planejamento lendo os números da Central de Performance (ponto de partida das metas); (2) agente da coluna com ferramentas (números, pesquisa, campanhas anteriores).

## O papel: Coordenador da campanha/projeto

- **Cada campanha e cada projeto tem o seu coordenador**, apontado na própria campanha/projeto (campo novo, pessoa da equipe). Pode ser sugerido pelo Papel Funcional "Coordenador de Campanhas e Projetos" (novo) e trocado à mão.
- É quem avalia, no período, se a campanha/projeto está andando: lê os números, a produção, os comentários e o que o cliente disse, registra a conferência e puxa o próximo passo.
- Não é o gestor de tráfego (conferência diária de anúncio na Central de Campanhas continua dele) nem o Gestor de Projetos (fila e prazos da produção).

## A rotina (sempre a mesma)

1. **Começo:** ao criar a campanha/projeto (botão do planejamento), o coordenador é apontado. O documento já diz "Conferir a cada N dias" (seção 09 da campanha; padrão 15) e a data do fechamento.
2. **A cada N dias:** o App cria para o coordenador a pendência "Conferir a campanha X" (vencimento = data da conferência). Ela fecha quando a conferência é registrada.
3. **Conferência:** formulário curto na aba **Acompanhamento** da campanha/projeto (ver abaixo). O agente pode pré-preencher.
4. **Fechamento:** a última conferência é o fechamento (seção "Fechamento" do documento): resultado de cada número, ângulo que funcionou, o que levar para o próximo ciclo.
5. **Próximo planejamento:** o rascunho do ciclo seguinte lê as conferências e os fechamentos.

## Os três pilares da avaliação

Cada conferência junta três olhares. O App mostra os três lado a lado; a nota final é do coordenador.

| Pilar | O que entra | Como entra |
|---|---|---|
| **Sistema** (automático) | Número principal e de apoio quando "o App puxa sozinho" (Central de Performance, Central de Leads); semáforo pelo ritmo (resultado × tempo que passou); sinais de alerta disparados; produção: tarefas atrasadas, entregas no prazo, quantos ajustes o cliente pediu nas aprovações; pendências do cliente em aberto | App calcula, ninguém digita |
| **Coordenador** (equipe) | Números "a equipe lança"; situação (no rumo / atenção / fora do rumo); o que está funcionando; o que travou e de quem depende (equipe ou cliente); alertas que aconteceram; próximo passo e quem faz; nota | Formulário da conferência; agente pode pré-preencher a partir do Sistema + comentários |
| **Cliente** | Percepção do cliente sobre a campanha/projeto: nota curta + comentário opcional (link no WhatsApp ou Portal, sem senha, como o de aprovação); sinais indiretos: ajustes pedidos nas peças, tempo para aprovar, reclamações | **A decidir:** frequência (a cada conferência? só no meio e no fechamento?) e formato (nota 1-5? "está satisfeito com o andamento?") |

## O que o agente lê (depois de pronto)

- Na **coluna** de qualquer documento: conferências e fechamentos da campanha/projeto, números da Central, comentários, avaliações do cliente.
- No **rascunho da conferência**: tudo do pilar Sistema + comentários da campanha + retornos de entrega + ajustes do cliente desde a última conferência.
- No **rascunho do planejamento**: números da Central + conferências + fechamentos + avaliações do cliente do ciclo anterior.

## Já existe no App (reaproveitar, não duplicar)

- `CampaignDailyCheck` — conferência diária do gestor de tráfego (anúncios). Fica como está.
- `CampaignInsight` — alertas automáticos dos anúncios (entram no pilar Sistema).
- `campaign_logs` — histórico de mudanças nos anúncios.
- Comentários da campanha/projeto (`ProjectCommentController`), retorno de entrega das tarefas, rodadas de aprovação com ajustes do cliente (pilar Sistema/Cliente).
- Pendências (`PendencyService`) — usar para "Conferir a campanha X".
- Documento de campanha: `checkin_dias`, `fechamento`, seção 08 Sinais de alerta, `metrica_entrada` (automatico/informado/avaliado).
- Método de tráfego (memória `project-cerebro-campanhas`): escada das 5 perguntas, regra do volume, 3 ritmos (diário · semanal · ciclo).

## A decidir com o Evandro

1. Coordenador: campo na campanha/projeto apenas, ou também padrão por cliente?
2. Campos do formulário da conferência (proposta: os 6 do pilar Coordenador acima).
3. Agente pré-preenche toda conferência (custo ~30-50 créditos, na conta de quem confere) ou só quando pedirem?
4. Pilar Cliente: frequência e formato da avaliação; aparece no Portal?
5. Projeto (não campanha) usa a mesma rotina? Proposta: sim, com "pronto quando" e "o que melhora depois" no lugar dos números.

## Ordem sugerida de implementação

1. Campo coordenador em campanha/projeto + Papel Funcional + apontar na criação pelo planejamento.
2. Tabela de conferências (`project_checkins`: projeto, data, situação, números, textos, alertas, nota, autor) + aba Acompanhamento com o formulário e o histórico + pilar Sistema calculado.
3. Pendência automática a cada N dias (scheduler) + fechamento como última conferência.
4. Rascunho da conferência pelo agente.
5. Pilar Cliente (link de avaliação).
6. Agente lendo tudo (coluna, planejamento) — junto com as sugestões 1 e 2 já combinadas.
