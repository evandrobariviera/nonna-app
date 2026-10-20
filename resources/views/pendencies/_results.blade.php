{{-- Fragmento trocado pelo live-filter (PendencyController::results) — 3 colunas por
     "quem deve". --}}
<div class="flex flex-wrap items-center gap-4 mb-4 text-xs" style="color:var(--muted)">
    <span><strong style="color:var(--text); font-size:14px">{{ $totalCount }}</strong> {{ $view === 'abertas' ? 'em aberto' : 'resolvidas/canceladas (últimas 150)' }}</span>
    @if($overdueCount > 0)
        <span style="color:var(--red)"><strong style="font-size:14px">{{ $overdueCount }}</strong> vencida{{ $overdueCount > 1 ? 's' : '' }}</span>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
    @foreach(\App\Models\Pendency::$owedBy as $key => $col)
        <div class="flex flex-col gap-2.5">
            <div class="flex items-center justify-between px-1">
                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--{{ $col['color'] }}); letter-spacing:.08em">
                    {{ $col['column'] }}
                </p>
                <span class="text-xs font-semibold" style="color:var(--muted)">{{ $columns[$key]->count() }}</span>
            </div>

            @forelse($columns[$key] as $p)
                {{-- Editar abre o form único da página (index.blade.php), não um form por cartão --}}
                @include('pendencies._item', [
                    'p'          => $p,
                    'users'      => collect(),
                    'contacts'   => collect(),
                    'showOrigin' => true,
                    'last'       => $loop->last,
                ])
            @empty
                <div class="px-3 py-6 text-center text-xs" style="border:1px dashed var(--border2); border-radius:8px; color:var(--muted)">
                    Nada por aqui.
                </div>
            @endforelse
        </div>
    @endforeach
</div>
