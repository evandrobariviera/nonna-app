<x-app-layout>
    <x-slot name="header">Sprints</x-slot>

    <div class="flex items-center justify-between mb-6">
        <div>
            @if($active)
                <span class="text-xs font-mono" style="color:var(--muted)">
                    Sprint ativa: <span style="color:var(--orange)">{{ $active->title }}</span>
                    — {{ $active->starts_at->format('d/m') }} a {{ $active->ends_at->format('d/m') }}
                </span>
            @else
                <span class="text-xs font-mono" style="color:var(--muted)">Nenhuma sprint ativa no momento</span>
            @endif
        </div>
        <a href="{{ route('sprints.create') }}" class="btn btn-primary">
            + Nova Sprint
        </a>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @forelse($sprints as $sprint)
        <div class="card px-5 py-4 mb-3 flex items-center justify-between gap-4 flex-wrap"
             style="{{ $sprint->isActive() ? 'border-left:3px solid var(--orange)' : '' }}">
            <div class="flex items-center gap-4 flex-wrap">
                <span class="badge badge-{{ $sprint->statusColor() }}">{{ $sprint->statusLabel() }}</span>
                <div>
                    <p class="text-sm font-bold" style="color:var(--text)">{{ $sprint->title }}</p>
                    <p class="text-xs font-mono mt-0.5" style="color:var(--muted)">
                        {{ $sprint->starts_at->format('d/m/Y') }} → {{ $sprint->ends_at->format('d/m/Y') }}
                        @if($sprint->isLocked())
                            · <span style="color:var(--purple)">Travada</span>
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-mono" style="color:var(--muted)">
                    {{ $sprint->tasks_count }} tarefa{{ $sprint->tasks_count !== 1 ? 's' : '' }}
                </span>
                <a href="{{ route('sprints.show', $sprint) }}" class="btn btn-ghost btn-xs">
                    Ver Sprint →
                </a>
            </div>
        </div>
    @empty
        <div class="px-6 py-12 text-center" style="border:1px dashed var(--border2)">
            <p class="text-sm font-mono" style="color:var(--muted)">Nenhuma sprint criada ainda.</p>
            <a href="{{ route('sprints.create') }}" class="text-xs mt-2 inline-block" style="color:var(--purple)">
                Criar primeira sprint →
            </a>
        </div>
    @endforelse

</x-app-layout>
