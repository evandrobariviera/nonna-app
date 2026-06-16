<x-superadmin-layout>

    <div class="mb-8">
        <h1 class="text-2xl font-black" style="color: var(--text)">Dashboard</h1>
        <p class="text-sm mt-1" style="color: var(--muted)">Visão geral da plataforma</p>
    </div>

    {{-- Cards de status --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach([
            ['label' => 'Total',     'value' => $stats['total'],     'color' => 'var(--purple)'],
            ['label' => 'Ativos',    'value' => $stats['active'],    'color' => 'var(--green)'],
            ['label' => 'Trial',     'value' => $stats['trial'],     'color' => 'var(--orange)'],
            ['label' => 'Suspensos', 'value' => $stats['suspended'], 'color' => 'var(--red)'],
        ] as $card)
            <div class="card p-5">
                <p class="text-xs font-bold uppercase tracking-wide mb-2" style="color: var(--muted)">
                    {{ $card['label'] }}
                </p>
                <p class="text-3xl font-black" style="color: {{ $card['color'] }}">
                    {{ $card['value'] }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Por plano --}}
        <div class="card p-6">
            <h2 class="text-sm font-bold mb-4" style="color: var(--text)">Organizações por plano</h2>
            <div class="space-y-3">
                @foreach(\App\Models\Organization::$plans as $key => $label)
                    @php $count = $byPlan[$key] ?? 0; $total = $stats['total'] ?: 1; @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold" style="color: var(--muted)">{{ $label }}</span>
                            <span class="text-xs font-bold" style="color: var(--text)">{{ $count }}</span>
                        </div>
                        <div class="h-1.5 rounded-full" style="background: var(--s3)">
                            <div class="h-1.5 rounded-full" style="background: var(--grad); width: {{ $total > 0 ? round($count / $total * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Recentes --}}
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold" style="color: var(--text)">Organizações recentes</h2>
                <a href="{{ route('superadmin.organizations.index') }}"
                   class="text-xs font-semibold" style="color: var(--purple)">Ver todas</a>
            </div>
            <div class="space-y-3">
                @forelse($recent as $org)
                    <div class="flex items-center justify-between py-2 border-b" style="border-color: var(--border2)">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--text)">{{ $org->name }}</p>
                            <p class="text-xs" style="color: var(--muted)">{{ $org->owner?->email }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                  style="background: var(--s3); color: var(--muted)">
                                {{ \App\Models\Organization::$plans[$org->plan] ?? $org->plan }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--muted)">Nenhuma organização ainda.</p>
                @endforelse
            </div>
        </div>

    </div>

</x-superadmin-layout>
