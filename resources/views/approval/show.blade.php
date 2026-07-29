<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprovação de Material · Nonna</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:400,600,700,800|ibm-plex-mono:400,500&display=swap" rel="stylesheet"/>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Syne', sans-serif; min-height: 100vh; }
        .mono { font-family: 'IBM Plex Mono', monospace; }
        .label-sm { font-family: 'IBM Plex Mono', monospace; font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 6px; }

        .piece-card { background: var(--s1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 20px; transition: border-color .2s; }
        .piece-card.decided { border-color: var(--border2); }
        .piece-header { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
        .piece-body { padding: 18px; }

        .preview-img { width: 100%; max-height: 460px; object-fit: contain; background: #000; display: block; border-bottom: 1px solid var(--border); }

        .btn-decision { flex: 1; padding: 12px 10px; font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; border: 2px solid var(--border2); cursor: pointer; transition: all .15s; background: transparent; color: var(--muted2); }
        .btn-decision.sel-approve  { border-color: #22c55e; background: rgba(34,197,94,.12); color: #22c55e; }
        .btn-decision.sel-changes  { border-color: var(--orange); background: rgba(255,140,0,.12); color: var(--orange); }

        .textarea-pub { width: 100%; padding: 10px 14px; font-size: 13px; font-family: 'Syne', sans-serif; background: var(--s2); border: 1px solid var(--border2); color: var(--text); resize: vertical; outline: none; min-height: 80px; box-sizing: border-box; }
        .textarea-pub:focus { border-color: var(--purple); }
        .textarea-pub::placeholder { color: var(--muted); }

        .file-link { display: flex; align-items: center; gap: 10px; padding: 14px; background: var(--s2); border: 1px solid var(--border); color: var(--text); text-decoration: none; font-size: 13px; }
        .file-link:hover { border-color: var(--purple); }

        .badge { font-family: 'IBM Plex Mono', monospace; font-size: 9px; letter-spacing: .1em; padding: 3px 8px; }
        .badge-pending  { background: rgba(100,100,130,.2); color: var(--muted); }
        .badge-approved { background: rgba(34,197,94,.15); color: #22c55e; }
        .badge-changes  { background: rgba(255,140,0,.15); color: var(--orange); }

        .submit-btn { width: 100%; padding: 16px; font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 800; background: var(--purple); color: #fff; border: none; cursor: pointer; transition: opacity .15s; letter-spacing: .04em; }
        .submit-btn:disabled { opacity: .3; cursor: not-allowed; }
        .submit-btn:not(:disabled):hover { opacity: .88; }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body>

    <header style="background:rgba(12,12,18,.95); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); height:56px; display:flex; align-items:center; justify-content:space-between; padding:0 20px; position:sticky; top:0; z-index:10">
        <img src="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/Nonna-Horizontal-Mescla-Roxo-1024x294.png"
             alt="Nonna" style="height:20px"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
        <span style="font-weight:800; font-size:15px; display:none">nonna</span>
        <span class="mono" style="font-size:10px; color:var(--muted)">Rodada #{{ $approvalToken->round->round_number }}</span>
    </header>

    <main style="max-width:680px; margin:0 auto; padding:32px 16px 80px">

        {{-- CONTEXTO --}}
        <div style="margin-bottom:24px">
            <span class="label-sm">Aprovação de Material</span>
            <h1 style="font-size:20px; font-weight:800; margin:0 0 4px">{{ $approvalToken->round->task->title }}</h1>
            <p style="font-size:13px; color:var(--muted); margin:0">
                {{ $approvalToken->round->task->client->company_name }}
                <span style="margin:0 8px; opacity:.3">·</span>
                Olá, <strong style="color:var(--text)">{{ $approvalToken->contact->name }}</strong>
            </p>
        </div>

        @if($approvalToken->round->notes)
        <div style="background:var(--s1); border-left:3px solid var(--purple); border-top:1px solid var(--border); border-right:1px solid var(--border); border-bottom:1px solid var(--border); padding:14px 18px; margin-bottom:24px">
            <span class="label-sm" style="margin-bottom:4px">Observações do time</span>
            <p style="font-size:13px; color:var(--muted2); margin:0; line-height:1.6">{{ $approvalToken->round->notes }}</p>
        </div>
        @endif

        @if($deliverables->isEmpty())
            <p style="text-align:center; color:var(--muted); padding:48px 0">Nenhum arquivo disponível para avaliação.</p>
        @else

        @if($errors->any())
        <div style="border:1px solid rgba(239,68,68,.3); background:rgba(239,68,68,.06); padding:12px 16px; margin-bottom:20px">
            <ul style="margin:0; padding-left:16px; font-size:13px; color:#ef4444">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- FORM COM ESTADO CENTRALIZADO --}}
        <form method="POST"
              action="{{ route('approval.submit', $approvalToken->token) }}"
              x-data="{
                  decisions: {},
                  get pending() { return {{ $deliverables->count() }} - Object.keys(this.decisions).length; },
                  decide(idx, val) { this.decisions[idx] = val; }
              }">
            @csrf

            @foreach($deliverables as $idx => $file)
            <div class="piece-card" :class="decisions[{{ $idx }}] ? 'decided' : ''">

                <div class="piece-header">
                    <span style="font-size:22px; line-height:1">{{ $file->icon() }}</span>
                    <div style="flex:1; min-width:0">
                        <p style="margin:0; font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis">{{ $file->filename }}</p>
                        <p class="mono" style="margin:0; font-size:10px; color:var(--muted)">{{ $file->sizeForHumans() }}</p>
                    </div>
                    <span class="badge badge-pending"  x-show="!decisions[{{ $idx }}]">PENDENTE</span>
                    <span class="badge badge-approved" x-show="decisions[{{ $idx }}] === 'approved'" x-cloak>APROVADO</span>
                    <span class="badge badge-changes"  x-show="decisions[{{ $idx }}] === 'changes_requested'" x-cloak>AJUSTES</span>
                </div>

                @if($file->isImage())
                    <img src="{{ $file->url() }}" alt="{{ $file->filename }}" class="preview-img">
                @else
                    <div style="padding:14px 18px; border-bottom:1px solid var(--border)">
                        <a href="{{ $file->url() }}" target="_blank" class="file-link">
                            <span style="font-size:22px">{{ $file->icon() }}</span>
                            <span>{{ $file->filename }}</span>
                            <span style="margin-left:auto; font-size:10px; color:var(--muted); font-family:'IBM Plex Mono',monospace">↗ abrir</span>
                        </a>
                    </div>
                @endif

                <div class="piece-body">
                    <input type="hidden" name="feedbacks[{{ $idx }}][attachment_id]" value="{{ $file->id }}">
                    <input type="hidden" name="feedbacks[{{ $idx }}][status]" :value="decisions[{{ $idx }}] ?? ''">

                    <span class="label-sm">Sua avaliação</span>
                    <div style="display:flex; gap:8px; margin-bottom:14px">
                        <button type="button" class="btn-decision"
                                :class="decisions[{{ $idx }}] === 'approved' ? 'sel-approve' : ''"
                                @click="decide({{ $idx }}, 'approved')">
                            ✓ Aprovar
                        </button>
                        <button type="button" class="btn-decision"
                                :class="decisions[{{ $idx }}] === 'changes_requested' ? 'sel-changes' : ''"
                                @click="decide({{ $idx }}, 'changes_requested')">
                            ✎ Solicitar Ajustes
                        </button>
                    </div>

                    <div x-show="decisions[{{ $idx }}] === 'changes_requested'" x-cloak>
                        <span class="label-sm">Descreva os ajustes <span style="color:var(--orange)">*</span></span>
                        <textarea class="textarea-pub"
                                  name="feedbacks[{{ $idx }}][comment]"
                                  placeholder="Ex: Alterar a cor do botão, ajustar o texto do título..."
                                  rows="3"></textarea>
                    </div>
                </div>
            </div>
            @endforeach

            {{-- COMENTÁRIO GERAL --}}
            <div style="background:var(--s1); border:1px solid var(--border); padding:18px; margin-bottom:24px">
                <span class="label-sm">Comentário geral <span style="color:var(--muted); font-size:8px">(opcional)</span></span>
                <textarea class="textarea-pub" name="overall_comment"
                          placeholder="Observações gerais sobre o material..."
                          rows="3"></textarea>
            </div>

            {{-- PENDING INDICATOR --}}
            <div x-show="pending > 0" x-cloak style="text-align:center; margin-bottom:12px">
                <p class="mono" style="font-size:11px; color:var(--muted)">
                    Avalie todas as peças para enviar
                    (<span x-text="pending"></span> restante(s))
                </p>
            </div>

            <button type="submit" class="submit-btn" :disabled="pending > 0">
                Enviar Avaliação
            </button>

        </form>
        @endif

    </main>

</body>
</html>
