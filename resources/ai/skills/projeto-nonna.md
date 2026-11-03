---
name: "projeto-nonna"
description: "Use esta skill quando o usuário quiser APROFUNDAR, ORGANIZAR ou DETALHAR um PROJETO específico — um entregável que fica quando o trabalho termina (site, CRM, automação, dossiê, consultoria, sistema, integração). Ative com menções como \"aprofunda esse projeto\", \"detalha o projeto do site\", \"monta o escopo do CRM\", \"organiza esse projeto pra mim\" ou variações. Recebe UM projeto por vez (do planejamento ou avulso) e o transforma em escopo estruturado — objetivo, racional, escopo, desdobramento por disciplina, cronograma, tarefas e entregáveis. Entrega UM documento HTML próprio do projeto (visual claro do Nonna App), que é anexado na página do projeto no Nonna App e lido pela IA de tarefas. NÃO use para montar o macroplanejamento (essa é a `planejamento-nonna`), NÃO use para campanhas (essa é a `campanha-nonna`).\n"
---

# Skill: Projeto Nonna — Estrutura & Escopo de Projeto

## O que esta skill faz

Recebe **um projeto por vez** — vindo do planejamento do ciclo ou descrito avulso — e o
organiza até o ponto em que o time consegue **executar** e o cliente consegue **acompanhar**:
objetivo aprofundado, racional (por que importa agora), escopo, desdobramento por disciplina,
cronograma, tarefas e entregáveis.

Projeto **não é padronizável**. Um site, uma implantação de CRM, uma automação e um dossiê têm
naturezas diferentes. Esta skill usa uma **estrutura-base mínima obrigatória** e deixa o corpo
livre, guiado pela natureza real do projeto — nunca força um molde único.

**Entregável (v2, out/2026): um HTML próprio do projeto.** Não existe mais "injeção" no
documento do planejamento. Esse HTML é anexado na página do projeto no Nonna App, aparece lá
para a equipe e é lido pela IA que cria e organiza as tarefas — por isso **cronograma, tarefas
e datas** precisam estar claros no documento.

---

## Nota de evolução (leia antes de aprofundar)

Esta skill nasce **minimalista de propósito**: estrutura-base sólida e genérica, ainda sem lógica
especializada por tipo de projeto (checklist técnico de site, metodologia de CRM...). Isso vai
amadurecer aos poucos, conforme os projetos forem executados.

**Por enquanto:**
- A lista de tarefas **não é inferida automaticamente** — peça ao usuário (com responsável por
  setor, se possível). Não invente tarefas técnicas sem repertório real para prever.
- O desdobramento por disciplina fica na camada de **comunicação/organização** (o que cada setor
  precisa saber e fazer), não no técnico profundo. Se o usuário der o técnico, registre; não
  invente decisões de arquitetura.

---

## Fronteiras — o que esta skill NÃO faz

- **Não monta planejamento do ciclo.** Isso é a `planejamento-nonna`. Aqui é UM projeto.
- **Não aprofunda campanhas.** Esforço de comunicação com início/fim é a `campanha-nonna`.
- **Não inventa tarefas técnicas** que o usuário não validou. Pergunta e registra.
- **Não inventa dados de negócio.** Prazos, orçamento e restrições vêm do usuário; sem eles,
  infere com clareza e marca como hipótese a confirmar.

---

## Arquivos de referência

Antes de gerar, leia:
- `assets/template-visual.md` — visual claro do Nonna App (tokens, componentes, esqueleto)
- `assets/exemplo-estrutura.md` — exemplo de projeto organizado no nível de detalhe esperado

---

## Fluxo de execução — siga esta ordem sempre

### ETAPA 1 — Captura do projeto

Identifique se o projeto veio de:
- **O planejamento do ciclo** → use o que já está definido (nome, objetivo, início, término, entregas).
- **Uma descrição avulsa** → colete o essencial antes de organizar.

Extraia internamente:
- Nome e tipo (site, CRM, automação, dossiê, consultoria, sistema, outro)
- Cliente e segmento
- Objetivo declarado
- Disciplinas envolvidas (web, tráfego/setup, criação, estratégia, relacionamento/CS...)
- **Datas de início e término** (ou prazo estimado) e marcos
- Restrições (orçamento, obrigatoriedades, dependências externas)

### ETAPA 2 — Perguntas de maturação (máx. 3 por rodada)

Só pergunte o que for **crítico e não inferível**. Priorize:

1. **Objetivo de negócio real** — "O que muda de verdade pro cliente quando esse projeto terminar?"
2. **Prazos e marcos** — "Tem data de início e término? Algum marco intermediário (aprovação de
   layout, homologação, treinamento)?"
3. **Restrições inegociáveis** — "Algo que o cliente NÃO quer, dependência externa (acesso,
   fornecedor, aprovação) ou orçamento limitado?"

Depois do núcleo validado, **sempre peça a lista de tarefas**:
4. "Me passa a lista de tarefas desse projeto — pode ser bruta, eu organizo por disciplina."

Nunca mais de 3 perguntas por rodada. Nunca repita o que já foi respondido.

### ETAPA 3 — Estruturação do projeto (o coração da skill)

Escreva com convicção e clareza — nada de bullet vazio ou frase genérica.

**1. Objetivo & Racional** — o objetivo aprofundado em 2–3 frases; o racional: por que importa
AGORA (o gatilho de negócio).

**2. Escopo — corpo livre guiado pela natureza do projeto.** Não force seção que não serve:
- **Site/LP:** jornada do usuário, arquitetura de páginas, funcionalidades-chave.
- **CRM/Automação:** fluxo do processo, gatilhos, integrações (como o usuário descreveu).
- **Dossiê/Consultoria:** arquitetura de conteúdo, etapas de investigação, entregável final.
- **Outro:** o eixo que fizer sentido para aquele projeto.

**3. Desdobramento por Disciplina** (só as envolvidas) — o que cada uma precisa saber e fazer,
em prosa corrida.

**4. Cronograma** — início, término e marcos intermediários em ordem cronológica, com data.

**5. Tarefas** — a lista fornecida pelo usuário, por disciplina/responsável, no infinitivo
("Configurar...", "Homologar..."), com **prazo** quando houver. Sem lista: pendência explícita,
nunca inventada.

**6. Entregáveis** — os entregáveis concretos, uma linha de direcionamento cada.

### ETAPA 4 — Validação antes de gerar

Apresente um resumo em texto das seções 1–2 e do cronograma. Ajuste conforme o retorno. **Não
gere HTML antes de o escopo e as datas estarem aprovados.**

### ETAPA 5 — Geração do documento do projeto

Gere **um** arquivo `projeto_[cliente]_[nome-projeto].html`, no visual claro de
`assets/template-visual.md`:

- Rolagem contínua, sem menu lateral, `max-width` ~860px, funcionando no celular.
- **Ficha no topo**: cliente · projeto · tipo · início · término · planejamento de origem.
- Depois as seções 1 a 6, nesta ordem. Cronograma como linha do tempo; tarefas agrupadas por disciplina.
- No fim do `<body>`, o **bloco de dados** (o Nonna App lê só isto):

```html
<script type="application/json" id="nonna-dados">
{
  "contrato": 2,
  "tipo": "projeto",
  "cliente": "Casa Rigotti",
  "nome": "Site Casa Rigotti",
  "inicio": "2026-10-01",
  "fim": "2026-10-30",
  "marcos": [
    { "nome": "Aprovação do layout", "data": "2026-10-14" }
  ],
  "tarefas": [
    { "titulo": "Desenvolver estrutura das 3 páginas", "area": "web", "prazo": "2026-10-23" }
  ]
}
</script>
```

Regras do bloco: JSON válido; datas `AAAA-MM-DD` ou `null`; `area` = criacao, video, web,
trafego, setup, social, seo, email, estrategia; `tarefas` = as mesmas da seção 5.

Ao entregar, lembre em uma linha: "Anexe este HTML na página do projeto no Nonna App — ele vira
o documento principal e o Assistente de Tarefas passa a usar."

---

## Regras de qualidade — verificar antes de entregar

- [ ] Objetivo aprofundado E racional (por que agora), não repetição do resumo
- [ ] Escopo reflete a natureza real do projeto — não molde forçado
- [ ] Disciplinas envolvidas com desdobramento de comunicação/organização
- [ ] Cronograma com início, término e marcos (quando houver)
- [ ] Tarefas vieram do usuário — nenhuma tarefa técnica inventada
- [ ] Entregáveis concretos
- [ ] Ficha no topo; bloco `nonna-dados` válido e igual às seções 4–5
- [ ] Nenhuma seção vazia; sem tarefas = pendência explícita, nunca omitida
- [ ] Cada decisão vem com o motivo, um raciocínio por parágrafo (passa no teste de clareza)

---

## Tom e linguagem

- Direto, profissional, sem rodeios
- Tarefas no infinitivo
- Racionais e escopo em prosa corrida
- pt-BR sempre
- O cliente também lê: compreensível por quem não é da área
- Campos vazios viram pendência clara

### Clareza e ligação entre as ideias (regra obrigatória)

O documento é lido por cliente e equipe, inclusive por quem não estava na reunião. Cada ideia
precisa estar ligada à anterior e trazer o próprio motivo.

- **Um raciocínio por parágrafo, em sequência.** Ordem natural: o que temos → o que decidimos →
  por quê → como vamos medir.
- **Toda decisão vem com a causa**, na mesma frase ou na seguinte. Ex.: "As famílias fecham a
  reserva à noite. Por isso, os anúncios ficam mais fortes nesse horário."
- **Conectores explícitos** ("por isso", "como", "assim", "a partir de", "para"). Não comprima
  várias ideias em listas com dois-pontos e vírgulas.
- **Uma decisão por frase.** Se a frase carrega duas decisões, quebre em duas.
- **Tempo em ordem.** Fases e datas ganham uma frase por momento: "Em novembro... A partir do fim
  de novembro... Em janeiro..."
- **Pilares, mensagens e públicos começam por quem recebem e terminam no problema que resolvem.**
  Ex.: "Para quem mora na região. Dá para decidir em cima da hora, olhando a previsão do dia. É a
  resposta ao medo de chuva que fez cancelar reservas."
- **Palavras comuns.** Nada de expressão interna ("processos redondos") nem termo técnico sem
  tradução.

**Antes:** "Em vez de abrir uma campanha para cada oferta, cada marca troca os anúncios conforme o
momento: dezembro e grupos em novembro, Réveillon no fim de novembro e em dezembro, grupos 2027
em janeiro."

**Depois:** "Como o mesmo público pode se interessar por qualquer uma das ofertas, não vale abrir
uma campanha para cada. Vamos manter uma campanha por marca e trocar os anúncios de acordo com o
calendário. Em novembro, os anúncios falam da primeira quinzena de dezembro e dos grupos. A partir
do fim de novembro, o Réveillon ganha espaço. Em janeiro, o foco passa a ser os grupos de 2027."

**Teste antes de entregar:** quem não estava na reunião entende o que foi decidido e por quê,
lendo cada parágrafo uma vez só?

## O que NÃO fazer

- Não gerar HTML antes de validar o escopo e as datas
- Não injetar nada no documento do planejamento — o projeto tem documento próprio
- Não perguntar mais de 3 coisas por vez
- Não inventar tarefas técnicas — sempre pedir ao usuário
- Não forçar estrutura de campanha (big idea, mecânica) em um projeto
- Não tratar mais de um projeto por execução
- Não inventar prazos, orçamento ou restrições — inferir e marcar como hipótese
- Não usar placeholder no HTML final


---

# Arquivo de referência: assets/exemplo-estrutura.md

# Exemplo — Projeto Estruturado (calibração de profundidade)

Este exemplo usa um projeto fictício de "Implementação de CRM" para mostrar o NÍVEL DE
DETALHE esperado quando a skill estrutura um projeto. Não copie o conteúdo — copie a
profundidade e a lógica de raciocínio. Note que a estrutura é mais enxuta e mais operacional
que a de campanha — projeto não tem big idea nem mecânica, tem escopo e execução.

---

## 00 — Objetivo & Racional

**Objetivo:** Implementar um CRM dedicado para a rede de franquias, centralizando o
histórico de atendimento de cada cliente e permitindo que a matriz acompanhe indicadores de
relacionamento por unidade.

**Racional:** Hoje o atendimento acontece disperso entre WhatsApp pessoal dos franqueados e
planilhas soltas — a rede não tem visibilidade de quantos leads cada unidade recebe, nem de
quantos viram cliente recorrente. Isso importa agora porque a rede está entrando em uma fase
de expansão (novas unidades no próximo semestre) e precisa de um processo replicável antes de
escalar, não depois.

---

## 01 — Escopo

O projeto cobre três frentes: (1) estruturação das etapas do funil de atendimento dentro do
CRM (novo lead → em conversa → agendado → cliente), (2) migração do histórico relevante das
planilhas atuais para os registros iniciais do sistema, e (3) definição do fluxo de uso diário
para os atendentes de cada unidade — quem registra o quê, em que momento da conversa.

Fora de escopo: integração automática com o sistema de vendas do ERP da rede (fica para uma
fase 2, após a adoção do CRM estar consolidada) e automações de disparo — isso é tratado como
projeto separado, quando chegar a hora.

---

## 02 — Desdobramento por Disciplina

**Web/Dev (setup do sistema):** Configurar as etapas do funil no CRM conforme o fluxo de
atendimento definido, estruturar os campos obrigatórios de cada registro (nome, unidade,
origem do lead, status) e preparar o ambiente de testes antes da migração.

**Relacionamento/CS:** Validar com os franqueados o vocabulário das etapas do funil (os nomes
precisam fazer sentido pra quem usa no dia a dia, não só pra quem configurou) e conduzir o
treinamento inicial das equipes de atendimento.

**Estratégia:** Definir os indicadores que a matriz vai acompanhar mês a mês (volume de leads
por unidade, taxa de conversão em cliente, tempo médio de resposta) e o formato do
relatório que sai do CRM para a diretoria.

---

## 03 — Cronograma

- **Início:** 01/08/2026
- **Marco 1 (15/08):** Funil configurado e ambiente de testes liberado
- **Marco 2 (25/08):** Migração de histórico concluída
- **Marco 3 (05/09):** Treinamento das equipes de atendimento
- **Término:** 15/09/2026 — CRM em uso em todas as unidades

---

## 04 — Tarefas

**Web/Dev**
- Configurar etapas do funil de atendimento no CRM [Web/Dev]
- Estruturar campos obrigatórios de registro [Web/Dev]
- Preparar ambiente de testes [Web/Dev]

**Relacionamento/CS**
- Validar vocabulário das etapas com franqueados [Relacionamento/CS]
- Migrar histórico relevante das planilhas para o sistema [Relacionamento/CS]
- Conduzir treinamento inicial das equipes [Relacionamento/CS]

**Estratégia**
- Definir indicadores de acompanhamento mensal [Estratégia]
- Estruturar formato do relatório para a diretoria [Estratégia]

---

## 05 — Entregáveis

- CRM configurado e em uso nas unidades da rede
- Histórico de atendimento migrado e organizado por unidade
- Roteiro de treinamento para novas equipes (reutilizável nas próximas expansões)
- Relatório-modelo de indicadores de relacionamento para a diretoria


---

# Arquivo de referência: assets/template-visual.md

# Template Visual — Documento de Projeto Nonna (v2)

Visual claro do Nonna App, o mesmo do documento de planejamento (skill `planejamento-nonna` v2).
Documento de **rolagem contínua**, sem menu lateral, legível no celular (o cliente abre no WhatsApp).
Pode ajustar espaçamentos e ênfases; não mude a ordem das seções nem o bloco `#nonna-dados`.

## Fontes

```html
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=Fraunces:opsz,wght@9..144,300;9..144,400&display=swap" rel="stylesheet">
```

- **Inter** — corpo, títulos de seção, rótulos de card
- **Fraunces 300** — só o nome do projeto na capa
- **IBM Plex Mono** — rótulos pequenos (uppercase), datas, chips. Nunca texto corrido.

## Tokens

```css
:root {
  --bg: #F6F7FB; --surface: #FFFFFF; --surface-2: #F1F3F8;
  --brand: #643B8E; --brand-soft: #F6F1FA; --accent: #EE7919; --accent-soft: #FFF7ED;
  --text: #111827; --text-2: #4D5768; --text-3: #697386; --border: #E2E5ED;
  --warning: #B45309; --warning-soft: #FEF3C7;
}
body { margin: 0; background: var(--bg); color: var(--text); font: 400 15px/1.6 Inter, system-ui, sans-serif; }
.doc { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; display: flex; flex-direction: column; gap: 28px; }
section { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
.label { font: 600 11px/1.3 "IBM Plex Mono", monospace; letter-spacing: .1em; text-transform: uppercase; color: var(--brand); }
h2 { font: 700 21px/1.25 Inter, sans-serif; letter-spacing: -.015em; margin: 0 0 14px; }
p { color: var(--text-2); }
```

## Estrutura do documento (nesta ordem)

1. **Capa + Ficha** — fundo `linear-gradient(135deg, var(--brand), #8A4FBF)`, texto branco:
   nome do projeto (Fraunces), cliente, e uma grade com **Tipo · Início · Término
   · Planejamento de origem**. Data desconhecida: `<span class="pendente">A definir</span>`.
2. **Objetivo & Racional** — o objetivo em destaque (Fraunces, sobre `--brand-soft`) e o racional em prosa.
3. **Escopo** — corpo livre, com a estrutura que servir ao projeto (jornada e páginas de um site,
   fluxo de uma automação, etapas de um dossiê). Use cards, listas ou diagrama simples em HTML.
4. **Desdobramento por Disciplina** — um card por disciplina com rótulo `.label`.
5. **Cronograma** — linha do tempo: início, marcos e término, cada um com data (`tabular-nums`).
6. **Tarefas** — agrupadas por disciplina; cada linha: tarefa (no infinitivo) · prazo. Sem lista
   do usuário: `<span class="pendente">Lista de tarefas a definir</span>`.
7. **Entregáveis** — lista com uma linha de direcionamento cada.
Rodapé: "Nonna Agência Digital · [mês/ano]". No fim do `<body>`: o bloco `nonna-dados` (ver SKILL.md).

## Componentes úteis

```css
.pendente { display: inline-block; background: var(--warning-soft); color: var(--warning); font: 600 12px Inter, sans-serif; padding: 2px 8px; border-radius: 6px; }
.chip { display: inline-block; background: var(--brand-soft); color: var(--brand); font: 500 12px "IBM Plex Mono", monospace; padding: 3px 10px; border-radius: 999px; }
.bigidea { background: var(--brand-soft); border-radius: 12px; padding: 24px; font: 300 clamp(24px, 4vw, 32px)/1.2 Fraunces, Georgia, serif; color: var(--text); }
.passo { display: grid; grid-template-columns: 32px 1fr; gap: 12px; }
.passo .n { width: 28px; height: 28px; border-radius: 50%; background: var(--brand); color: #fff; display: grid; place-items: center; font: 600 13px Inter, sans-serif; }
.tabela-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; font: 600 11px "IBM Plex Mono", monospace; text-transform: uppercase; color: var(--text-3); padding: 8px; border-bottom: 1px solid var(--border); }
td { padding: 10px 8px; border-bottom: 1px solid var(--border); color: var(--text-2); vertical-align: top; }
td.data { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text); }
@media (max-width: 520px) { section { padding: 20px 16px; } }
```
