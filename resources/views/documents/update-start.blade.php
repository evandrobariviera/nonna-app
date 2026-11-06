{{-- "Atualizar com reunião" — escolhe a reunião (e um pedido opcional) e manda a IA conferir
     o documento (DocumentUpdateController / DocumentUpdateService). --}}
<x-app-layout>
    <x-slot name="header">Atualizar {{ $kindLabel === 'planejamento' ? 'o planejamento' : 'a ' . $kindLabel }} com uma reunião</x-slot>

    <div class="max-w-3xl">
        <a href="{{ $backUrl }}" class="text-xs font-semibold flex items-center gap-1 mb-3" style="color:var(--muted)">
            <x-icon name="arrow-left" size="12" /> Voltar para {{ $owner->title }}
        </a>

        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">
                {{ session('error') }}
            </div>
        @endif

        <div class="card p-5 mb-5">
            <p class="text-sm" style="color:var(--text)">
                A IA funciona como <b>fiscal e secretária</b>: lê a <b>ATA</b> e a <b>transcrição</b> da reunião e confere se ela
                mantém, ajusta ou muda o rumo de <b>{{ $owner->title }}</b> (objetivo, público, mensagem, tom, linha criativa). Ela avisa
                as <b>mudanças de rota</b> e os <b>desvios</b> que ninguém assumiu, pega <b>demandas novas</b> e <b>mudanças criativas</b>,
                mostra para onde foi cada assunto da reunião e só sugere mexer no documento no que importa (não compara redação). Escreve seguindo as mesmas regras usadas para criar {{ $kindLabel === 'planejamento' ? 'o planejamento' : 'a ' . $kindLabel }}.
            </p>
            <p class="text-xs mt-2" style="color:var(--muted)">
                Nada muda sozinho: você confere as sugestões, escolhe quais aceitar e só então o documento ganha uma versão nova.
                A anterior fica guardada.
            </p>
        </div>

        @php
            // Trava de custo: reunião já conferida com este documento não pode ser escolhida de novo.
            $firstFree = $meetings->first(fn ($m) => !$usedRuns->has($m->id))?->id;
            $initial = ($selected && !$usedRuns->has($selected)) ? $selected : $firstFree;
        @endphp
        <form method="POST" action="{{ $storeUrl }}" class="card p-5" x-data="{ meeting: @js($initial), sending: false }" @submit="sending = true">
            @csrf
            <h2 class="card-title mb-1">Qual reunião?</h2>
            <p class="text-xs mb-3" style="color:var(--muted)">Cada reunião é conferida com este documento uma vez só.</p>

            @forelse($meetings as $m)
                @if($usedRunId = $usedRuns->get($m->id))
                    <div class="flex items-start justify-between gap-3 px-3 py-2.5 mb-2 rounded-lg" style="border:1px solid var(--border2); opacity:.7">
                        <span class="min-w-0">
                            <span class="text-sm font-semibold block" style="color:var(--text)">{{ $m->title }}</span>
                            <span class="text-xs" style="color:var(--muted)">{{ $m->scheduled_at->format('d/m/Y') }} · {{ $m->typeLabel() }} · já conferida</span>
                        </span>
                        <a href="{{ route('document-updates.show', $usedRunId) }}" class="btn btn-ghost btn-xs flex-shrink-0">Ver análise →</a>
                    </div>
                    @continue
                @endif
                <label class="flex items-start gap-3 px-3 py-2.5 mb-2 cursor-pointer rounded-lg"
                       :style="meeting === @js($m->id) ? 'background:rgba(100,59,142,.08); border:1px solid rgba(100,59,142,.35)' : 'border:1px solid var(--border2)'">
                    <input type="radio" name="meeting_id" value="{{ $m->id }}" x-model="meeting" class="mt-1">
                    <span class="min-w-0">
                        <span class="text-sm font-semibold block" style="color:var(--text)">{{ $m->title }}</span>
                        <span class="text-xs" style="color:var(--muted)">
                            {{ $m->scheduled_at->format('d/m/Y') }} · {{ $m->typeLabel() }}
                            @if($m->cites_owner)
                                · <span style="color:var(--purple)">citou {{ $kindLabel === 'planejamento' ? 'este planejamento' : 'esta ' . $kindLabel }} na pauta</span>
                            @endif
                        </span>
                    </span>
                </label>
            @empty
                <p class="text-sm" style="color:var(--muted)">
                    Nenhuma reunião deste cliente nos últimos 6 meses tem ATA ou transcrição. Registre a ATA (ou cole a transcrição)
                    na reunião e volte aqui.
                </p>
            @endforelse

            @if($meetings->isNotEmpty())
                <label class="block mt-5">
                    <span class="text-sm font-semibold" style="color:var(--text)">Algum foco ou cuidado? <span class="font-normal" style="color:var(--muted)">(opcional)</span></span>
                    <textarea name="instruction" rows="3" class="field w-full px-3 py-2 text-sm mt-1" maxlength="2000"
                              placeholder="Ex.: o cliente mudou a data de lançamento e cortou o reels de bastidores — confira principalmente as peças e datas.">{{ old('instruction') }}</textarea>
                </label>

                <div class="flex items-center justify-between gap-3 mt-5 flex-wrap">
                    <p class="text-xs" style="color:var(--muted)">Leva de 1 a 4 minutos. Você recebe um aviso no sino quando ficar pronto.</p>
                    <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1" :disabled="!meeting || sending">
                        <x-icon name="sparkles" size="14" /> <span x-text="sending ? 'Enviando…' : 'Conferir documento com a IA'"></span>
                    </button>
                </div>
            @endif
        </form>

        @if($recent->isNotEmpty())
            <div class="card p-5 mt-5">
                <h2 class="card-title mb-3 flex items-center gap-1"><x-icon name="history" size="14" /> Análises anteriores</h2>
                @foreach($recent as $r)
                    <a href="{{ route('document-updates.show', $r) }}" class="flex items-center justify-between gap-3 py-2 text-sm" style="border-top:1px solid var(--border2)">
                        <span class="min-w-0 truncate" style="color:var(--text)">
                            {{ $r->meeting?->title ?? 'Reunião excluída' }}
                            <span class="text-xs" style="color:var(--muted)">· {{ $r->created_at->format('d/m H:i') }} · {{ $r->requestedBy?->name }}</span>
                        </span>
                        <x-document-update-status :status="$r->status" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
