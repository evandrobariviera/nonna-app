{{-- Central de Comércio → Proposta (leitura + ações do ciclo: enviar, liberar, aceitar, recusar, nova versão).
     Valores sempre do que foi salvo (cópia do catálogo na época), nunca recalculados aqui. --}}
@php
    $brl = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $calc = $proposal->calculation['calc'] ?? [];
    $upgrade = $proposal->calculation['upgrade'] ?? null;
    $warnings = $proposal->calculation['warnings'] ?? [];
    $snap = $proposal->package_snapshot;
    $months = (int) config('proposals.contract_months', 12);
    $canRelease = ($isOrgAdmin ?? false) && $proposal->status === 'pending_release';
@endphp
<x-app-layout>
    <x-slot name="header">{{ $proposal->displayTitle() }}</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">{{ session('success') }}</div>
    @endif

    <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
        <div>
            <p class="text-sm" style="color:var(--muted2)">
                Oportunidade <a href="{{ route('opportunities.show', $proposal->opportunity) }}" class="font-semibold" style="color:var(--purple)">{{ $proposal->opportunity->title }}</a>
                · {{ $proposal->opportunity->contact?->name }}
            </p>
            <p class="text-xs mt-1" style="color:var(--muted)">
                Criada por {{ $proposal->createdBy?->name ?? '—' }} em {{ $proposal->created_at->format('d/m/Y') }}
                @if($proposal->parent) · nova versão da <a href="{{ route('proposals.show', $proposal->parent) }}" style="color:var(--purple)">v{{ $proposal->parent->version }}</a>@endif
                @if($proposal->sent_at) · enviada em {{ $proposal->sent_at->format('d/m/Y') }}@endif
                @if($proposal->accepted_at) · aceita em {{ $proposal->accepted_at->format('d/m/Y') }}@endif
                @if($proposal->rejected_at) · recusada em {{ $proposal->rejected_at->format('d/m/Y') }}@endif
            </p>
        </div>
        <span class="badge badge-{{ $proposal->statusColor() }}" style="padding:4px 12px">{{ $proposal->statusLabel() }}</span>
    </div>

    @if($proposal->status === 'pending_release')
        <div class="mb-5 px-4 py-3 text-sm" style="background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3); color:var(--yellow); border-radius:var(--radius)">
            Esta proposta fica {{ number_format((float) ($calc['effective_discount'] ?? 0), 1, ',', '.') }}% abaixo da tabela e precisa da liberação de um administrador antes de ser enviada.
        </div>
    @elseif($proposal->released_at)
        <p class="mb-4 text-xs" style="color:var(--muted)">Desconto de até {{ number_format((float) $proposal->released_percent, 1, ',', '.') }}% liberado por {{ $proposal->releasedBy?->name }} em {{ $proposal->released_at->format('d/m/Y') }}@if($proposal->release_note) · "{{ $proposal->release_note }}"@endif</p>
    @endif

    <div class="grid gap-5 items-start" style="grid-template-columns:minmax(0,1fr) minmax(0,340px)">
        <div class="flex flex-col gap-4 min-w-0">
            @if($snap)
                <div class="card card-body">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold" style="color:var(--text)">{{ $snap['name'] }}</p>
                            <p class="text-xs" style="color:var(--muted)">{{ ($snap['type'] ?? '') === 'closed' ? 'Pacote fechado' : 'Pacote da Nonna' }}</p>
                        </div>
                        <div class="text-right text-sm num">
                            @if($snap['monthly'])<p class="font-semibold">{{ $brl($snap['monthly']) }}/mês</p>@endif
                            @if($snap['one_time'])<p class="text-xs" style="color:var(--muted)">+ {{ $brl($snap['one_time']) }}</p>@endif
                        </div>
                    </div>
                    @if(!empty($snap['rules']['includes']))
                        <ul class="text-xs mt-2 pl-4" style="color:var(--muted2); list-style:disc; columns:2; column-gap:20px">
                            @foreach($snap['rules']['includes'] as $inc)<li>{{ $inc }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if($proposal->items->isNotEmpty())
                <div class="card" style="padding:0; overflow-x:auto">
                    @if($proposal->group_on)
                        <p class="px-4 pt-3 text-sm font-bold" style="color:var(--text)">{{ $proposal->group_name }}
                            <span class="text-xs font-normal" style="color:var(--muted)">· vendido como pacote ({{ $proposal->group_mode === 'fixed' ? 'preço fechado' : 'soma dos itens' }})</span></p>
                    @endif
                    <table class="nonna-table w-full">
                        <thead><tr><th>Item</th><th>Qtd</th><th>Preço</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            @foreach($proposal->items as $item)
                                <tr>
                                    <td><span class="font-semibold" style="color:var(--text)">{{ $item->name }}</span>
                                        <span class="badge {{ $item->kind === 'monthly' ? 'badge-blue' : 'badge-orange' }}" style="font-size:9px">{{ $item->kind === 'monthly' ? 'mensal' : 'único' }}</span></td>
                                    <td class="num">{{ $item->quantity }}</td>
                                    <td class="num" style="white-space:nowrap">{{ $brl($item->unit_price) }}
                                        @if((float) $item->unit_price !== (float) $item->list_price)<span class="text-xs" style="color:var(--muted)">(tabela {{ $brl($item->list_price) }})</span>@endif</td>
                                    <td class="num" style="white-space:nowrap">{{ $brl($item->subtotal) }}{{ $item->kind === 'monthly' ? '/mês' : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if($proposal->group_on && $proposal->group_mode === 'fixed')
                        <p class="px-4 py-3 text-sm" style="color:var(--muted2)">Preço do pacote: <b style="color:var(--text)">{{ $brl($proposal->group_one_time) }}</b>@if((float) $proposal->group_monthly) + <b style="color:var(--text)">{{ $brl($proposal->group_monthly) }}/mês</b>@endif</p>
                    @endif
                </div>
            @endif

            @if(!empty($calc['deliverables']))
                <div class="card card-body">
                    <p class="text-xs font-semibold uppercase mb-2" style="color:var(--muted); letter-spacing:.06em">O que o cliente recebe por mês</p>
                    <div class="grid grid-cols-4 gap-2 text-center">
                        @foreach(['ads' => 'anúncios', 'videos' => 'vídeos', 'campaigns' => 'campanhas ao mesmo tempo', 'attendants' => 'atendentes avisados'] as $k => $label)
                            <div class="py-2" style="background:var(--s2); border-radius:7px">
                                <b class="block text-lg num" style="color:var(--text)">{{ $calc['deliverables'][$k] ?? '∞' }}</b>
                                <span class="text-xs" style="color:var(--muted2)">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach($warnings as $w)
                <div class="px-4 py-2 text-xs" style="background:rgba(245,158,11,.1); color:var(--yellow); border-radius:7px">{{ $w }}</div>
            @endforeach
        </div>

        <aside class="flex flex-col gap-3">
            <div class="card card-body text-sm">
                <p class="text-xs font-semibold uppercase mb-1" style="color:var(--muted); letter-spacing:.06em">Mensalidade</p>
                <p class="text-2xl font-black num" style="color:var(--text)">{{ (float) $proposal->monthly_total > 0 ? $brl($proposal->monthly_total) . '/mês' : 'Sem mensalidade' }}</p>
                @if(!empty($calc['discount_value']))<p class="text-xs" style="color:var(--green)">inclui desconto de {{ rtrim(rtrim(number_format((float) $proposal->discount_percent, 2, ',', '.'), '0'), ',') }}% (−{{ $brl($calc['discount_value']) }})</p>@endif
                @if(!empty($calc['excess_value']))<p class="text-xs" style="color:var(--muted2)">inclui {{ $brl($calc['excess_value']) }} de gestão da verba acima do teto</p>@endif

                <p class="text-xs font-semibold uppercase mt-4 mb-1" style="color:var(--muted); letter-spacing:.06em">Valores únicos</p>
                <p class="num font-semibold" style="color:var(--text)">{{ $brl($proposal->one_time_total) }}
                    @if($proposal->installments > 1)<span class="text-xs font-normal" style="color:var(--muted2)">em {{ $proposal->installments }}x de {{ $brl($calc['installment'] ?? 0) }}</span>@endif</p>

                <div class="flex justify-between font-bold mt-4 pt-2" style="border-top:1px solid var(--border); color:var(--text)">
                    <span>Total do contrato ({{ $months }} meses)</span><span class="num">{{ $brl($proposal->contract_total) }}</span>
                </div>
                @if($proposal->ad_budget !== null)
                    <div class="flex justify-between text-xs mt-1" style="color:var(--muted2)"><span>Verba às plataformas (fora da conta)</span><span class="num">{{ $brl($proposal->ad_budget) }}/mês</span></div>
                @endif
            </div>

            @if($upgrade && $proposal->isOpen())
                <div class="card card-body text-xs" style="border-color:rgba(238,121,25,.4); background:rgba(238,121,25,.06)">
                    <p class="font-bold text-sm" style="color:var(--orange)">Vale oferecer o {{ str_replace('PlaNo C · ', '', $upgrade['package_name']) }}</p>
                    <p class="mt-1" style="color:var(--text)">Por {{ $upgrade['monthly_diff'] > 0 ? 'mais ' . $brl($upgrade['monthly_diff']) : 'menos ' . $brl(abs($upgrade['monthly_diff'])) }}/mês, o cliente sobe de nível.
                        @if($upgrade['absorbed'])Ele já inclui {{ implode(', ', $upgrade['absorbed']) }}.@endif</p>
                </div>
            @endif

            <div class="card card-body flex flex-col gap-2">
                @if($proposal->isEditable())
                    <a href="{{ route('proposals.edit', $proposal) }}" class="btn btn-sm"><x-icon name="pencil" size="13" /> Editar</a>
                    @if($proposal->status === 'draft')
                        <form method="POST" action="{{ route('proposals.send', $proposal) }}"
                              @submit.prevent="if (await $store.confirmDialog.ask('Marcar como enviada? Depois disso esta versão não muda mais; para alterar, você cria uma nova versão.')) $el.submit()">
                            @csrf
                            <button class="btn btn-primary btn-sm w-full"><x-icon name="send" size="13" /> Marcar como enviada</button>
                        </form>
                    @endif
                    @if($canRelease)
                        <form method="POST" action="{{ route('proposals.release', $proposal) }}" class="flex flex-col gap-2 pt-2" style="border-top:1px solid var(--border)">
                            @csrf
                            <input name="note" class="field w-full" maxlength="500" placeholder="Motivo (opcional)" aria-label="Motivo da liberação">
                            <button class="btn btn-sm w-full" style="background:var(--green); color:#fff"><x-icon name="check" size="13" /> Liberar desconto</button>
                        </form>
                    @endif
                @endif

                @if($proposal->status === 'sent')
                    <form method="POST" action="{{ route('proposals.accept', $proposal) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Marcar esta proposta como aceita? As outras propostas abertas desta oportunidade viram recusadas, e em seguida você fecha o negócio.')) $el.submit()">
                        @csrf
                        <button class="btn btn-sm w-full" style="background:var(--green); color:#fff"><x-icon name="circle-check" size="13" /> Cliente aceitou</button>
                    </form>
                    <form method="POST" action="{{ route('proposals.reject', $proposal) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Marcar esta proposta como recusada?')) $el.submit()">
                        @csrf
                        <button class="btn btn-ghost btn-sm w-full" style="color:var(--red)">Cliente recusou</button>
                    </form>
                @endif

                @if(in_array($proposal->status, ['sent', 'rejected'], true) && $proposal->opportunity->isOpen())
                    <form method="POST" action="{{ route('proposals.new-version', $proposal) }}">
                        @csrf
                        <button class="btn btn-sm w-full"><x-icon name="copy" size="13" /> Criar nova versão</button>
                    </form>
                @endif

                <a href="{{ route('opportunities.show', $proposal->opportunity) }}" class="text-xs text-center mt-1" style="color:var(--muted)">Voltar para a oportunidade</a>
            </div>
        </aside>
    </div>
</x-app-layout>
