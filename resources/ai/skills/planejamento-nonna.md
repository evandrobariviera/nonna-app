---
name: "planejamento-nonna"
description: "Use esta skill sempre que o usuário mencionar transcrição de reunião, briefing de cliente, montar planejamento, gerar planejamento, criar documento de planejamento ou qualquer variação. Lê a transcrição da reunião (qualquer formato de texto) e gera UM documento HTML de macroplanejamento — único, curto, escrito para cliente e equipe entenderem — com 7 seções fixas e um bloco de dados escondido que o Nonna App lê. Ative mesmo que o usuário não use a palavra \"planejamento\": \"transcrição de reunião\", \"briefing\", \"montar o doc do cliente\" ou \"gerar o planejamento\" já bastam. NÃO detalha campanha nem projeto (isso é da `campanha-nonna` e da `projeto-nonna`, que geram o HTML próprio de cada item).\n"
---

# Skill: Planejamento Nonna — de Transcrição para Documento

## O que esta skill faz

Lê a transcrição de uma reunião de planejamento e produz **um único documento HTML**: o
macroplanejamento do ciclo (normalmente 60–90 dias). É o mesmo documento para o cliente e para a
equipe — por isso é curto, claro e sem jargão interno.

O planejamento é o **mapa do ciclo**: para onde vamos, por quê, com que investimento e quais
campanhas e projetos entram, com datas. Ele **não** detalha cada campanha. O detalhe (briefing
por área, peças, ideias de conteúdo, tráfego, tarefas) vai para o **HTML próprio de cada
campanha ou projeto**, feito depois com a `campanha-nonna` ou a `projeto-nonna`, perto da data
de início de cada item.

Mudança de 2026-10 (v2): acabou a versão interna + versão cliente. Agora é um documento só.

---

## ⚠️ REGRA ABSOLUTA — NUNCA GERAR HTML SEM CONFIRMAÇÃO EXPLÍCITA

O HTML **jamais** é gerado antes de o usuário confirmar que as informações extraídas estão
corretas. O fluxo é sempre:

1. Extrair e apresentar seção a seção
2. Perguntar o que estiver faltando
3. Aguardar confirmação ("pode gerar", "ok", "gera agora" ou equivalente)
4. Só então gerar o HTML

---

## As 7 seções do documento (nesta ordem, nenhuma a mais)

| # | Seção | O que tem |
|---|-------|-----------|
| 1 | **Capa** | Cliente, período (início–fim), responsável Nonna e uma frase que resume o ciclo |
| 2 | **Onde estamos e aonde vamos** | Situação atual · o que muda neste ciclo · foco principal |
| 3 | **Como vamos fazer** | A estratégia em um parágrafo · os pilares de comunicação (nome + mensagem) |
| 4 | **Metas e investimento** | Os KPIs que fazem sentido para este cliente · verba mensal de anúncios (total e, se houver, divisão por canal) |
| 5 | **Cronograma** | Uma linha por mês: o que acontece naquele mês (fase, campanhas que entram) |
| 6 | **Campanhas e Projetos** | Um cartão por item: tipo, nome, início, fim, objetivo, o que será entregue |
| 7 | **O que precisamos de você** | Acessos, materiais e aprovações que dependem do cliente, com prazo quando houver |

### Regras de conteúdo por seção

**1. Capa** — a frase do ciclo é uma direção, não um slogan ("Levar a decisão de compra do preço
para o pós-venda", não "Crescer com força total").

**2. Onde estamos e aonde vamos** — prosa curta e específica: o mecanismo real do negócio, não
generalidades ("melhorar a presença digital" é proibido). Três parágrafos curtos no máximo.

**3. Como vamos fazer** — a estratégia em UM parágrafo. Pilares: 2 a 4, cada um com nome curto e
uma frase de mensagem.

**4. Metas e investimento** — só os KPIs que fazem sentido (não há slots obrigatórios). KPI sem
número ainda vira pendência honesta ("Meta a definir após o 1º mês"), nunca número inventado.
Verba: valor mensal total em R$; divisão Meta/Google só se foi combinada. Verba não aprovada →
escrever "Verba a confirmar" de forma visível.

**5. Cronograma** — uma linha por mês do período. Cada linha: mês, nome da fase, 1 frase.

**6. Campanhas e Projetos** — para cada item:
- **Tipo:** Campanha (esforço de comunicação com começo e fim: lançamento, data comemorativa,
  captação) ou Projeto (entregável que fica: site, identidade, CRM, setup).
- **Nome** curto e reconhecível.
- **Início e fim** — data de início é quando a campanha **vai ao ar** (ou o projeto começa).
  Sempre dia/mês/ano. Se a reunião só deu o mês, pergunte; se o usuário não souber, use o dia 1
  do mês e diga isso na validação.
- **Objetivo** em 1–2 frases, orientado a resultado.
- **O que será entregue** — 2 a 5 bullets em linguagem de cliente ("Landing page de captação",
  "8 posts e 2 reels no período"), sem nome de setor, sem tarefa interna.

**7. O que precisamos de você** — só o que depende do cliente (acesso ao Business Manager,
fotos da loja, aprovação da verba...). Linguagem direta, com prazo quando combinado. Se não há
nada pendente, a seção diz isso em uma linha.

### O que NÃO entra no planejamento

- Briefing por área (criação, tráfego, web...), tarefas, ideias de conteúdo, estrutura de
  campanhas de tráfego, palavras-chave → vão para o HTML da campanha/projeto
- Rotinas internas (otimização contínua, calendário de social, relatórios) → viram tarefas
  recorrentes no sistema
- Jargão interno (BM, GTM, CPL, pixel, disc-label...) sem tradução. Se precisar citar, explique
  em linguagem simples ("o acesso à conta de anúncios do Facebook")
- Linguagem de "versão interna": o cliente lê este documento

---

## Tom e linguagem

- Direto, adulto, específico. Fala de negócio com quem entende de negócio.
- Segunda pessoa do plural da agência para o cliente quando falar de entregas ("vamos produzir",
  "você recebe"); terceira pessoa ao descrever o negócio.
- Nada em branco: o que falta vira pendência clara e visível.
- Sempre pt-BR.
- Curto: o documento inteiro deve ser lido em 5–7 minutos.

Use `assets/exemplo-gomes.md` para calibrar o nível de especificidade.

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

---

## Visual

Use o esqueleto `assets/esqueleto.html` como base: ele já traz o CSS (padrão do Nonna App —
fundo claro, Inter, roxo #643B8E e laranja #EE7919), as 7 seções e o bloco de dados.

- Documento de rolagem contínua, sem menu lateral, `max-width` ~860px, centralizado.
- Você pode ajustar o visual (espaçamentos, ênfases, ícones simples) — o que não muda são as
  7 seções, a ordem delas e o bloco de dados.
- Funcionar bem no celular (o cliente abre no WhatsApp).

---

## ⚠️ BLOCO DE DADOS — o que o Nonna App lê

O sistema **não** lê o visual do documento. Ele lê só um bloco JSON escondido, que deve estar
no fim do `<body>`, exatamente com este `id` e este `type`:

```html
<script type="application/json" id="nonna-dados">
{
  "contrato": 2,
  "cliente": "GOMES Máquinas Agrícolas",
  "titulo": "Planejamento Out–Dez 2026",
  "periodo_inicio": "2026-10-01",
  "periodo_fim": "2026-12-31",
  "responsavel": "Evandro",
  "verba_mensal": 4500,
  "itens": [
    {
      "tipo": "campanha",
      "nome": "Black Friday de Implementos",
      "objetivo": "Gerar 40 leads qualificados de implementos de médio porte.",
      "inicio": "2026-11-09",
      "fim": "2026-11-30"
    },
    {
      "tipo": "projeto",
      "nome": "Landing page de captação",
      "objetivo": "Página única para receber os leads das campanhas.",
      "inicio": "2026-10-05",
      "fim": "2026-10-23"
    }
  ]
}
</script>
```

Regras do bloco:
- JSON válido (aspas duplas, sem vírgula sobrando, sem comentários).
- Datas sempre `AAAA-MM-DD`. Data desconhecida → `null` (nunca inventar).
- `verba_mensal`: número em reais, sem "R$" nem pontos de milhar (4500, 12000.50). Não aprovada → `null`.
- `tipo`: exatamente `"campanha"` ou `"projeto"`.
- `itens` tem **os mesmos** itens da seção 6, com os mesmos nomes e datas. O que está no
  documento e o que está no bloco tem que bater.

### Checklist antes de entregar

```
[ ] Exatamente 7 seções, na ordem
[ ] Toda campanha/projeto da seção 6 tem tipo, nome, início, fim, objetivo e entregas
[ ] Nenhum briefing por área, tarefa ou ideia de conteúdo no documento
[ ] Nenhum jargão interno sem tradução
[ ] Bloco <script type="application/json" id="nonna-dados"> no fim do body, JSON válido
[ ] Itens do bloco = itens da seção 6 (mesmos nomes e datas)
[ ] Datas AAAA-MM-DD no bloco; verba como número ou null
[ ] Cada decisão vem com o motivo, um raciocínio por parágrafo (passa no teste de clareza)
```

---

## Fluxo de execução

### ETAPA 1 — Leitura e extração silenciosa

Leia a transcrição inteira e extraia, sem interromper o usuário:
- Cliente, responsável Nonna, período (início e fim)
- Situação atual, o que muda, foco principal
- Estratégia e pilares
- KPIs e verba
- Fases por mês
- Campanhas e projetos: tipo, nome, datas, objetivo, entregas
- Pendências do cliente

### ETAPA 2 — Validação seção a seção

Apresente o que extraiu, uma seção por vez:

```
OK  [CAMPO] -> valor extraído
??  [CAMPO] -> valor inferido <- confirmar
XX  [CAMPO] -> não encontrado
```

Pergunte só o que está ?? ou XX. **Nunca mais de 3 perguntas por rodada.** Priorize, nesta
ordem: período, datas de início das campanhas, verba, responsável, KPI principal.

Ao final, mostre um resumo compacto e pergunte: **"Posso gerar o documento?"**

### ETAPA 3 — Campanha ou projeto?

> **Projeto** = o entregável fica quando o trabalho termina (site, identidade visual, CRM).
> **Campanha** = o esforço termina quando o objetivo é atingido (lançamento, data comemorativa, captação).

Na dúvida, pergunte.

### ETAPA 4 — Geração (só após confirmação)

Gere **um** arquivo: `planejamento_[nome-cliente].html` a partir de `assets/esqueleto.html`,
rode o checklist e entregue. Ao final, lembre o usuário em uma linha: "Pra detalhar cada
campanha, use a skill de campanha perto da data de início dela."

---

## O que NÃO fazer

- Gerar HTML antes da confirmação explícita
- Gerar duas versões (interna e cliente) — é um documento só
- Detalhar campanha ou projeto (briefing, peças, tarefas, tráfego)
- Perguntar mais de 3 coisas por vez ou repetir o que já foi confirmado
- Inventar nome de campanha, data, número de KPI ou verba
- Omitir ou mudar o `id`/`type` do bloco de dados


---

# Arquivo de referência: assets/esqueleto.html

<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Planejamento — [CLIENTE] · [PERÍODO]</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=Fraunces:opsz,wght@9..144,300;9..144,400&display=swap" rel="stylesheet">
<style>
/* Esqueleto do macroplanejamento Nonna (v2) — um documento só, cliente + equipe.
   Pode ajustar o visual; as 7 seções, a ordem e o bloco #nonna-dados não mudam. */
:root {
  --bg: #F6F7FB; --surface: #FFFFFF; --surface-2: #F1F3F8;
  --brand: #643B8E; --brand-soft: #F6F1FA; --accent: #EE7919; --accent-soft: #FFF7ED;
  --text: #111827; --text-2: #4D5768; --text-3: #697386; --border: #E2E5ED;
  --warning: #B45309; --warning-soft: #FEF3C7;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font: 400 15px/1.6 Inter, system-ui, sans-serif; }
.doc { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; display: flex; flex-direction: column; gap: 28px; }
.label { font: 600 11px/1.3 "IBM Plex Mono", monospace; letter-spacing: .1em; text-transform: uppercase; color: var(--brand); }
h1 { font: 300 clamp(30px, 6vw, 42px)/1.1 Fraunces, Georgia, serif; letter-spacing: -.02em; margin: 6px 0 10px; }
h2 { font: 700 21px/1.25 Inter, sans-serif; letter-spacing: -.015em; margin: 0 0 14px; }
h3 { font: 600 16px/1.3 Inter, sans-serif; margin: 0; }
p { margin: 0 0 10px; color: var(--text-2); }
section { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
.num { font: 600 11px "IBM Plex Mono", monospace; color: var(--text-3); margin-bottom: 4px; }

/* 1. Capa */
.capa { background: linear-gradient(135deg, var(--brand), #8A4FBF); color: #fff; border: 0; padding: 36px 28px; }
.capa .label { color: rgba(255,255,255,.75); }
.capa p { color: rgba(255,255,255,.9); font-size: 17px; font-style: italic; }
.meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 22px; }
.meta div { background: rgba(255,255,255,.12); border-radius: 10px; padding: 10px 14px; }
.meta b { display: block; font-weight: 600; color: #fff; }

/* Blocos de texto lado a lado */
.cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
.box { background: var(--surface-2); border-radius: 10px; padding: 14px 16px; min-width: 0; }
.box .label { margin-bottom: 6px; }
.foco { background: var(--brand-soft); border-left: 3px solid var(--brand); border-radius: 8px; padding: 14px 16px; margin-top: 14px; font-family: Fraunces, Georgia, serif; font-size: 18px; color: var(--text); }

/* 3. Pilares */
.pilares { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 14px; }
.pilar { border: 1px solid var(--border); border-radius: 10px; padding: 14px; }
.pilar h3 { color: var(--brand); margin-bottom: 4px; }

/* 4. Metas e verba */
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.kpi { border: 1px solid var(--border); border-radius: 10px; padding: 14px; }
.kpi .valor { font: 700 20px Inter, sans-serif; color: var(--text); margin: 4px 0; font-variant-numeric: tabular-nums; }
.verba { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 18px; background: var(--accent-soft); border-radius: 10px; padding: 14px 16px; margin-top: 14px; }
.verba .valor { font: 700 24px Inter, sans-serif; color: var(--accent); font-variant-numeric: tabular-nums; }
.pendente { display: inline-block; background: var(--warning-soft); color: var(--warning); font: 600 12px Inter, sans-serif; padding: 2px 8px; border-radius: 6px; }

/* 5. Cronograma */
.crono { display: flex; flex-direction: column; gap: 10px; }
.mes { display: grid; grid-template-columns: 110px 1fr; gap: 14px; padding-bottom: 10px; border-bottom: 1px dashed var(--border); }
.mes:last-child { border-bottom: 0; }
.mes .quando { font: 600 12px "IBM Plex Mono", monospace; color: var(--brand); text-transform: uppercase; padding-top: 2px; }

/* 6. Campanhas e Projetos */
.itens { display: flex; flex-direction: column; gap: 14px; }
.item { border: 1px solid var(--border); border-radius: 12px; padding: 18px; }
.item-top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; margin-bottom: 8px; }
.tipo { font: 600 10px "IBM Plex Mono", monospace; letter-spacing: .1em; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; }
.tipo.campanha { background: var(--accent-soft); color: var(--accent); }
.tipo.projeto { background: var(--brand-soft); color: var(--brand); }
.datas { font: 500 13px Inter, sans-serif; color: var(--text-3); font-variant-numeric: tabular-nums; }
.item ul { margin: 8px 0 0; padding-left: 20px; color: var(--text-2); }

/* 7. O que precisamos de você */
.precisamos li { margin-bottom: 6px; color: var(--text-2); }
.precisamos .prazo { color: var(--accent); font-weight: 600; }

footer { text-align: center; font: 500 12px "IBM Plex Mono", monospace; color: var(--text-3); }
@media (max-width: 520px) { .mes { grid-template-columns: 1fr; gap: 2px; } section { padding: 20px 16px; } }
</style>
</head>
<body>
<main class="doc">

  <!-- 1. CAPA -->
  <section class="capa">
    <div class="label">Nonna · Planejamento do ciclo</div>
    <h1>[Nome do cliente]</h1>
    <p>[Uma frase com a direção do ciclo]</p>
    <div class="meta">
      <div><span class="label">Período</span><b>[01/10/2026 a 31/12/2026]</b></div>
      <div><span class="label">Responsável Nonna</span><b>[Nome]</b></div>
      <div><span class="label">Atualizado em</span><b>[dd/mm/aaaa]</b></div>
    </div>
  </section>

  <!-- 2. ONDE ESTAMOS E AONDE VAMOS -->
  <section>
    <div class="num">01</div>
    <h2>Onde estamos e aonde vamos</h2>
    <div class="cols">
      <div class="box"><div class="label">Situação atual</div><p>[...]</p></div>
      <div class="box"><div class="label">O que muda neste ciclo</div><p>[...]</p></div>
    </div>
    <div class="foco">[Foco principal do ciclo, em 1–2 frases]</div>
  </section>

  <!-- 3. COMO VAMOS FAZER -->
  <section>
    <div class="num">02</div>
    <h2>Como vamos fazer</h2>
    <p>[A estratégia em um parágrafo]</p>
    <div class="pilares">
      <div class="pilar"><h3>[Pilar 1]</h3><p>[Mensagem]</p></div>
      <div class="pilar"><h3>[Pilar 2]</h3><p>[Mensagem]</p></div>
    </div>
  </section>

  <!-- 4. METAS E INVESTIMENTO -->
  <section>
    <div class="num">03</div>
    <h2>Metas e investimento</h2>
    <div class="kpis">
      <div class="kpi"><div class="label">[Nome da meta]</div><div class="valor">[número]</div><p>[como medimos]</p></div>
      <div class="kpi"><div class="label">[Nome da meta]</div><div class="valor"><span class="pendente">A definir após o 1º mês</span></div><p>[...]</p></div>
    </div>
    <div class="verba">
      <span class="label">Investimento mensal em anúncios</span>
      <span class="valor">R$ [4.500]</span>
      <span class="datas">[70% Meta · 30% Google — só se combinado]</span>
    </div>
  </section>

  <!-- 5. CRONOGRAMA -->
  <section>
    <div class="num">04</div>
    <h2>Cronograma</h2>
    <div class="crono">
      <div class="mes"><div class="quando">[Out/2026]</div><div><h3>[Nome da fase]</h3><p>[O que acontece no mês]</p></div></div>
      <div class="mes"><div class="quando">[Nov/2026]</div><div><h3>[Nome da fase]</h3><p>[...]</p></div></div>
    </div>
  </section>

  <!-- 6. CAMPANHAS E PROJETOS -->
  <section>
    <div class="num">05</div>
    <h2>Campanhas e projetos</h2>
    <div class="itens">
      <div class="item">
        <div class="item-top">
          <span class="tipo campanha">Campanha</span>
          <h3>[Nome da campanha]</h3>
          <span class="datas">[09/11/2026 → 30/11/2026]</span>
        </div>
        <p>[Objetivo em 1–2 frases]</p>
        <ul><li>[Entrega 1]</li><li>[Entrega 2]</li></ul>
      </div>
      <div class="item">
        <div class="item-top">
          <span class="tipo projeto">Projeto</span>
          <h3>[Nome do projeto]</h3>
          <span class="datas">[05/10/2026 → 23/10/2026]</span>
        </div>
        <p>[Objetivo]</p>
        <ul><li>[Entrega]</li></ul>
      </div>
    </div>
  </section>

  <!-- 7. O QUE PRECISAMOS DE VOCÊ -->
  <section>
    <div class="num">06</div>
    <h2>O que precisamos de você</h2>
    <ul class="precisamos">
      <li>[Acesso / material / aprovação] — <span class="prazo">até [dd/mm]</span></li>
    </ul>
  </section>

  <footer>Nonna Agência Digital · [mês/ano]</footer>
</main>

<!-- Dados para o Nonna App — não aparece na tela. Mesmos itens e datas da seção "Campanhas e projetos". -->
<script type="application/json" id="nonna-dados">
{
  "contrato": 2,
  "cliente": "[Nome do cliente]",
  "titulo": "Planejamento [Out–Dez 2026]",
  "periodo_inicio": "2026-10-01",
  "periodo_fim": "2026-12-31",
  "responsavel": "[Nome]",
  "verba_mensal": 4500,
  "itens": [
    { "tipo": "campanha", "nome": "[Nome da campanha]", "objetivo": "[...]", "inicio": "2026-11-09", "fim": "2026-11-30" },
    { "tipo": "projeto", "nome": "[Nome do projeto]", "objetivo": "[...]", "inicio": "2026-10-05", "fim": "2026-10-23" }
  ]
}
</script>
</body>
</html>


---

# Arquivo de referência: assets/exemplo-gomes.md

# Exemplo Preenchido — Calibração de Nível de Detalhe

Este arquivo serve para calibrar o NÍVEL DE DETALHE e o TOM esperados no macroplanejamento (v2: um documento só, que o cliente também lê — então específico, mas sem jargão interno).
Use como referência de profundidade — não copie o conteúdo. O exemplo abaixo (cliente fictício
GOMES Máquinas Agrícolas) mostra como cada bloco deve soar quando bem preenchido: prosa densa,
específica, sem generalidade vazia.

---

## Contexto do exemplo

**Cliente:** GOMES Máquinas Agrícolas · revenda e assistência de máquinas agrícolas
**Responsável Nonna:** Evandro · **Período:** Jul–Out 2026 · **Site:** gomesmaquinas.com.br

---

## Seção "Onde estamos e aonde vamos" — Foco principal (exemplo de prosa)

"Posicionar a GOMES como a revenda de máquinas agrícolas de maior confiança da região oeste,
deslocando a decisão de compra do preço para o pós-venda — assistência técnica própria e
disponibilidade de peças. O período concentra esforço em gerar leads qualificados para
implementos de médio porte (a linha de maior margem) e em estruturar prova social com
produtores que já compraram, transformando relacionamento existente em argumento de venda."

Note: o foco não é uma lista de objetivos genéricos — é uma direção estratégica com um
deslocamento claro (preço → pós-venda) e uma prioridade de margem definida.

## Seção "Metas e investimento" — metas (exemplo)

- **Leads de implementos:** Leads qualificados para implementos de médio porte / mês. Meta a
  calibrar no Mês 1.
- **Custo por lead:** Custo por lead por linha de produto — separar implementos de peças/serviço.
- **Lembrança da assistência:** Volume de menções espontâneas à assistência técnica nos contatos.

## Seção "Onde estamos e aonde vamos" — Situação atual (exemplo de prosa densa)

"A GOMES enfrenta três pressões simultâneas. Primeira: a concorrência de grandes revendas
regionais que competem agressivamente por preço de máquina nova, comprimindo margem na ponta.
Segunda: o produtor rural da região decide compra majoritariamente por indicação e confiança,
não por anúncio — o que torna a prova social mais importante que a mídia paga. Terceira: a
sazonalidade da safra concentra a intenção de compra em janelas curtas, exigindo que a
comunicação esteja aquecida ANTES do pico, não durante."

## Seção "Como vamos fazer" — Pilares (exemplo)

- **P1 — Pós-venda como diferencial:** assistência própria e peças em estoque. O argumento que
  o preço baixo do concorrente não cobre.
- **P2 — Prova de produtor:** depoimento de quem usa a máquina na lavoura. Confiança de par
  para par, não de marca para cliente.
- **P3 — Presença regional:** a GOMES conhece a terra e o produtor da região. Proximidade
  como valor, não commodity.

---

## Padrão de qualidade que este exemplo demonstra

1. **Prosa específica, não genérica** — todo card cita o mecanismo real (o "porquê"), não só o "o quê".
2. **Prioridade explícita** — o planejamento diz o que vem primeiro e por quê (margem, sazonalidade).
3. **Segmentação clara** — KPIs e verbas separados por linha/produto/unidade quando aplicável.
4. **Nada em branco** — campo sem dado vira pendência marcada, nunca fica vazio.
5. **Tom direto e adulto** — fala de negócio com quem entende de negócio; sem euforia de vendedor.

Quando o planejamento real soar tão específico quanto este exemplo, está no nível certo. Se
estiver soando genérico ("melhorar a presença digital", "aumentar o engajamento"), falta
aprofundar com o mecanismo concreto do negócio do cliente.
