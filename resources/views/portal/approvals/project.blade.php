<x-portal-layout>
    <x-slot name="title">{{ $pageTitle }} — Central de Aprovações</x-slot>

    @php
        $statusColors = [
            'pending'           => ['bg' => 'rgba(100, 59, 142,.08)', 'text' => 'var(--purple)', 'label' => 'Aguardando você'],
            'approved'          => ['bg' => 'rgba(5,150,105,.1)',     'text' => 'var(--green)',  'label' => 'Aprovado'],
            'changes_requested' => ['bg' => 'rgba(238, 121, 25,.08)', 'text' => 'var(--orange)', 'label' => 'Ajuste solicitado'],
        ];
    @endphp

    <div class="mb-6 flex items-center gap-2 flex-wrap text-xs font-semibold">
        <a href="{{ route('portal.approvals.index') }}" style="color: var(--muted)">← Central de Aprovações</a>
        <span style="color: var(--muted)">›</span>
        <span style="color: var(--text)">{{ $pageTitle }}</span>
    </div>

    {{-- TOPO: título + descrição para o cliente (nunca objective/briefings, que são internos) --}}
    <div class="mb-6">
        <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color: var(--purple)">{{ $pageType === 'Avulsas' ? 'Avulsas' : 'Projeto · ' . $pageType }}</p>
        <h1 class="text-3xl font-black" style="color: var(--text)">{{ $pageTitle }}</h1>
        @if($description)
            <p class="text-base mt-3 whitespace-pre-line" style="color: var(--muted2); line-height: 1.6; max-width: 680px">{{ $description }}</p>
        @endif
    </div>

    {{-- PROGRESSO --}}
    <div class="card p-5 mb-8">
        <div class="flex items-center justify-between text-sm mb-2 gap-3 flex-wrap">
            <span class="font-bold" style="color: var(--text)">{{ $summary['approved'] }} de {{ $summary['total'] }} {{ $summary['total'] === 1 ? 'item aprovado' : 'itens aprovados' }}</span>
            <span style="color: var(--muted)">
                {{ $summary['pending'] }} aguardando você
                @if($summary['changes'])
                    · {{ $summary['changes'] }} em ajuste
                @endif
            </span>
        </div>
        <div class="w-full h-2 rounded-full overflow-hidden" style="background: var(--s3)">
            <div class="h-2 rounded-full" style="width: {{ $summary['percent'] }}%; background: var(--purple)"></div>
        </div>
    </div>

    {{-- DESTAQUE --}}
    @if($highlight)
        @php $hsc = $statusColors[$highlight['status']] ?? $statusColors['pending']; @endphp
        <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Destaque</p>
        <a href="{{ route('portal.approvals.show', $highlight['round']) }}" class="card mb-8 flex flex-wrap overflow-hidden" style="text-decoration:none; padding:0">
            <div class="flex items-center justify-center" style="flex: 1 1 280px; min-height: 200px; background: #2a1a3d">
                @if($highlight['thumb'])
                    <img src="{{ $highlight['thumb']->url() }}" alt="" class="w-full h-full object-cover" style="max-height: 280px">
                @else
                    <span class="text-4xl">{{ $highlight['first_file']?->icon() ?? '📄' }}</span>
                @endif
            </div>
            <div class="p-6 flex flex-col justify-center gap-3" style="flex: 2 1 360px">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold" style="color: var(--orange)">★ Destaque</span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="background: {{ $hsc['bg'] }}; color: {{ $hsc['text'] }}">{{ $hsc['label'] }}</span>
                </div>
                <h2 class="text-xl font-black" style="color: var(--text)">{{ $highlight['task']->title }}</h2>
                @if($highlight['round']->caption)
                    <p class="text-sm" style="color: var(--muted2); line-height: 1.6">{{ \Illuminate\Support\Str::limit($highlight['round']->caption, 220) }}</p>
                @endif
                <span class="text-sm font-bold" style="color: var(--purple)">{{ $highlight['status'] === 'pending' ? 'Abrir e aprovar' : 'Ver' }} →</span>
            </div>
        </a>
    @endif

    {{-- PEÇAS --}}
    @if($pieces->isNotEmpty())
        <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color: var(--muted)">Peças</p>
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))">
            @foreach($pieces as $item)
                @php $sc = $statusColors[$item['status']] ?? $statusColors['pending']; @endphp
                <a href="{{ route('portal.approvals.show', $item['round']) }}" class="card flex flex-col overflow-hidden" style="text-decoration:none; padding:0">
                    <div class="flex items-center justify-center" style="aspect-ratio: 4 / 3; background: var(--s3)">
                        @if($item['thumb'])
                            <img src="{{ $item['thumb']->url() }}" alt="" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <span class="text-3xl">{{ $item['first_file']?->icon() ?? '📝' }}</span>
                        @endif
                    </div>
                    <div class="p-4 flex flex-col gap-2">
                        <span class="self-start text-xs font-semibold px-2.5 py-1 rounded-full" style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">{{ $sc['label'] }}</span>
                        <span class="text-sm font-bold" style="color: var(--text); line-height: 1.35">{{ $item['task']->title }}</span>
                        @if($item['deliverable_count'] > 1)
                            <span class="text-xs" style="color: var(--muted)">{{ $item['deliverable_count'] }} arquivos</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</x-portal-layout>
