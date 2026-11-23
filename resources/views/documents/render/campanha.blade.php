{{-- Versão do cliente do Documento de campanha em campos (CampanhaSchema). Mesmo visual do modelo
     (nonna-campanha-modelo.html), sem instruções. Nome do cliente = razão social (apelido é só interno). --}}
@php
    $S        = \App\Services\Documents\Schemas\CampanhaSchema::class;
    $project  = $owner instanceof \App\Models\Project ? $owner : null;
    $plan     = $project?->macroPlan;
    $cliente  = $document->client?->company_name ?? '';
    $angulos  = collect($content['angulos'] ?? [])->keyBy('id');
    $d        = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '';
    $txt      = fn ($v) => nl2br(e((string) $v));
    $lines    = fn ($v) => array_values(array_filter(array_map('trim', explode("\n", (string) $v))));
    $money    = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $tipos    = ['venda' => 'Venda', 'marca' => 'Marca', 'lancamento' => 'Lançamento', 'relacionamento' => 'Relacionamento'];
    $midiaTot = array_sum(array_map(fn ($m) => (float) ($m['verba_mensal'] ?? 0), $content['midia'] ?? []));
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Campanha · {{ $content['nome'] ?? '' }} · {{ $cliente }}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=Fraunces:opsz,wght@9..144,300;9..144,400&display=swap" rel="stylesheet">
<style>
/* Visual do modelo .claude/docs/templates/nonna-campanha-modelo.html — manter igual. */
:root {
  --bg: #F6F7FB; --surface: #FFFFFF; --surface-2: #F1F3F8;
  --brand: #643B8E; --brand-soft: #F6F1FA; --accent: #EE7919; --accent-soft: #FFF7ED;
  --text: #111827; --text-2: #4D5768; --text-3: #697386; --border: #E2E5ED;
  --warning: #B45309; --warning-soft: #FEF3C7;
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font: 400 15px/1.6 Inter, system-ui, sans-serif; }
.doc { max-width: 860px; margin: 0 auto; padding: 40px 20px 80px; display: flex; flex-direction: column; gap: 28px; }
section { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
.label { font: 600 11px/1.3 "IBM Plex Mono", monospace; letter-spacing: .1em; text-transform: uppercase; color: var(--brand); }
.num { font: 600 11px "IBM Plex Mono", monospace; color: var(--text-3); margin-bottom: 4px; }
h1 { font: 300 clamp(28px, 6vw, 40px)/1.1 Fraunces, Georgia, serif; letter-spacing: -.02em; margin: 6px 0 8px; }
h2 { font: 700 21px/1.25 Inter, sans-serif; letter-spacing: -.015em; margin: 0 0 14px; }
h3 { font: 600 16px/1.3 Inter, sans-serif; margin: 0 0 6px; }
p { margin: 0 0 10px; color: var(--text-2); }
ul { margin: 6px 0 0; padding-left: 20px; color: var(--text-2); }

/* Campo a preencher e pendências */
.ph { background: var(--accent-soft); color: #9A4A0B; border-radius: 4px; padding: 0 4px; }
.pendente { display: inline-block; background: var(--warning-soft); color: var(--warning); font: 600 12px Inter, sans-serif; padding: 2px 8px; border-radius: 6px; }

/* Instruções de preenchimento (somem ao imprimir ou pelo botão) */
.guia { border: 1px dashed #C9B6DD; background: #FBF8FD; border-radius: 10px; padding: 12px 14px; margin: 0 0 16px; font-size: 13.5px; color: var(--text-2); }
.guia b { color: var(--brand); }
.guia .label { display: block; margin-bottom: 4px; }
body.sem-guia .guia { display: none; }
.alternar { position: fixed; right: 16px; bottom: 16px; background: var(--brand); color: #fff; border: 0; border-radius: 999px; padding: 10px 16px; font: 600 13px Inter, sans-serif; cursor: pointer; box-shadow: 0 4px 14px rgba(100,59,142,.3); }

/* Capa */
.capa { background: linear-gradient(135deg, var(--brand), #8A4FBF); color: #fff; border: 0; padding: 36px 28px; }
.capa .label { color: rgba(255,255,255,.75); }
.capa p { color: rgba(255,255,255,.9); }
.capa .ph { background: rgba(255,255,255,.18); color: #fff; }
.ficha { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 20px; }
.ficha div { background: rgba(255,255,255,.12); border-radius: 10px; padding: 10px 14px; }
.ficha b { display: block; font-weight: 600; color: #fff; font-variant-numeric: tabular-nums; }

/* 01 Objetivo e como medir */
.objetivo { background: var(--brand-soft); border-radius: 12px; padding: 20px; font: 300 clamp(19px, 3.4vw, 24px)/1.35 Fraunces, Georgia, serif; color: var(--text); margin-bottom: 16px; }
.metrica { border: 2px solid var(--brand); border-radius: 12px; padding: 18px; margin: 16px 0; }
.metrica .nome { font: 700 18px/1.3 Inter, sans-serif; color: var(--text); margin: 4px 0 12px; }
.metrica .campos { display: grid; grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)); gap: 10px; }
.metrica .campos div { background: var(--surface-2); border-radius: 8px; padding: 10px 12px; }
.metrica .campos b { display: block; color: var(--text); font-weight: 600; font-variant-numeric: tabular-nums; margin-top: 2px; }
.semanal { display: flex; gap: 12px; align-items: flex-start; background: var(--accent-soft); border-radius: 10px; padding: 14px 16px; margin-top: 14px; }
.semanal .label { color: var(--accent); white-space: nowrap; padding-top: 2px; }

/* Blocos */
.cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; }
.box { background: var(--surface-2); border-radius: 10px; padding: 14px 16px; min-width: 0; }
.box .label { margin-bottom: 6px; display: block; }
.box p:last-child { margin-bottom: 0; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.card { border: 1px solid var(--border); border-radius: 10px; padding: 16px; }
.card .label { margin-bottom: 6px; display: block; }
.angulo h3 { color: var(--brand); }
.angulo dl { margin: 8px 0 0; }
.angulo dt { font: 600 11px "IBM Plex Mono", monospace; letter-spacing: .08em; text-transform: uppercase; color: var(--text-3); margin-top: 8px; }
.angulo dd { margin: 2px 0 0; color: var(--text-2); }
.angulo dd ul { margin: 2px 0 0; padding-left: 18px; }
.angulo .msg { font: 400 17px/1.35 Fraunces, Georgia, serif; color: var(--text); margin: 6px 0 4px; }
.tipo-ang { display: inline-block; background: var(--accent-soft); color: var(--accent); font: 600 10px "IBM Plex Mono", monospace; letter-spacing: .1em; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; margin-bottom: 8px; }
.tipo-ang .ph { background: none; color: inherit; padding: 0; }
.tipo-ang.momento { background: var(--brand-soft); color: var(--brand); }
.hero { background: var(--brand-soft); border-radius: 12px; padding: 24px; font: 300 clamp(26px,4.5vw,36px)/1.2 Fraunces, Georgia, serif; color: var(--text); margin-bottom: 14px; }
.frente { border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px 6px; margin-bottom: 12px; }
.frente > .label { display: block; margin-bottom: 2px; }
.rotulo { display: block; margin-bottom: 6px; }
.passo { display: grid; grid-template-columns: 32px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px dashed var(--border); }
.passo:last-child { border-bottom: 0; }
.passo .n { width: 28px; height: 28px; border-radius: 50%; background: var(--brand); color: #fff; display: grid; place-items: center; font: 600 13px Inter, sans-serif; }
.verba-bar { display: flex; height: 34px; border-radius: 8px; overflow: hidden; margin: 10px 0 6px; font: 600 12px Inter, sans-serif; color: #fff; }
.verba-bar div { display: flex; align-items: center; padding: 0 10px; }
.chip { display: inline-block; background: var(--brand-soft); color: var(--brand); font: 500 12px "IBM Plex Mono", monospace; padding: 3px 10px; border-radius: 999px; margin: 0 6px 6px 0; }

/* Tabelas */
.rolagem { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; font: 600 11px "IBM Plex Mono", monospace; text-transform: uppercase; color: var(--text-3); padding: 8px; border-bottom: 1px solid var(--border); }
td { padding: 10px 8px; border-bottom: 1px solid var(--border); color: var(--text-2); vertical-align: top; }
tr:last-child td { border-bottom: 0; }
td.data { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text); }
tr.total td { font-weight: 700; color: var(--text); border-top: 2px solid var(--border); }

/* 10 Acompanhamento e fechamento */
.lista-dupla { display: grid; grid-template-columns: 200px 1fr; }
.lista-dupla div { padding: 9px 0; border-bottom: 1px dashed var(--border); }
.lista-dupla div:nth-last-child(-n+2) { border-bottom: 0; }
.lista-dupla .chave { font-weight: 600; color: var(--text); padding-right: 14px; }
.fechamento { border: 1px dashed var(--border); border-radius: 12px; padding: 18px; margin-top: 18px; background: var(--surface-2); }
.fechamento h3 { margin-bottom: 10px; }
.veredito { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.veredito span { border: 1px solid var(--border); background: var(--surface); border-radius: 999px; padding: 4px 12px; font: 600 12px Inter, sans-serif; color: var(--text-2); }

footer { text-align: center; font: 500 12px "IBM Plex Mono", monospace; color: var(--text-3); }
@media (max-width: 520px) {
  section { padding: 20px 16px; }
  .lista-dupla { grid-template-columns: 1fr; }
  .lista-dupla .chave { border-bottom: 0 !important; padding-bottom: 0; }
  .semanal { flex-direction: column; gap: 4px; }
}
@media print {
  body { background: #fff; }
  .guia, .alternar { display: none !important; }
  .doc { padding: 0; gap: 16px; }
  section { break-inside: avoid; }
  .ph { background: none; color: inherit; padding: 0; }
}
.vazio { color: var(--text-3); font-style: italic; }
</style>
</head>
<body>
<main class="doc">

  <section class="capa">
    <div class="label">Nonna · Campanha</div>
    <h1>{{ $content['nome'] ?? '' }}</h1>
    @if(filled($content['objetivo'] ?? null))<p>{{ $content['objetivo'] }}</p>@endif
    <div class="ficha">
      <div><span class="label">Cliente</span><b>{{ $cliente }}</b></div>
      <div><span class="label">Tipo</span><b>{{ $tipos[$content['tipo_campanha'] ?? ''] ?? '' }}</b></div>
      <div><span class="label">Vai ao ar</span><b>{{ $d($content['inicio'] ?? null) }}</b></div>
      <div><span class="label">Fim</span><b>{{ $d($content['fim'] ?? null) }}</b></div>
      <div><span class="label">Peças prontas</span><b>{{ $d($content['entrega_pecas'] ?? null) }}</b></div>
      <div><span class="label">Planejamento de origem</span><b>{{ $plan ? $plan->periodLabel() : 'Avulsa' }}</b></div>
    </div>
  </section>

  <section>
    <div class="num">01</div>
    <h2>Objetivo e como vamos medir</h2>
    <span class="label rotulo">Objetivo</span>
    <div class="objetivo">{{ $content['objetivo'] ?? '' }}</div>
    <div class="cols">
      <div class="box"><span class="label">Tipo de campanha</span><p>{{ $tipos[$content['tipo_campanha'] ?? ''] ?? '' }}</p></div>
      <div class="box"><span class="label">Meta do planejamento que ela ajuda a bater</span><p>{{ $content['meta_planejamento'] ?? '' }}</p></div>
    </div>
    <div class="metrica">
      <span class="label">Como vamos saber se deu certo</span>
      <div class="nome">{{ $content['metrica'] ?? '' }}</div>
      <div class="campos">
        <div><span class="label">Quanto é hoje</span><b>{{ $content['metrica_hoje'] ?? '' }}</b></div>
        <div><span class="label">Quanto queremos</span><b>{{ $content['metrica_meta'] ?? '' }}@if(!empty($content['metrica_ate'])) · até {{ $d($content['metrica_ate']) }}@endif</b></div>
        <div><span class="label">Onde medimos</span><b>{{ $content['metrica_fonte'] ?? '' }}</b></div>
        <div><span class="label">Quem lança o número</span><b>{{ $S::ENTRADAS[$content['metrica_entrada'] ?? ''] ?? '' }}</b></div>
      </div>
    </div>
    @if(!empty($content['apoio']))
      <div class="rolagem">
        <table>
          <thead><tr><th>Número de apoio</th><th>Quanto é hoje</th><th>Quanto queremos</th><th>Onde medimos</th><th>Quem lança</th></tr></thead>
          <tbody>
            @foreach($content['apoio'] as $a)
              <tr><td>{{ $a['nome'] }}</td><td>{{ $a['hoje'] }}</td><td>{{ $a['meta'] }}</td><td>{{ $a['fonte'] }}</td><td>{{ $S::ENTRADAS[$a['entrada'] ?? ''] ?? '' }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <section>
    <div class="num">02</div>
    <h2>Ângulos criativos</h2>
    <div class="cards">
      @foreach($content['angulos'] ?? [] as $a)
        <div class="card angulo">
          @if($a['motivo'] ?? null)<span class="tipo-ang">{{ $S::MOTIVOS[$a['motivo']] ?? '' }}</span>@endif
          @if($a['momento'] ?? null)<span class="tipo-ang momento">{{ $S::MOMENTOS[$a['momento']] ?? '' }}</span>@endif
          <h3>{{ $a['nome'] }}</h3>
          <p class="msg">{{ $a['mensagem'] }}</p>
          <dl>
            <dt>Para quem</dt><dd>{{ $a['para_quem'] }}</dd>
            <dt>Ganchos</dt><dd><ul>@foreach($lines($a['ganchos']) as $g)<li>{{ $g }}</li>@endforeach</ul></dd>
            <dt>Formatos</dt><dd>{{ $a['formatos'] }}</dd>
          </dl>
        </div>
      @endforeach
    </div>
  </section>

  <section>
    <div class="num">03</div>
    <h2>Conceito e racional</h2>
    <span class="label rotulo">A grande ideia</span>
    <div class="hero">{{ $content['frase_conceito'] ?? '' }}</div>
    <p>{!! $txt($content['conceito'] ?? '') !!}</p>
    <span class="label">Por que deve funcionar</span>
    <p>{!! $txt($content['por_que'] ?? '') !!}</p>
  </section>

  <section>
    <div class="num">04</div>
    <h2>Mecânica</h2>
    @foreach($content['frentes'] ?? [] as $f)
      <div class="frente">
        <span class="label">Frente</span>
        <h3>{{ $f['nome'] }}</h3>
        @foreach($lines($f['passos']) as $n => $passo)
          @php [$titulo, $resto] = array_pad(array_map('trim', preg_split('/:|—| - /u', $passo, 2)), 2, ''); @endphp
          <div class="passo"><span class="n">{{ $n + 1 }}</span><div><h3>{{ $titulo }}</h3>@if($resto)<p>{{ $resto }}</p>@endif</div></div>
        @endforeach
      </div>
    @endforeach
    @if(filled($content['encontro'] ?? null))<p>{!! $txt($content['encontro']) !!}</p>@endif
  </section>

  <section>
    <div class="num">05</div>
    <h2>Desdobramento por disciplina</h2>
    <div class="cards">
      @foreach($content['disciplinas'] ?? [] as $di)
        <div class="card"><span class="label">{{ $di['area'] }}</span><p>{!! $txt($di['o_que']) !!}</p></div>
      @endforeach
    </div>
  </section>

  <section>
    <div class="num">06</div>
    <h2>Tráfego pago</h2>
    @if(empty($content['midia']))
      <p>Esta campanha não tem tráfego pago.</p>
    @else
      <div class="rolagem">
        <table>
          <thead><tr><th>Onde anuncia</th><th>Para quê</th><th>Verba por mês</th></tr></thead>
          <tbody>
            @foreach($content['midia'] as $m)
              <tr><td>{{ $m['plataforma'] }}</td><td>{{ $m['para'] }}</td><td class="data">{{ $m['verba_mensal'] ? $money($m['verba_mensal']) : '' }}</td></tr>
            @endforeach
            <tr class="total"><td>Total</td><td></td><td class="data">{{ $money($midiaTot) }}</td></tr>
          </tbody>
        </table>
      </div>
      @if(filled($content['publico'] ?? null))<p style="margin-top:12px">{!! $txt($content['publico']) !!}</p>@endif
      @if(filled($content['regioes'] ?? null))
        <div>@foreach(array_filter(array_map('trim', explode(',', $content['regioes']))) as $r)<span class="chip">{{ $r }}</span>@endforeach</div>
      @endif
    @endif
  </section>

  <section>
    <div class="num">07</div>
    <h2>Peças e datas</h2>
    <div class="rolagem">
      <table>
        <thead><tr><th>Peça</th><th>Ângulo criativo</th><th>Formato</th><th>Quantas</th><th>Pronta até</th></tr></thead>
        <tbody>
          @foreach($content['pecas'] ?? [] as $p)
            <tr><td>{{ $p['nome'] }}</td><td>{{ $angulos[$p['angulo'] ?? '']['nome'] ?? '—' }}</td><td>{{ $p['formato'] }}</td><td>{{ $p['quantidade'] }}</td><td class="data">{{ $d($p['data']) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <div class="num">08</div>
    <h2>Sinais de alerta</h2>
    <div class="rolagem">
      <table>
        <thead><tr><th>Se acontecer</th><th>O que fazemos</th><th>Quem age</th></tr></thead>
        <tbody>
          @foreach($content['alertas'] ?? [] as $al)
            <tr><td>{{ $al['se'] }}</td><td>{{ $al['fazemos'] }}</td><td>{{ $al['quem'] }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

  <section>
    <div class="num">09</div>
    <h2>Acompanhamento e fechamento</h2>
    <div class="cols">
      <div class="box"><span class="label">Quem acompanha</span><p>{{ $content['quem_acompanha'] ?? '' }}</p></div>
      @php
        $checkin = ($content['checkin_dias'] ?? null) ? 'A cada ' . $content['checkin_dias'] . ' dias' : '';
        if ($checkin && ($content['checkin_inicio'] ?? null)) { $checkin .= ', a partir de ' . $d($content['checkin_inicio']); }
      @endphp
      <div class="box"><span class="label">Check-in</span><p>{{ $checkin }}</p></div>
      <div class="box"><span class="label">Fechamento</span><p>{{ $d($content['fechamento'] ?? null) }}</p></div>
    </div>
    @if(filled($content['resultado'] ?? null))
      @php $res = ['atingiu' => 'Atingiu', 'parcial' => 'Parcial', 'nao_atingiu' => 'Não atingiu'][$content['resultado']] ?? ''; @endphp
      <span class="label rotulo" style="margin-top:16px">Resultado: {{ $res }}</span>
      <div class="cols">
        <div class="box"><span class="label">O que aconteceu</span><p>{!! $txt($content['aconteceu'] ?? '') !!}</p></div>
        <div class="box"><span class="label">Por que houve diferença</span><p>{!! $txt($content['diferenca'] ?? '') !!}</p></div>
        <div class="box"><span class="label">Ângulo criativo que funcionou melhor</span><p>{!! $txt($content['angulo_melhor'] ?? '') !!}</p></div>
        <div class="box"><span class="label">O que levamos para o próximo ciclo</span><p>{!! $txt($content['levamos'] ?? '') !!}</p></div>
      </div>
    @endif
  </section>

  <footer>Nonna Agência Digital · {{ ucfirst($document->updated_at->locale('pt_BR')->isoFormat('MMMM [de] YYYY')) }}</footer>
</main>
</body>
</html>
