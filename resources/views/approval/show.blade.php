<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Nonna Agência Digital — Do posicionamento à conversão!') }}</title>
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-150x150.png" sizes="32x32">
    <link rel="icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png" sizes="192x192">
    <link rel="apple-touch-icon" href="https://nonnaagenciadigital.com.br/wp-content/uploads/2024/02/FAVICON-300x300.png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css'])
    <style>
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; }
        .mono { font-family:Arial,"Segoe UI",Tahoma,sans-serif; }
        .label-sm { font-family:Arial,"Segoe UI",Tahoma,sans-serif; font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 6px; }

        .piece-card { background: var(--s1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 20px; }

        .preview-img { width: 100%; max-height: 460px; object-fit: contain; background: #000; display: block; }

        {{-- Material + Comentários: empilhado (material primeiro) em telas pequenas;
             lado a lado a partir de tablet/desktop. --}}
        .approval-media-row .media-col { min-width: 0; }
        @media (min-width: 768px) {
            .approval-media-row { display: flex; gap: 20px; align-items: flex-start; }
            .approval-media-row .media-col { flex: 1 1 60%; }
            .approval-media-row .side-col { flex: 1 1 40%; }
        }

        .comment-item { padding: 10px 0; }
        .comment-item + .comment-item { border-top: 1px solid var(--border); }
        .comment-author { font-size: 12px; font-weight: 700; color: var(--text); }
        .comment-date { font-size: 10px; color: var(--muted); margin-left: 6px; }
        .comment-body { font-size: 13px; color: var(--text); line-height: 1.6; white-space: pre-wrap; margin: 4px 0 0; }

        .btn-decision { flex: 1; padding: 12px 10px; font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 700; border: 2px solid var(--border2); cursor: pointer; transition: all .15s; background: transparent; color: var(--muted2); }
        .btn-decision.sel-approve  { border-color: #22c55e; background: rgba(34,197,94,.12); color: #22c55e; }
        .btn-decision.sel-changes  { border-color: var(--orange); background: rgba(238, 121, 25,.12); color: var(--orange); }

        .textarea-pub { width: 100%; padding: 10px 14px; font-size: 13px; font-family: 'Inter', sans-serif; background: var(--s2); border: 1px solid var(--border2); color: var(--text); resize: vertical; outline: none; min-height: 80px; box-sizing: border-box; }
        .textarea-pub:focus { border-color: var(--purple); }
        .textarea-pub::placeholder { color: var(--muted); }

        .file-link { display: flex; align-items: center; gap: 10px; padding: 14px; background: var(--s2); border: 1px solid var(--border); color: var(--text); text-decoration: none; font-size: 13px; }
        .file-link:hover { border-color: var(--purple); }
        .file-link + .file-link { border-top: 1px solid var(--border); }

        .badge { font-family:Arial,"Segoe UI",Tahoma,sans-serif; font-size: 9px; letter-spacing: .1em; padding: 3px 8px; }
        .badge-pending  { background: rgba(100,100,130,.2); color: var(--muted); }
        .badge-approved { background: rgba(34,197,94,.15); color: #22c55e; }
        .badge-changes  { background: rgba(238, 121, 25,.15); color: var(--orange); }

        .approvers-box { background: var(--s1); border: 1px solid var(--border); padding: 16px 18px; margin-bottom: 24px; }
        .approver-row { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; font-size: 13px; }
        .approver-row + .approver-row { border-top: 1px solid var(--border); }
        .approver-feedback { font-size: 12px; color: var(--muted2); margin: 4px 0 0; line-height: 1.5; border-left: 2px solid var(--border2); padding-left: 10px; }

        .history-item { padding: 12px 0; }
        .history-item + .history-item { border-top: 1px solid var(--border); }

        .submit-btn { width: 100%; padding: 16px; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 800; background: var(--purple); color: #fff; border: none; cursor: pointer; transition: opacity .15s; letter-spacing: .04em; }
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

    {{-- OUTROS JOBS DO MESMO CLIENTE NESTE MÊS — navegação, cada um com decisão própria --}}
    @if($batch->count() > 1)
    <div style="background:var(--s1); border-bottom:1px solid var(--border); padding:16px 20px">
        <div style="max-width:680px; margin:0 auto">
            <span class="label-sm" style="margin-bottom:10px">
                {{ $approvalToken->round->task->client->company_name }} · {{ ucfirst($approvalToken->round->submitted_at->translatedFormat('F')) }}
            </span>
            <div style="display:flex; gap:8px; flex-wrap:wrap">
                @foreach($batch as $i => $t)
                    @php $isCurrent = $t->id === $approvalToken->id; @endphp
                    <a href="{{ route('approval.show', $t->token) }}"
                       title="{{ $t->round->task->title }}"
                       style="display:inline-flex; align-items:center; gap:5px; padding:8px 14px; font-size:13px; font-weight:700; text-decoration:none; border:2px solid {{ $isCurrent ? 'var(--purple)' : ($t->status !== 'pending' ? '#22c55e' : 'var(--border2)') }}; background:{{ $isCurrent ? 'var(--purple)' : ($t->status !== 'pending' ? 'rgba(34,197,94,.1)' : 'transparent') }}; color:{{ $isCurrent ? '#fff' : ($t->status !== 'pending' ? '#22c55e' : 'var(--muted2)') }}">
                        @if(!$isCurrent && $t->status !== 'pending')✓@endif {{ $i + 1 }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <main style="max-width:680px; margin:0 auto; padding:32px 16px 80px">

        {{-- CONTEXTO --}}
        <div style="margin-bottom:24px">
            <span class="label-sm">{{ $approvalToken->round->isAviso() ? 'Aviso' : 'Aprovação de Material' }}</span>
            <h1 style="font-size:20px; font-weight:800; margin:0 0 4px">{{ $approvalToken->round->task->title }}</h1>
            <p style="font-size:13px; color:var(--muted); margin:0">
                {{ $approvalToken->round->task->client->company_name }}
                <span style="margin:0 8px; opacity:.3">·</span>
                Olá, <strong style="color:var(--text)">{{ $approvalToken->contact->name }}</strong>
            </p>
        </div>

        @if($approvalToken->round->notes)
        <div style="background:var(--s1); border-left:3px solid var(--purple); border-top:1px solid var(--border); border-right:1px solid var(--border); border-bottom:1px solid var(--border); padding:14px 18px; margin-bottom:24px">
            <span class="label-sm" style="margin-bottom:4px">{{ $approvalToken->round->isAviso() ? 'Mensagem' : 'Observações do time' }}</span>
            <p style="font-size:13px; color:var(--muted2); margin:0; line-height:1.6">{{ $approvalToken->round->notes }}</p>
        </div>
        @endif

        {{-- QUEM FALTA APROVAR / QUEM JÁ APROVOU (rodada atual) — não se aplica a
             aviso, que não tem decisão nenhuma a coletar. --}}
        @if($approvalToken->round->tokens->count() > 1 && !$approvalToken->round->isAviso())
        <div class="approvers-box">
            <span class="label-sm" style="margin-bottom:8px">Aprovadores desta rodada</span>
            @foreach($approvalToken->round->tokens as $t)
                <div class="approver-row">
                    <span style="{{ $t->id === $approvalToken->id ? 'font-weight:700' : '' }}">
                        {{ explode(' ', $t->contact->name)[0] }}{{ $t->id === $approvalToken->id ? ' (você)' : '' }}
                    </span>
                    <span class="badge badge-{{ $t->status === 'approved' ? 'approved' : ($t->status === 'changes_requested' ? 'changes' : 'pending') }}">
                        {{ $t->status === 'approved' ? 'APROVOU' : ($t->status === 'changes_requested' ? 'PEDIU AJUSTE' : 'AGUARDANDO') }}
                    </span>
                </div>
                @if($t->status === 'changes_requested' && $t->overall_comment)
                    <p class="approver-feedback">"{{ $t->overall_comment }}"</p>
                @endif
            @endforeach
        </div>
        @endif

        {{-- HISTÓRICO DE RODADAS ANTERIORES --}}
        @php
            $otherRounds = $approvalToken->round->task->approvalRounds
                ->where('id', '!=', $approvalToken->round_id)
                ->sortByDesc('round_number');
        @endphp
        @if($otherRounds->isNotEmpty())
        <div class="approvers-box">
            <span class="label-sm" style="margin-bottom:8px">Histórico de rodadas anteriores</span>
            @foreach($otherRounds as $r)
                <div class="history-item">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px">
                        <span style="font-size:13px; font-weight:600">Rodada #{{ $r->round_number }}</span>
                        <span class="badge badge-{{ $r->status === 'approved' ? 'approved' : ($r->status === 'changes_requested' ? 'changes' : 'pending') }}">
                            {{ $r->displayStatusLabel() }}
                        </span>
                    </div>
                    @if($r->resolved_at)
                        <p class="mono" style="font-size:10px; color:var(--muted); margin:0 0 6px">{{ $r->resolved_at->format('d/m/Y') }}</p>
                    @endif
                    @if($r->caption)
                        <p class="approver-feedback" style="font-style:italic">"{{ $r->caption }}"</p>
                    @endif
                    @foreach($r->tokens as $t)
                        @if($t->overall_comment)
                            <p class="approver-feedback">{{ explode(' ', $t->contact->name)[0] }}: "{{ $t->overall_comment }}"</p>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
        @endif

        {{-- LEGENDA — largura cheia, acima do material + comentários. --}}
        @if($approvalToken->round->caption)
        <div class="piece-card" style="padding:18px">
            <span class="label-sm">Legenda</span>
            <p style="font-size:14px; white-space:pre-wrap; margin:0; color:var(--text); line-height:1.6">{{ $approvalToken->round->caption }}</p>
        </div>
        @endif

        {{-- MATERIAL + COMENTÁRIOS — lado a lado a partir de tablet/desktop; em
             celular fica empilhado na ordem do DOM (material primeiro). Os
             comentários são os marcados como visíveis pro cliente na tarefa — é a
             "venda" da arte feita pelo designer, explicando a produção. --}}
        @if($deliverables->isNotEmpty() || $visibleComments->isNotEmpty())
        <div class="approval-media-row">
            @if($deliverables->isNotEmpty())
            <div class="media-col">
                @foreach($deliverables as $file)
                    <div class="piece-card">
                        @if($file->isImage())
                            <img src="{{ $file->url() }}" alt="{{ $file->filename }}" class="preview-img">
                        @elseif($file->isVideo())
                            <video controls preload="metadata" playsinline class="preview-img">
                                <source src="{{ $file->url() }}" type="{{ $file->mime_type }}">
                            </video>
                            <a href="{{ $file->url() }}" target="_blank" class="file-link" style="border-top:1px solid var(--border)">
                                <span style="font-size:22px">{{ $file->icon() }}</span>
                                <span>Vídeo não abre? Baixar {{ $file->filename }}</span>
                                <span style="margin-left:auto; font-size:10px; color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif">↗ abrir</span>
                            </a>
                        @else
                            <a href="{{ $file->url() }}" target="_blank" class="file-link">
                                <span style="font-size:22px">{{ $file->icon() }}</span>
                                <span>{{ $file->filename }}</span>
                                <span style="margin-left:auto; font-size:10px; color:var(--muted); font-family:Arial,"Segoe UI",Tahoma,sans-serif">↗ abrir</span>
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif

            @if($visibleComments->isNotEmpty())
            <div class="side-col">
                <div class="piece-card" style="padding:18px">
                    <span class="label-sm">Comentários</span>
                    @foreach($visibleComments as $comment)
                        @php
                            // Comentário antigo (anterior ao editor rico) é texto puro, sem tag
                            // nenhuma — converte pra <p>/<br> só na exibição (mesma lógica de
                            // tasks/show.blade.php e tiptap-editor.js:normalizeContent).
                            $commentHtml = $comment->body;
                            if (!preg_match('/<[a-z][\s\S]*>/i', $commentHtml)) {
                                $commentHtml = collect(preg_split('/\n\n+/', $commentHtml))
                                    ->map(fn ($p) => '<p>' . nl2br(e($p)) . '</p>')
                                    ->implode('');
                            }
                        @endphp
                        <div class="comment-item">
                            <span class="comment-author">{{ $comment->commenter()?->name ?? '—' }}</span>
                            <span class="comment-date">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                            <div class="comment-body">{!! $commentHtml !!}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif

        @if($errors->any())
        <div style="border:1px solid rgba(239,68,68,.3); background:rgba(239,68,68,.06); padding:12px 16px; margin-bottom:20px">
            <ul style="margin:0; padding-left:16px; font-size:13px; color:#ef4444">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- DECISÃO ÚNICA PRA TUDO (material + legenda juntos) — aviso é só
             informativo, não pede decisão nenhuma do cliente. --}}
        @if($approvalToken->round->isAviso())
        <div style="background:var(--s1); border:1px solid var(--border); padding:16px 18px; text-align:center">
            <p style="font-size:13px; color:var(--muted2); margin:0">Isso é só um aviso — nenhuma ação é necessária da sua parte.</p>
        </div>
        @else
        <div x-data="{ decision: null }">
            <form method="POST" action="{{ route('approval.submit', $approvalToken->token) }}">
                @csrf
                <input type="hidden" name="decision" x-model="decision">

                <span class="label-sm">Sua avaliação</span>
                <div style="display:flex; gap:8px; margin-bottom:14px">
                    <button type="button" class="btn-decision"
                            :class="decision === 'approved' ? 'sel-approve' : ''"
                            @click="decision = 'approved'">
                        ✓ Aprovar Item
                    </button>
                    <button type="button" class="btn-decision"
                            :class="decision === 'changes_requested' ? 'sel-changes' : ''"
                            @click="decision = 'changes_requested'">
                        ✎ Solicitar Ajuste
                    </button>
                </div>

                <div style="background:var(--s1); border:1px solid var(--border); padding:18px; margin-bottom:20px">
                    <span class="label-sm">
                        Comentário
                        <span x-show="decision === 'changes_requested'" style="color:var(--orange)">(obrigatório)</span>
                        <span x-show="decision !== 'changes_requested'" style="color:var(--muted); font-size:8px">(opcional)</span>
                    </span>
                    <textarea class="textarea-pub" name="comment"
                              :required="decision === 'changes_requested'"
                              placeholder="Descreva o que precisa ajustar..."
                              rows="3"></textarea>
                </div>

                <button type="submit" class="submit-btn" :disabled="decision === null">
                    Enviar Avaliação
                </button>
            </form>
        </div>
        @endif

    </main>

</body>
</html>
