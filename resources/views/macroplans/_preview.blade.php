{{-- Preview leve pro painel lateral (canvas). Não inclui layout — só o fragmento. --}}
<div class="p-6 flex flex-col gap-6">

    {{-- Cabeçalho --}}
    <div>
        <p class="text-base font-bold" style="color:var(--text)">{{ $macroplan->title }}</p>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="badge badge-{{ $macroplan->statusColor() }}">{{ $macroplan->statusLabel() }}</span>
            @if($macroplan->client)
                <span class="text-xs" style="color:var(--muted)">{{ $macroplan->client->company_name }}</span>
            @endif
        </div>
    </div>

    {{-- Período e responsável --}}
    <div class="card card-body">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Período</p>
                <p class="text-sm" style="color:var(--text)">{{ $macroplan->periodLabel() }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Responsável</p>
                <p class="text-sm" style="color:var(--text)">{{ $macroplan->responsible?->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Projetos --}}
    @if($macroplan->projects->isNotEmpty())
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Projetos ({{ $macroplan->projects->count() }})</p>
            <div class="flex flex-col gap-2">
                @foreach($macroplan->projects as $proj)
                    <a href="{{ route('macroplans.projects.show', [$macroplan, $proj]) }}"
                       class="card card-body flex items-center justify-between gap-3 transition-colors"
                       onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                        <span class="text-sm font-medium truncate" style="color:var(--text)">{{ $proj->title }}</span>
                        <span class="badge badge-{{ $proj->statusColor() }} flex-shrink-0">{{ $proj->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Link pra página completa --}}
    <a href="{{ route('macroplans.edit', $macroplan) }}"
       class="text-center px-4 py-2.5 text-xs font-bold transition-colors"
       style="border:1px solid var(--border2); color:var(--muted2)"
       onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
       onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
        Ver página completa do planejamento →
    </a>
</div>
