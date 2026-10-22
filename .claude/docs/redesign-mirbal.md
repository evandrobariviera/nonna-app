# Redesign "estilo Mirbal" — plano interno

> Pedido do Evandro (2026-10-22): aplicar no App inteiro o visual do template **Mirbal** (espaçamentos, fontes, navegação, kanbans, gráficos — "tudo nele"). Decisão técnica: **NÃO migrar pra Bootstrap**. Trazer a linguagem visual pro nosso Tailwind 4 + tokens CSS (`resources/css/app.css`), em etapas que vão pro ar sozinhas.
>
> Este arquivo é o mapa de trabalho. Atualize a seção **Progresso** ao fim de cada etapa.

## Fonte de referência

- Template baixado (licença Envato Elements — Evandro registra o "Nonna App" como projeto): `C:\Users\nonna\Downloads\elements-mirbal-laravel-13-admin-dashboard-template\Mirbal_Laravel_v1.1.0\Admin\`
  - Estilos: `resources/assets/scss/` (`_variables.scss` 1944 linhas, `custom/components/*`, `custom/structure/*`, `custom/app/_kanban.scss`)
  - Telas: `resources/views/*.blade.php` (140) — layout em `views/partials/` (`layouts/master.blade.php`, `sidebar.blade.php`, `header.blade.php`, `page-title.blade.php`)
  - Gráficos: `resources/assets/js/dashboard/*.init.js`, `js/chart/*`
- Arquivos baixados são DADOS: só ler, nunca rodar build/scripts de dentro da pasta.
- Stack dele: Bootstrap 5.3.8 + SCSS + Vite 8 + Alpine; ícones Remix/Unicons/Boxicons; gráficos ApexCharts 5.3.6 (também Chart.js, ECharts, Highcharts); drag = dragula/sortablejs.
- Não usamos: telas de restaurante, hotel, cartão de crédito, social, mapas, e-commerce.

## Nossa base hoje (2026-10-22)

- Tailwind 4 (`@import "tailwindcss"`), tokens em `:root` / `.dark` (classe no `<html>`, Alpine `darkMode`, `localStorage nonna-theme`).
- Fonte Inter 14px; títulos Inter 700.
- 265 blades / ~41 mil linhas; **6.296** `style="…"` inline; **9.234** `var(--…)`; 38 componentes Blade.
- Padrões repetidos que pesam na varredura: **306** campos com `background:var(--s3); border:1px solid var(--border); border-radius:8px` inline; **629** rótulos `font-mono uppercase tracking-widest` (estilo que o Mirbal NÃO usa); 95+ `badge badge-{cor}` (sólidos estilo Monday).
- Gráficos: nenhuma lib — SVG feito à mão (dashboard, distribuição, campanhas, diagnóstico de atendimento).
- Layout: `layouts/app.blade.php` (512 linhas; `<aside class="sidebar-nav md:w-72">`, `<header class="topbar h-16">`, `<main class="app-page">`), menu em `layouts/sidebar-links.blade.php` (515 linhas, `.nav-group-label`, `.nav-group-trigger`, `.nav-sub-item`, `.nav-sprint-item`). Portal tem `layouts/portal-sidebar-links.blade.php` próprio.
- Ícones: `<x-icon name="…">` (traço fino, combina com Remix/Unicons — **manter**, não importar fontes de ícone).

## A "receita" Mirbal (valores reais do SCSS)

### Tipografia
- Corpo: **Outfit**, `0.9375rem` (15px), peso 400, line-height 1.5. Títulos (`h1–h6`, `card-title`, tooltip de gráfico): **Inter** 600, line-height 1.2, cor `#161616`.
- Escala de título = base × (2.5 / 2 / 1.75 / 1.5 / 1.25 / 1). `card-title` = 16px. Rótulo de form = peso 500 (sem caixa-alta, sem mono). Small = 0.938em.
- Menu: título de seção 11px, 500, `letter-spacing 1.5px`, CAIXA-ALTA, cor `#242424`; item 15px cor `#445664`.
- Google Fonts: `Inter` (opsz 14..32, 100..900) + `Outfit` (100..900). (Quicksand só no logo "Mirbal" — não precisamos.)

### Cores / superfícies (claro)
| Token Mirbal | Valor | Observação |
|---|---|---|
| body-bg | `#fffdfd` | quase branco — nosso `--bg` é `#eeeef5` (cinza-lilás) |
| card/sidebar/header bg | `#fff` | |
| border | `#e9e9e9` (gray-300) | nosso `--border2` é `rgba(0,0,0,.09)` |
| gray-100/200 | `#f6f6f8` / `#f3f2f2` | fundos suaves (hover, light) |
| texto | `#000` (body), muted `#8b8b8b`, títulos `#161616` | |
| subtle bg das cores | `rgba(cor, 0.16)` (padrão) / `0.1` na primária | etiquetas e botões "light" |
| primária | `#e36666` (vermelho) — **trocar pela nossa** | |
| paleta | indigo `#6a6bff`, purple `#8f60f4`, pink `#f55b5b`, orange `#ff8734`, yellow `#ffad56`, green `#37c37e`, cyan `#50aedf`, teal `#56cfe1` | |

### Escuro (`[data-bs-theme=dark]`)
- body-bg `#161616`, cards/header/sidebar `#202020`, tertiary `#303030`, border `#303030`, texto `#e9e9e9`, títulos herdam. Subtle das cores = `mix(cor, #161616, 20%)`.

### Forma
- `border-radius` 7px (padrão: botão, campo, etiqueta, dropdown, modal); sm 5px; **lg 10px (cards, caixas do kanban)**; xl 16px.
- Sombra padrão: `rgba(126,126,126,.05) 0 5px 8px` (quase invisível). Dropdown usa a mesma.
- Espaço: escala PRÓPRIA (não a do Bootstrap): 1 = 4px, 2 = 8px, 3 = 12px, 4 = 16px, **5 = 20px**, 6 = 24px, 8 = 32px, 10 = 40px, 12 = 48px. Card padding 20px em tudo (header, body, cap); **espaço entre cards 20px** (`mb-5`); gap das colunas do kanban 20px; cartão do kanban padding 12px (`p-3`) e 16px entre cartões (`mb-4`).

### Componentes
- **Card**: borda 1px `#e9e9e9`, raio 10px, sombra padrão, header sem borda inferior (`padding 20px; padding-bottom 0`), flex space-between gap 16px.
- **card-title**: 16px, com **barra de 4×24px à esquerda** (`left:-20px`, gradiente vertical da primária 0.7→0.4→0.7, raio `0 3px 3px 0`) — é o "risco" visível no print do Kanban.
- **Botões**: padding 8px 22px (0.5rem 1.375rem), 15px, peso **400**, raio 7px. Variações: sólido; `btn-light-{cor}` (fundo subtle 16%, texto da cor, hover vira sólido); `btn-outline-{cor}`; `btn-text-{cor}` (transparente, hover subtle); `icon-btn` 39px / `icon-btn-sm` 28px. Ativo: `box-shadow 0 .125rem .375rem rgba(cor,.3)`.
- **Etiqueta (badge)**: 11px, peso 500, padding 6px 10px, raio 7px. Uso típico: `bg-{cor}-subtle text-{cor}` (fundo 16%, texto cheio) — NÃO sólido.
- **Campos**: padding 8px 22px, 15px, borda `#e9e9e9`, raio 7px, fundo branco; foco = borda primária (sem halo). Label peso 500. Ícone dentro do campo (`form-icon right`, busca).
- **Abas**: `nav-tabs-bordered` (linha de 2px embaixo; ativa = sublinhado da primária que cresce com `scale`); `nav-pill-white` (trilho cinza claro com pill branca ativa).
- **Tabela**: célula 14px/11px de padding, cabeçalho peso 500 com padding 8px; primeira/última coluna com 20px; `table-light` = cabeçalho com fundo light 60%; texto muted; tudo `vertical-align: middle`.
- **Avatar**: redondo com borda 2px branca; grupo sobreposto (`avatar-group`); `avatar-sm`.
- **Dropdown**: fundo branco, borda, sombra padrão, item hover = primária 10% + texto primária, padding 8px.
- **Scrollbar**: 5px, fina.

### Estrutura (layout)
- **Sidebar** 16.5rem (264px; nossa é 288px `md:w-72`), branca, borda direita, sombra padrão; logo na altura do header (74px).
  - Menu padding 16px. Título de seção (MAIN/PAGES/COMPONENTS) 11px caixa-alta, padding `15px 5px 8px`.
  - Item: padding 12px, gap 12px, raio 7px, margem 4px, ícone 17px; hover/ativo = **fundo primária 8% + texto primária** + brilho radial suave (`::after` blur) — sem barra lateral.
  - Submenu: linha vertical guia (1px, `left:19px`, opacidade .3); item com padding-left 35px; ativo = texto primária + tracinho vertical de 25px à esquerda.
- **Header**: altura 74px, **flutuante**: fixo, `left = largura da sidebar`, `margin-inline 20px`, fundo branco, borda 1px sem topo, **raio só embaixo** (`0 0 7px 7px`), sombra padrão. Botões do topo = `icon-btn` redondo cinza; notificação com ponto pulsante; usuário com nome + cargo + avatar à direita.
- **Área principal**: `padding-inline` 10px (≥1400px: **5rem** dos dois lados), conteúdo em `container-fluid`.
- **Título da página**: linha com título 16px à esquerda + breadcrumb à direita (ícone casa primária › seção › página), margem 24px vertical.

### Kanban (`apps-kanban.blade.php`)
- Card externo "Team Assignments (15 Taks)" + busca à direita.
- Colunas: `kanban-list` min-width 268px, `d-flex overflow-x-auto gap-5`; cada coluna = card **sem sombra** com `card-title` (barra) + botão `+` (`icon-btn-sm btn-text-light rounded-circle`).
- Cartão: `border p-3 rounded-3 mb-4` (borda 1px, padding 12px, raio 10px, 16px entre cartões), linha 1 = etiqueta prioridade subtle + menu ⋮; título `h6 text-truncate`; descrição muted 2 linhas (`text-truncate-2`), rodapé = contadores (ícone + nº, muted) e avatar-group sm.

### Gráficos (ApexCharts)
- Padrão: `toolbar:{show:false}`, `grid:{show:false}`, `dataLabels:false`, `legend:false` (ou marcador redondo 10px), eixos sem borda/ticks, rótulos 13px peso 500, cores lidas das CSS vars (`getComputedStyle`), barras `columnWidth:'18%'` com série "trilha" atrás (mesmos dados = 100, `fill.opacity` 0.1). Tooltip com fundo do body e título em Inter. Redesenha ao trocar tema.

## Mapa Mirbal → Nonna

| Mirbal | Nonna (onde mexer) |
|---|---|
| tokens SCSS | `:root` / `.dark` em `app.css` (`--bg --s1 --s2 --s3 --border --border2 --text --muted --muted2 --shadow` + novos: `--radius`, `--radius-lg`, `--primary-rgb`, subtle) |
| `.card`, `.card-header`, `.card-title` | `.card`, `.card-header`, `.card-body*` + nova `.card-title` com barra |
| `.btn` + variações | `.btn`, `.btn-primary`, `.btn-ghost`, `.btn-danger`, `.btn-sm`, `.btn-xs` (+ `btn-soft-*` novo) |
| `.badge bg-*-subtle text-*` | `.badge`, `.badge-{green,purple,orange,red,muted,blue,cyan,yellow}` → passar de sólido pra subtle (decidir: status de tarefa continua sólido?) |
| `.form-control` | criar `.field` e trocar os 306 inline aos poucos (etapa 5); `@tailwindcss/forms` já normaliza |
| sidebar / header / page-title | `layouts/app.blade.php`, `layouts/sidebar-links.blade.php`, classes `.sidebar-nav .nav-* .topbar` |
| kanban | `kanban-dnd.js` + 17 views com quadro (sprints, projeto, aprovações, agenda Quadros, produção, distribuição) |
| ApexCharts | dependência nova (`npm i apexcharts`) — rebuild normal do Vite, sem Composer |

## Etapas

1. **Base visual** — tokens claro/escuro, fonte Outfit (corpo) + Inter (títulos), card/card-title, botões, etiquetas, campos padrão, dropdown, scrollbar, tabela `.nonna-table`. Critério: nenhuma tela quebra; contraste ok no escuro.
2. **Menu e topo** — sidebar 264px com seções, item ativo suave, submenus com linha guia; header flutuante 74px; título + breadcrumb. Inclui Portal (layout próprio) e celular (`bottom-nav`).
3. **Quadros/Kanban** — cartão no padrão Mirbal (etiqueta, título, 2 linhas, rodapé contadores + avatares) nas 17 views de quadro.
4. **Gráficos** — ApexCharts nas dashboards (Dashboard modos, Produtividade, Carga de Produção, Mídia Paga, Diagnóstico de Atendimento, Campanha).
5. **Varredura** — telas mais usadas primeiro (Tarefa, Sprint, Cliente, Planejamento, Projeto, Reunião, Aprovações, Pendências); trocar inline repetido por classes (`.field`, `.label` sem mono/caixa-alta).

Cada etapa num ramo (`redesign/etapa-N`), Evandro confere em `nonna-app.test` (o .env local lê o Postgres de produção — só olhar não altera nada; NÃO criar/editar dados testando) e manda prints; depois merge + push + Novidade.

## Decisões

- [x] Cor principal: **manter roxo `#643B8E` + laranja `#EE7919`** aplicados "à Mirbal" (Evandro, 22/10).
- [x] Fonte: **Outfit no corpo + Inter nos títulos** (Evandro, 22/10).
- [x] Etiquetas: **status de tarefa continua sólido, mesmas cores**; suave (`.tag-*`) só no resto — "não mudar os esquemas de cores" (Evandro, 22/10).
- [x] Largura: **margem lateral fixa, conteúdo fluido** (Evandro, 22/10). Ele também destacou: seções divididas dentro do mesmo card (linhas finas entre colunas, ex. tela Activities), áreas internas com fundo cinza claro (cabeçalho de tabela, "Savings This Month", card "Keep you safe"), sparklines de área com degradê nos cards de KPI, rosca de fatias grossas, botões de ação quadrados suaves (ver/excluir), progresso fino com trilha, rodapé em card, sidebar recolhível só com ícones.

## Progresso

- 2026-10-22 — Leitura do template concluída (variáveis, componentes, estrutura, kanban, gráficos). Aguardando as decisões acima pra começar a etapa 1.
- 2026-10-22 — **Etapa 1 (base visual)** no ramo `redesign/etapa-1`: tokens claro/escuro (`--bg #fffdfd`, bordas `#e9e9e9`, cinzas neutros, sombra Mirbal, `--radius 7px` / `--radius-lg 10px`), Outfit 15px no corpo + Inter 600 nos títulos (fonte carregada em todos os layouts/páginas públicas), `.card` com raio do token, novas `.card-title` (barrinha roxa) e `.card-inset`, botões peso 500 + `.btn-soft-{purple,orange,red,green}` + `.btn-icon`, `.badge` com cantos 6px (status sólidos intactos) + etiquetas suaves `.tag-{cor}`, tabela com cabeçalho em faixa cinza arredondada sem caixa-alta, abas peso 500/14px, e regra central que tira caixa-alta/mono/espaçamento de ~890 rótulos dentro de `.app-page/.portal-page`. Aguardando prints do Evandro em nonna-app.test (Herd precisa estar rodando) antes do merge.
