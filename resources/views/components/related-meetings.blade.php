{{-- Reuniões relacionadas a um planejamento, projeto/campanha ou tarefa (Meeting::scopeRelatedTo*):
     ligadas direto, citadas na pauta, ou citadas por um item de dentro ("via tarefa X").
     Clicar abre o resumo no painel lateral. $meetings precisa de agendaProjects/agendaTasks
     (e agendaMacroPlans) carregados pro "via". --}}
@props(['meetings', 'context', 'title' => 'Reuniões', 'empty' => 'Nenhuma reunião ligada ainda.'])

<p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
    {{ $title }}
    @if($meetings->count() > 0)
        <span class="ml-1 px-1.5 py-0.5" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $meetings->count() }}</span>
    @endif
</p>
@if($meetings->isEmpty())
    <p class="text-xs py-1" style="color:var(--muted)">{{ $empty }}</p>
@else
    @foreach($meetings as $m)
        @php $via = $m->viaLabelFor($context); @endphp
        <button type="button" @click="$store.sidePanel.open('{{ route('meetings.preview', $m) }}')"
                class="w-full flex flex-col gap-1 px-2 py-1.5 mb-1 text-left transition-colors"
                style="background:var(--s2); border:none; cursor:pointer"
                onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='var(--s2)'">
            <span class="text-xs font-semibold truncate w-full" style="color:var(--text)">{{ $m->title }}</span>
            <span class="flex items-center gap-1.5 flex-wrap">
                <span class="badge badge-{{ $m->statusColor() }}">{{ $m->statusLabel() }}</span>
                <span class="text-xs font-mono" style="color:var(--muted)">{{ $m->scheduled_at->format('d/m/y H:i') }}</span>
            </span>
            @if($via)
                <span class="text-xs truncate w-full" style="color:var(--muted2)" title="{{ $via }}">{{ $via }}</span>
            @endif
        </button>
    @endforeach
@endif
