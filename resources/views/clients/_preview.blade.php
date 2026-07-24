{{-- Preview leve pro painel lateral (canvas). Não inclui layout — só o fragmento. --}}
<div class="p-6 flex flex-col gap-6">

    {{-- Cabeçalho --}}
    <div class="flex items-start gap-3">
        @if($client->logoUrl())
            <img src="{{ $client->logoUrl() }}" alt="{{ $client->company_name }}"
                 class="h-10 w-10 rounded-lg object-cover flex-shrink-0" style="border:1px solid var(--border2)">
        @endif
        <div class="min-w-0 flex-1">
            <p class="text-base font-bold truncate" style="color:var(--text)">{{ $client->company_name }}</p>
            <div class="flex items-center gap-2 mt-1">
                <span class="badge badge-{{ $client->statusColor() }}">{{ $client->statusLabel() }}</span>
                @if($client->segment)
                    <span class="text-xs" style="color:var(--muted)">{{ $client->segment }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Contato --}}
    <div class="card card-body">
        <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Contato</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Responsável</p>
                <p class="text-sm" style="color:var(--text)">{{ $client->responsible_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">E-mail</p>
                <p class="text-sm truncate" style="color:var(--text)">{{ $client->contact_email ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Telefone</p>
                <p class="text-sm" style="color:var(--text)">{{ $client->contact_phone ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Verba mensal</p>
                <p class="text-sm" style="color:var(--text)">{{ $client->monthly_ad_budget ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Números rápidos --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="card card-body text-center">
            <p class="text-xl font-bold" style="color:var(--purple)">{{ $client->macroplans_count }}</p>
            <p class="text-xs" style="color:var(--muted)">Planejamentos</p>
        </div>
        <div class="card card-body text-center">
            <p class="text-xl font-bold" style="color:var(--purple)">{{ $client->ad_accounts_count }}</p>
            <p class="text-xs" style="color:var(--muted)">Contas de anúncio</p>
        </div>
    </div>

    {{-- Planejamentos recentes --}}
    @if($recentMacroplans->isNotEmpty())
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Planejamentos recentes</p>
            <div class="flex flex-col gap-2">
                @foreach($recentMacroplans as $plan)
                    <a href="{{ route('macroplans.edit', $plan) }}"
                       class="card card-body flex items-center justify-between gap-3 transition-colors"
                       onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border2)'">
                        <span class="text-sm font-medium truncate" style="color:var(--text)">{{ $plan->title }}</span>
                        <span class="badge badge-{{ $plan->statusColor() }} flex-shrink-0">{{ $plan->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Link pra página completa --}}
    <a href="{{ route('clients.show', $client) }}"
       class="text-center px-4 py-2.5 text-xs font-bold transition-colors"
       style="border:1px solid var(--border2); color:var(--muted2)"
       onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
       onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
        Ver página completa do cliente →
    </a>
</div>
