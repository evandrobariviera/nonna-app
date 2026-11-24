# Acompanhamento de campanhas e projetos — escopo (etapa E)

Definido com o Evandro em 24/11/2026. Ponto de partida para implementar; os itens marcados **A decidir** ainda precisam da palavra dele.

## Onde isso se encaixa (o fio condutor)

O Agente Nonna conduz o processo inteiro: **planejamento → campanhas e projetos → materiais → tarefas → acompanhamento → fechamento → próximo planejamento**. Já está no ar (23/11):

- Planejamento, campanha e projeto em documento de campos, com o agente na coluna ao lado (mesa de 3 colunas) e regras do modelo.
- Rascunho pelo agente: planejamento (reunião + plano anterior + briefing); campanha com dados reais, campanhas anteriores, pesquisa na internet e Manual Criativo.
- Plano em Aprovação → "Criar campanhas e projetos" (aviso de leitura para responsável do cliente e Gestor de Projetos).
- Peças e entregas viram tarefas (sem IA).
- Cliente lê planejamento e campanhas no link do aviso de execução e no Portal.

Falta: **E (este documento)** e **F (manuais do agente editáveis no App)**. Feito em 24/11 (1ca8b5a): rascunho do planejamento lê os números da Central (ponto de partida das metas de anúncio); agente da coluna consulta números, campanhas anteriores e pesquisa de mercado (botão em segundo plano).

## O papel: Coordenador da campanha/projeto

- **Cada campanha e cada projeto tem o seu coordenador**, apontado na própria campanha/projeto (campo novo, pessoa da equipe). **Decidido 24/11: só por campanha/projeto, sem padrão por cliente.** O Papel Funcional "Coordenador de Campanhas e Projetos" (novo) só filtra quem aparece na lista.
- É quem avalia, no período, se a campanha/projeto está andando: lê os números, a produção, os comentários e o que o cliente disse, registra a conferência e puxa o próximo passo.
- Não é o gestor de tráfego (conferência diária de anúncio na Central de Campanhas continua dele) nem o Gestor de Projetos (fila e prazos da produção).

## A rotina (sempre a mesma)

1. **Começo:** ao criar a campanha/projeto (botão do planejamento), o coordenador é apontado. O documento já diz "Conferir a cada N dias" (seção 09 da campanha; padrão 15) e a data do fechamento.
2. **A cada N dias:** o App cria para o coordenador a pendência "Conferir a campanha X" (vencimento = data da conferência). Ela fecha quando a conferência é registrada.
3. **Conferência:** formulário curto na aba **Acompanhamento** da campanha/projeto (ver abaixo). **Sem agente no preenchimento (decidido 24/11):** o Sistema mostra o que é objetivo; coordenador e cliente avaliam o qualitativo.
4. **Fechamento:** a última conferência é o fechamento (seção "Fechamento" do documento): resultado de cada número, ângulo que funcionou, o que levar para o próximo ciclo.
5. **Próximo planejamento:** o rascunho do ciclo seguinte lê as conferências e os fechamentos.

## Os três pilares da avaliação

Cada conferência junta três olhares. O App mostra os três lado a lado; a nota final é do coordenador.

| Pilar | O que entra | Como entra |
|---|---|---|
| **Sistema** (automático) | Número principal e de apoio quando "o App puxa sozinho" (Central de Performance, Central de Leads); semáforo pelo ritmo (resultado × tempo que passou); sinais de alerta disparados; produção: tarefas atrasadas, entregas no prazo, quantos ajustes o cliente pediu nas aprovações; pendências do cliente em aberto | App calcula, ninguém digita |
| **Coordenador** (equipe) | Avaliação qualitativa das metas e dos pontos de cuidado (ver formulário) | Formulário da conferência, preenchido pela pessoa (sem IA) |
| **Cliente** | Avaliação qualitativa do cliente: **a cada 15 dias**, no **Portal** (e link sem senha no WhatsApp, como o de aprovação); sinais indiretos (ajustes pedidos, tempo para aprovar) ficam no pilar Sistema | Formulário curto do cliente — formato **a definir** |

## Formulário da conferência — rascunho (A REFINAR com o Evandro)

Princípio (24/11): o centro são **as metas** (números da seção 01 da campanha / "o que melhora" do projeto) e **os pontos de cuidado** (sinais de alerta, seção 08 da campanha / 05 do projeto). O Sistema traz o objetivo; a pessoa só avalia.

**Bloco 1 — Metas** (uma linha por número do documento: principal + apoio)
- Sistema mostra: ponto de partida · quanto queremos · **valor atual** (automático quando "o App puxa sozinho"; senão o coordenador lança) · semáforo pelo ritmo.
- Coordenador: avaliação curta (vai bater / em risco / não vai bater) + comentário opcional ("por quê").

**Bloco 2 — Pontos de cuidado** (uma linha por sinal de alerta do documento)
- Sistema marca quando consegue medir sozinho (ex.: custo por resultado acima do limite).
- Coordenador: aconteceu? (sim / não) · se sim, o que fizemos (a ação combinada foi feita?).

**Bloco 3 — Andamento**
- Sistema mostra: tarefas atrasadas, entregas no prazo, ajustes pedidos pelo cliente, pendências do cliente em aberto.
- Coordenador: situação geral (no rumo / atenção / fora do rumo) · o que travou e de quem depende (equipe / cliente) · próximo passo.

**Cliente (Portal, a cada 15 dias)** — proposta: "Como você avalia o andamento desta campanha?" (nota curta) + "Algo que gostaria de ajustar?" (texto opcional). A refinar.

## O que o agente lê (depois de pronto)

- Na **coluna** de qualquer documento: conferências e fechamentos da campanha/projeto, números da Central, comentários, avaliações do cliente.
- No **rascunho do planejamento**: números da Central + conferências + fechamentos + avaliações do cliente do ciclo anterior.

## Já existe no App (reaproveitar, não duplicar)

- `CampaignDailyCheck` — conferência diária do gestor de tráfego (anúncios). Fica como está.
- `CampaignInsight` — alertas automáticos dos anúncios (entram no pilar Sistema).
- `campaign_logs` — histórico de mudanças nos anúncios.
- Comentários da campanha/projeto (`ProjectCommentController`), retorno de entrega das tarefas, rodadas de aprovação com ajustes do cliente (pilar Sistema/Cliente).
- Pendências (`PendencyService`) — usar para "Conferir a campanha X".
- Documento de campanha: `checkin_dias`, `fechamento`, seção 08 Sinais de alerta, `metrica_entrada` (automatico/informado/avaliado).
- Método de tráfego (memória `project-cerebro-campanhas`): escada das 5 perguntas, regra do volume, 3 ritmos (diário · semanal · ciclo).

## Decisões (24/11)

1. Coordenador: **por campanha/projeto**, sem padrão por cliente.
2. Formulário: centrado em **metas** e **pontos de cuidado**; rascunho acima, **ainda a refinar**.
3. **Sem agente no pré-preenchimento.** Sistema = objetivo (números, atraso, alertas medidos); coordenador e cliente = qualitativo.
4. Cliente: avalia **a cada 15 dias**, **aparece no Portal**; formato a definir.
5. **Projetos usam a mesma rotina** (no lugar dos números: "pronto quando" e "o que melhora depois").

Em aberto: refinar o formulário (coordenador e cliente) antes de construir.

## Ordem sugerida de implementação

1. Campo coordenador em campanha/projeto + Papel Funcional + apontar na criação pelo planejamento.
2. Tabela de conferências (`project_checkins`: projeto, data, situação, números, textos, alertas, nota, autor) + aba Acompanhamento com o formulário e o histórico + pilar Sistema calculado.
3. Pendência automática a cada N dias (scheduler) + fechamento como última conferência.
4. Pilar Cliente: avaliação a cada 15 dias no Portal + link.
5. Agente lendo tudo (coluna, planejamento) — junto com as sugestões 1 e 2 já combinadas. (O agente LÊ conferências e avaliações; não preenche.)
