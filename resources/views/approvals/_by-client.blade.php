{{-- ══ POR CLIENTE — o mesmo recorte que o cliente vê na Central dele
     (projeto por projeto), mais o que só a gente precisa: o que ainda não foi
     enviado e há quanto tempo espera o cliente. Só clientes com algo em aberto.
     Ver ApprovalOverviewService. ══ --}}
@php
    $waitColor = fn (?int $d) => match (true) {
        $d === null                                               => 'var(--muted)',
        $d >= \App\Services\ApprovalOverviewService::URGENT_DAYS => 'var(--red)',
        $d >= \App\Services\ApprovalOverviewService::WARN_DAYS   => 'var(--orange)',
        default                                                   => 'var(--muted2)',
    };
    $waitLabel = fn (?int $d) => $d === null ? null : ($d === 0 ? 'desde hoje' : ($d === 1 ? 'há 1 dia' : "há {$d} dias"));
@endphp
<div x-show="tab === 'clientes'" x-cloak>
    @if($byClient->isEmpty())
        <div class="card card-body text-center text-sm" style="color:var(--muted)">
            Nenhum cliente com aprovação em aberto.
        </div>
    @else
        <p class="text-xs mb-4" style="color:var(--muted2)">
            Ordenado por quem espera há mais tempo. Prazo em <span style="color:var(--orange)">laranja</span> a partir de {{ \App\Services\ApprovalOverviewService::WARN_DAYS }} dias,
            <span style="color:var(--red)">vermelho</span> a partir de {{ \App\Services\ApprovalOverviewService::URGENT_DAYS }}.
        </p>
        <div class="flex flex-col gap-4">
            @foreach($byClient as $c)
                <div class="card overflow-hidden">
                    {{-- Cabeçalho do cliente --}}
                    <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap" style="border-bottom:1px solid var(--border2)">
                        <a href="{{ route('approvals.index', ['client_id' => $c['client']->id, 'view' => 'list']) }}"
                           class="text-base font-bold hover:underline" style="color:var(--text)">
                            {{ $c['client']->displayName() }}
                        </a>
                        <div class="flex items-center gap-2 flex-wrap text-xs font-semibold">
                            @if($c['totals']['pending'])
                                <span class="px-2 py-1" style="border-radius:999px; background:rgba(100,59,142,.1); color:var(--purple)">
                                    {{ $c['totals']['pending'] }} aguardando cliente
                                    @if($c['oldest_days'] !== null)
                                        · <span style="color:{{ $waitColor($c['oldest_days']) }}">mais antiga {{ $waitLabel($c['oldest_days']) }}</span>
                                    @endif
                                </span>
                            @endif
                            @if($c['totals']['awaiting_send'])
                                <span class="px-2 py-1" style="border-radius:999px; background:var(--s3); color:var(--muted2)">{{ $c['totals']['awaiting_send'] }} aguardando envio</span>
                            @endif
                            @if($c['totals']['changes'])
                                <span class="px-2 py-1" style="border-radius:999px; background:rgba(238,121,25,.1); color:var(--orange)">{{ $c['totals']['changes'] }} em ajuste</span>
                            @endif
                            {{-- Lembrete único: uma mensagem por contato com o link da Central
                                 dele, em vez de reenviar peça por peça (ApprovalReminderService). --}}
                            @if($c['totals']['pending'])
                                <form method="POST" action="{{ route('approvals.remind-client', $c['client']) }}"
                                      @submit.prevent="if (await $store.confirmDialog.ask('Mandar UMA mensagem pra cada contato de {{ addslashes($c['client']->displayName()) }} com as peças que esperam a resposta dele e o link da Central de Aprovações?')) $el.submit()">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-xs flex items-center gap-1">
                                        <x-icon name="send" size="12" /> Lembrar cliente
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Projetos (e peças avulsas) com algo em aberto --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" style="min-width:640px">
                            <thead>
                                <tr class="text-xs uppercase tracking-widest" style="color:var(--muted)">
                                    <th class="text-left font-semibold px-5 py-2">Projeto</th>
                                    <th class="text-left font-semibold px-3 py-2" style="width:150px">Aprovados</th>
                                    <th class="text-left font-semibold px-3 py-2">Aguardando cliente</th>
                                    <th class="text-left font-semibold px-3 py-2">Envio</th>
                                    <th class="text-left font-semibold px-3 py-2">Ajuste</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($c['groups'] as $g)
                                    @php $pct = $g['total'] ? round($g['approved'] / $g['total'] * 100) : 0; @endphp
                                    <tr style="border-top:1px solid var(--border2)">
                                        <td class="px-5 py-3">
                                            <a href="{{ $g['url'] }}" class="font-semibold hover:underline" style="color:var(--text)">{{ $g['title'] }}</a>
                                            <span class="block text-xs" style="color:var(--muted)">{{ $g['type_label'] }}</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="text-xs" style="color:var(--muted2)">{{ $g['approved'] }} de {{ $g['total'] }}</span>
                                            <div class="h-1.5 mt-1 rounded-full overflow-hidden" style="background:var(--s3)">
                                                <div class="h-1.5 rounded-full" style="width:{{ $pct }}%; background:var(--purple)"></div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            @if($g['pending'])
                                                <span class="font-semibold" style="color:var(--purple)">{{ $g['pending'] }}</span>
                                                <span class="text-xs" style="color:{{ $waitColor($g['oldest_days']) }}">· {{ $waitLabel($g['oldest_days']) }}</span>
                                            @else
                                                <span style="color:var(--muted)">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3" style="color:{{ $g['awaiting_send'] ? 'var(--text)' : 'var(--muted)' }}">{{ $g['awaiting_send'] ?: '—' }}</td>
                                        <td class="px-3 py-3" style="color:{{ $g['changes'] ? 'var(--orange)' : 'var(--muted)' }}">{{ $g['changes'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
