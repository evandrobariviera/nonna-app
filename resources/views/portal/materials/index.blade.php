<x-portal-layout>
    <x-slot name="title">Materiais Aprovados</x-slot>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Materiais Aprovados</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Arquivos já aprovados de {{ $client->company_name }}, prontos pra baixar</p>
    </div>

    @forelse($rounds as $round)
        <div class="card p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="font-bold" style="color: var(--text)">{{ $round->task?->title ?? '—' }}</p>
                    <p class="text-xs mt-0.5" style="color: var(--muted)">Aprovado em {{ $round->resolved_at?->format('d/m/Y') }}</p>
                </div>
                <a href="{{ route('portal.approvals.show', $round) }}" class="text-xs font-semibold flex-shrink-0" style="color: var(--purple)">Ver detalhes →</a>
            </div>
            <div class="flex flex-col gap-2">
                @foreach($round->deliverables() as $file)
                    <a href="{{ $file->downloadUrl() }}"
                       class="flex items-center gap-3 p-3 rounded-lg transition-colors"
                       style="background: var(--s2); text-decoration:none">
                        <span>{{ $file->icon() }}</span>
                        <span class="text-sm flex-1 truncate" style="color: var(--text)">{{ $file->filename }}</span>
                        <span class="text-xs flex-shrink-0" style="color: var(--muted)">⬇ {{ $file->sizeForHumans() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card p-8 text-center">
            <p class="text-sm" style="color: var(--muted)">Nenhum material aprovado ainda.</p>
        </div>
    @endforelse

</x-portal-layout>
