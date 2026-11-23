{{-- Versão do cliente do Planejamento em campos (PlanejamentoSchema). Mesmo visual do modelo v4,
     sem instruções nem avisos internos. Nome do cliente = razão social (apelido é só interno). --}}
@php
    $plan      = $owner instanceof \App\Models\MacroPlan ? $owner : null;
    $cliente   = $document->client?->company_name ?? '';
    $metasById = collect($content['metas'] ?? [])->keyBy('id');
    $itensById = collect($content['itens'] ?? [])->keyBy('id');
    $carriers  = $schema->carriers($content);
    $budget    = $schema->budget($content);
    $d         = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '';
    $txt       = fn ($v) => nl2br(e((string) $v));
    $isPending = fn ($v) => is_string($v) && mb_strtolower(trim($v)) === 'a confirmar';
    $money     = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Planejamento · {{ $cliente }} · {{ $plan?->periodLabel() }}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=Fraunces:opsz,wght@9..144,300;9..144,400&display=swap" rel="stylesheet">
<style>
/* Visual do modelo v4 (.claude/docs/templates/nonna-planejamento-modelo-v4.html) — manter igual. */
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

/* Componentes do planejamento v4 */
.ph { background: var(--accent-soft); color: #9A4A0B; border-radius: 4px; padding: 0 4px; }
.capa .ph { background: rgba(255,255,255,.18); color: #fff; }
.pendente-ph { display: inline-block; background: var(--warning-soft); color: var(--warning); font: 600 12px Inter, sans-serif; padding: 2px 8px; border-radius: 6px; }
.guia { border: 1px dashed #C9B6DD; background: #FBF8FD; border-radius: 10px; padding: 12px 14px; margin: 0 0 16px; font-size: 13.5px; color: var(--text-2); }
.guia b { color: var(--brand); }
.guia .label { display: block; margin-bottom: 4px; }
body.sem-guia .guia { display: none; }
.alternar { position: fixed; right: 16px; bottom: 16px; background: var(--brand); color: #fff; border: 0; border-radius: 999px; padding: 10px 16px; font: 600 13px Inter, sans-serif; cursor: pointer; box-shadow: 0 4px 14px rgba(100,59,142,.3); }
.foco .label { display: block; font-family: "IBM Plex Mono", monospace; margin-bottom: 4px; }
.kpi .linha { display: flex; gap: 14px; flex-wrap: wrap; margin: 6px 0 8px; font-size: 13px; color: var(--text-3); }
.kpi .linha b { color: var(--text); font-weight: 600; }
.carrega { margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border); font-size: 12.5px; color: var(--text-3); }
.chip-item { display: inline-block; font: 600 11.5px Inter, sans-serif; padding: 2px 8px; border-radius: 999px; margin: 3px 4px 0 0; }
.chip-item.campanha { background: var(--accent-soft); color: #9A4A0B; }
.chip-item.projeto { background: var(--brand-soft); color: var(--brand); }
.sem-dono { color: var(--warning); font-weight: 600; }
.item .medida { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 12px; }
.item .medida div { background: var(--surface-2); border-radius: 8px; padding: 10px 12px; font-size: 13.5px; color: var(--text-2); }
.item .medida .label { display: block; margin-bottom: 2px; }
.rotulo { display: block; margin: 18px 0 8px; }
.rolagem { overflow-x: auto; }
.tabela { width: 100%; border-collapse: collapse; font-size: 14px; }
.tabela th { text-align: left; font: 600 11px "IBM Plex Mono", monospace; letter-spacing: .08em; text-transform: uppercase; color: var(--text-3); padding: 8px 10px; border-bottom: 1px solid var(--border); }
.tabela td { padding: 10px; border-bottom: 1px solid var(--border); color: var(--text-2); vertical-align: top; }
.tabela td.valor { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text); }
.tabela tr.total td { font-weight: 700; color: var(--text); border-top: 2px solid var(--border); border-bottom: 0; }
.mes.revisao .quando { color: var(--accent); }
@media print { body { background: #fff; } .guia, .alternar { display: none !important; } .doc { padding: 0; gap: 16px; } section { break-inside: avoid; } .ph { background: none; color: inherit; padding: 0; } }
.vazio { color: var(--text-3); font-style: italic; }
</style>
</head>
<body>
<main class="doc">

  <section class="capa">
    <div class="label">Nonna · Planejamento do ciclo</div>
    <h1>{{ $cliente }}</h1>
    @if(filled($content['objetivo'] ?? null))<p>{{ $content['objetivo'] }}</p>@endif
    <div class="meta">
      <div><span class="label">Período</span><b>{{ $plan ? $d($plan->period_start) . ' a ' . $d($plan->period_end) : '' }}</b></div>
      <div><span class="label">Responsável Nonna</span><b>{{ $plan?->responsible?->name ?? '—' }}</b></div>
      <div><span class="label">Atualizado em</span><b>{{ $document->updated_at->format('d/m/Y') }}</b></div>
    </div>
  </section>

  <section>
    <div class="num">01</div>
    <h2>Onde estamos e aonde vamos</h2>
    <div class="cols">
      <div class="box"><div class="label">Situação atual</div><p>{!! $txt($content['situacao'] ?? '') !!}</p></div>
      <div class="box"><div class="label">O que muda neste ciclo</div><p>{!! $txt($content['o_que_muda'] ?? '') !!}</p></div>
    </div>
    @if(filled($content['foco'] ?? null))
      <div class="foco"><span class="label">Foco do ciclo</span>{{ $content['foco'] }}</div>
    @endif
  </section>

  <section>
    <div class="num">02</div>
    <h2>Metas do ciclo</h2>
    <div class="kpis">
      @foreach($content['metas'] ?? [] as $meta)
        <div class="kpi">
          <div class="label">{{ $meta['nome'] }}</div><div class="valor">{{ $meta['alvo'] }}</div>
          <div class="linha">
            <span>Até <b>{{ $d($meta['ate']) }}</b></span>
            <span>Hoje @if($isPending($meta['hoje']))<span class="pendente">A confirmar</span>@else<b>{{ $meta['hoje'] }}</b>@endif</span>
            <span>Fonte <b>{{ $meta['fonte'] }}</b></span>
          </div>
          <div class="carrega">
            @forelse($carriers[$meta['id']] ?? [] as $item)
              @if($loop->first)Carregada por @endif<span class="chip-item {{ $item['tipo'] }}">{{ $item['nome'] }}</span>
            @empty
              <span class="sem-dono">Nenhuma campanha ou projeto carrega esta meta</span>
            @endforelse
          </div>
        </div>
      @endforeach
    </div>
  </section>

  <section>
    <div class="num">03</div>
    <h2>Como vamos fazer</h2>
    <p>{!! $txt($content['estrategia'] ?? '') !!}</p>
  </section>

  <section>
    <div class="num">04</div>
    <h2>Campanhas e projetos</h2>
    <div class="itens">
      @foreach($content['itens'] ?? [] as $item)
        @php
          $carrega = collect($item['metas'] ?? [])->map(fn ($id) => $metasById[$id]['nome'] ?? null)->filter()->implode(', ');
          $prepara = collect($item['prepara'] ?? [])->map(fn ($id) => $itensById[$id]['nome'] ?? null)->filter()->implode(', ');
        @endphp
        <div class="item">
          <div class="item-top">
            <span class="tipo {{ $item['tipo'] }}">{{ $item['tipo'] === 'projeto' ? 'Projeto' : 'Campanha' }}</span>
            <h3>{{ $item['nome'] }}</h3>
            @if($item['inicio'] || $item['fim'])<span class="datas">{{ $d($item['inicio']) }} → {{ $d($item['fim']) }}</span>@endif
          </div>
          <p>{{ $item['objetivo'] }}</p>
          <div class="medida">
            @if($item['tipo'] === 'campanha')
              <div><span class="label">Carrega</span>{{ $carrega }}</div>
              <div><span class="label">Métrica principal</span>{{ $item['metrica'] ?? '' }}</div>
            @else
              <div><span class="label">{{ $carrega ? 'Carrega' : 'Prepara o terreno para' }}</span>{{ $carrega ?: $prepara }}</div>
              <div><span class="label">Pronto quando</span>{!! $txt($item['pronto_quando'] ?? '') !!}</div>
              <div><span class="label">Benefício, conferido em {{ $d($item['beneficio_em'] ?? null) }}</span>{{ $item['beneficio'] ?? '' }}</div>
            @endif
          </div>
        </div>
      @endforeach
    </div>
    @if($budget['rows'])
      <span class="label rotulo">Verba de anúncios</span>
      <div class="rolagem">
        <table class="tabela">
          <thead><tr><th>Campanha</th><th>Plataformas</th><th>Verba mensal</th></tr></thead>
          <tbody>
            @foreach($budget['rows'] as $row)
              <tr><td>{{ $row['nome'] }}</td><td>{{ $row['plataformas'] }}</td><td class="valor">{{ $row['verba_mensal'] ? $money($row['verba_mensal']) : '' }}</td></tr>
            @endforeach
            <tr class="total"><td>Total</td><td></td><td class="valor">{{ $money($budget['total']) }}</td></tr>
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <section>
    <div class="num">05</div>
    <h2>Cronograma</h2>
    <div class="crono">
      @foreach($content['fases'] ?? [] as $fase)
        <div class="mes"><div class="quando">{{ $fase['quando'] }}</div><div><h3>{{ $fase['nome'] }}</h3><p>{{ $fase['texto'] }}</p></div></div>
      @endforeach
      <div class="mes revisao"><div class="quando">{{ $d($content['revisao_meio'] ?? null) }}</div><div><h3>Revisão do meio do ciclo</h3><p>Com o cliente: o que está no rumo e o que muda.</p></div></div>
      <div class="mes revisao"><div class="quando">{{ $d($content['fechamento'] ?? null) }}</div><div><h3>Fechamento do ciclo</h3><p>Resultado de cada meta, campanha e projeto, e o que levamos para o próximo planejamento.</p></div></div>
    </div>
  </section>

  <section>
    <div class="num">06</div>
    <h2>O que precisamos de você</h2>
    @if(empty($content['pedidos']))
      <p class="vazio">Nada pendente com você neste momento.</p>
    @else
      <ul class="precisamos">
        @foreach($content['pedidos'] as $pedido)
          <li>{{ $pedido['o_que'] }}@if($pedido['ate']), <span class="prazo">até {{ $d($pedido['ate']) }}</span>@endif</li>
        @endforeach
      </ul>
    @endif
  </section>

  <footer>Nonna Agência Digital · {{ ucfirst($document->updated_at->locale('pt_BR')->isoFormat('MMMM [de] YYYY')) }}</footer>
</main>
</body>
</html>
