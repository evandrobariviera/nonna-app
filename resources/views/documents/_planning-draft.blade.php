{{-- Rascunho do planejamento pelo Agente Nonna (PlanningDrafter / DraftPlanningDocumentJob).
     Recebe: $macroplan, $document, $draft (state, canDraft, sources, perguntas — MacroPlanController::edit). --}}
@php
    $state  = $draft['state'] ?? null;
    $status = $state['status'] ?? null;
@endphp

@if($status === 'processando')
    <div class="card mb-3 px-5 py-4 flex items-center gap-3" style="border:1px solid rgba(100,59,142,.35); background:rgba(100,59,142,.05)"
         x-data x-init="const t = setInterval(async () => {
             try { const r = await fetch(@js(route('macroplans.field-document.draft-status', $macroplan)), { headers: { Accept: 'application/json' } });
                   const d = await r.json(); if (d.status !== 'processando') { clearInterval(t); location.reload(); } } catch (e) {}
         }, 5000)">
        <span class="inline-block w-4 h-4 rounded-full animate-spin flex-shrink-0" style="border:2px solid rgba(100,59,142,.25); border-top-color:var(--purple)"></span>
        <div>
            <p class="text-sm font-semibold" style="color:var(--text)">O Agente Nonna está montando o rascunho…</p>
            <p class="text-xs mt-0.5" style="color:var(--muted)">Leva de 1 a 3 minutos. A página atualiza sozinha; se sair, você recebe aviso no sino.</p>
        </div>
    </div>
@endif

@if($draft['canDraft'] ?? false)
    <div class="card mb-3 overflow-hidden" x-data="{ open: {{ $status === 'pronto' ? 'false' : 'true' }}, files: [] }"
         style="border:1px solid rgba(100,59,142,.35)">
        <button type="button" class="w-full px-5 py-3 flex items-center justify-between gap-3 text-left" @click="open = !open"
                style="background:rgba(100,59,142,.05)">
            <span class="flex items-center gap-2">
                <x-icon name="sparkles" size="14" style="color:var(--purple)" />
                <span class="text-sm font-semibold" style="color:var(--text)">{{ $status === 'pronto' ? 'Montar o rascunho de novo' : 'Montar rascunho com o Agente Nonna' }}</span>
            </span>
            <span class="text-xs" style="color:var(--muted)" x-text="open ? 'Fechar' : 'Abrir'"></span>
        </button>
        <form x-show="open" method="POST" action="{{ route('macroplans.field-document.draft', $macroplan) }}" enctype="multipart/form-data"
              class="px-5 py-4 space-y-4" style="border-top:1px solid var(--border2)"
              @submit.prevent="if (await $store.confirmDialog.ask('O agente vai ler as fontes marcadas e preencher o documento. Leva de 1 a 3 minutos e gasta em torno de 100 a 200 créditos seus. Começar?')) $el.submit()">
            @csrf
            @if($status === 'erro')
                <p class="text-xs px-3 py-2" style="background:rgba(239,68,68,.06); border:1px solid rgba(239,68,68,.25); color:var(--red)">
                    A última tentativa não deu certo: {{ $state['error'] ?? 'erro desconhecido' }}
                </p>
            @endif
            @if($status === 'pronto')
                <p class="text-xs" style="color:var(--muted)">Montar de novo troca o rascunho atual. Só dá enquanto ninguém editou o documento.</p>
            @endif
            <p class="text-xs" style="color:var(--muted); max-width:80ch">
                Marque o que o agente deve ler. Ele lê tudo uma vez, preenche as seções e deixa perguntas sobre o que faltou.
                Número que ninguém falou fica "A confirmar": ele não inventa.
            </p>

            @forelse(collect($draft['sources'])->groupBy('group') as $group => $items)
                <div>
                    <p class="text-[11px] font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.06em">{{ $group }}</p>
                    <div class="space-y-1">
                        @foreach($items as $src)
                            <label class="flex items-start gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="sources[]" value="{{ $src['key'] }}" @checked($src['default']) class="mt-1">
                                <span><span style="color:var(--text)">{{ $src['label'] }}</span>
                                    <span class="text-xs" style="color:var(--muted)"> · {{ $src['detail'] }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-xs" style="color:var(--muted)">Não achei reunião com ATA ou transcrição, planejamento anterior nem briefing deste cliente. Envie os arquivos abaixo.</p>
            @endforelse

            <div>
                <p class="text-[11px] font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.06em">Mais arquivos (opcional)</p>
                <input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.md,.html,.htm,.png,.jpg,.jpeg,.webp"
                       class="text-xs" style="color:var(--muted)" @change="files = [...$event.target.files].map(f => f.name)">
                <p class="text-xs mt-1" style="color:var(--muted2)">PDF, Word, PowerPoint, planilha, texto ou imagem, até 6 arquivos de 15 MB. Ficam guardados nos Anexos do planejamento.</p>
            </div>

            <div>
                <p class="text-[11px] font-semibold uppercase mb-1.5" style="color:var(--muted); letter-spacing:.06em">Observação para o agente (opcional)</p>
                <textarea name="note" rows="2" maxlength="3000" class="field w-full px-3 py-2 text-sm"
                          placeholder="Ex.: o foco deste ciclo é ticket médio; a campanha de inverno já está decidida"></textarea>
            </div>

            @error('files.*')<p class="text-xs" style="color:var(--red)">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-primary btn-xs flex items-center gap-1">
                <x-icon name="sparkles" size="12" /> Montar rascunho
            </button>
        </form>
    </div>
@endif

@if(!empty($draft['perguntas']))
    <div class="card mb-3 px-5 py-4" style="border:1px solid rgba(238,121,25,.35); background:rgba(238,121,25,.04)">
        <div class="flex items-center justify-between gap-3 mb-2">
            <p class="text-sm font-semibold" style="color:var(--text)">Perguntas do Agente Nonna</p>
            <form method="POST" action="{{ route('documents.questions.hide', $document) }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-xs">Já resolvi, esconder</button>
            </form>
        </div>
        <ol class="space-y-1.5 text-sm list-decimal pl-5" style="color:var(--text)">
            @foreach($draft['perguntas'] as $p)
                <li>@if($p['secao'])<span class="text-xs font-mono font-semibold" style="color:var(--purple)">{{ $p['secao'] }}</span> @endif{{ $p['texto'] }}</li>
            @endforeach
        </ol>
        @if(!empty($state['used']))
            <p class="text-xs mt-3" style="color:var(--muted)">Leu: {{ implode(' · ', $state['used']) }}</p>
        @endif
        @if(!empty($state['skipped']))
            <p class="text-xs mt-1" style="color:var(--orange)">Não conseguiu ler: {{ implode(' · ', $state['skipped']) }}</p>
        @endif
    </div>
@endif
