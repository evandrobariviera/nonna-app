{{-- Resumo do cliente pra acesso rápido: quem organiza, quanto de produção ainda
     cabe no mês, o que foi contratado e quanto entra de verba.

     Montado na ficha do cliente e no canvas que abre de dentro da tarefa — é o
     mesmo bloco nos dois, pra não divergir. Só leitura; editar é na ficha. --}}
@props(['client', 'compact' => false])

@php
    $uso       = $client->productionUsage();
    $servicos  = $client->contracted_services ?? [];
    $lead      = $client->creativeLead;
    $verba     = $client->monthly_ad_budget;
    $preench   = $client->completeness();

    // Verde só quando está completo de verdade — nota alta com item faltando ainda
    // precisa chamar atenção, senão ninguém termina de preencher.
    $notaCor = $preench['score'] >= 100 ? 'var(--green)'
             : ($preench['score'] >= 70 ? 'var(--orange)' : 'var(--red)');
    $circ    = 2 * M_PI * 20; // raio 20 do anel abaixo
@endphp

<div class="card px-5 py-4 flex flex-col gap-4">

    {{-- Nota de preenchimento — mesma ideia da nota de otimização do Google Ads:
         mostra o número e, ao clicar, exatamente o que falta e onde resolver. --}}
    <div x-data="{ abertoPreenchimento: false }">
        <button type="button" @click="abertoPreenchimento = !abertoPreenchimento"
                class="w-full flex items-center gap-3 text-left">
            <svg width="48" height="48" viewBox="0 0 48 48" class="flex-shrink-0" style="transform:rotate(-90deg)">
                <circle cx="24" cy="24" r="20" fill="none" stroke="var(--s3)" stroke-width="4"></circle>
                <circle cx="24" cy="24" r="20" fill="none" stroke="{{ $notaCor }}" stroke-width="4"
                        stroke-linecap="round"
                        stroke-dasharray="{{ $circ }}"
                        stroke-dashoffset="{{ $circ - ($circ * $preench['score'] / 100) }}"></circle>
            </svg>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Cadastro preenchido</p>
                <p class="text-lg font-bold leading-tight" style="color:{{ $notaCor }}">{{ $preench['score'] }}%</p>
                <p class="text-xs" style="color:var(--muted)">
                    {{ $preench['done'] }} de {{ $preench['total'] }} itens
                    @if($preench['score'] < 100)
                        · <span style="color:{{ $notaCor }}">ver o que falta</span>
                    @endif
                </p>
            </div>
        </button>

        <div x-show="abertoPreenchimento" x-cloak class="mt-3 flex flex-col gap-2.5">
            @foreach($preench['groups'] as $grupo => $linhas)
                <div>
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">{{ $grupo }}</p>
                    <div class="flex flex-col gap-1">
                        @foreach($linhas as $item)
                            <a href="{{ route('clients.show', ['client' => $client, 'tab' => $item['tab']]) }}"
                               class="flex items-center gap-2 text-xs"
                               style="color:{{ $item['ok'] ? 'var(--muted)' : 'var(--text)' }}">
                                <span style="color:{{ $item['ok'] ? 'var(--green)' : 'var(--red)' }}">
                                    {{ $item['ok'] ? '✓' : '○' }}
                                </span>
                                <span style="{{ $item['ok'] ? 'text-decoration:line-through' : '' }}">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div style="border-top:1px solid var(--border2)"></div>

    {{-- Direção criativa --}}
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0">
            @if($lead)
                <x-user-avatar :user="$lead" size="8" color="var(--purple)" :title="$lead->name" />
                <div class="min-w-0">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Direção criativa</p>
                    <p class="text-sm font-semibold truncate" style="color:var(--text)">{{ $lead->name }}</p>
                </div>
            @else
                <div>
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Direção criativa</p>
                    <p class="text-sm" style="color:var(--muted)">Ninguém definido</p>
                </div>
            @endif
        </div>

        @if($verba)
            <div class="text-right flex-shrink-0">
                <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Verba de tráfego</p>
                <p class="text-sm font-semibold" style="color:var(--orange)">{{ $verba }}</p>
            </div>
        @endif
    </div>

    {{-- Produção do mês: cota × já planejado --}}
    <div style="border-top:1px solid var(--border2); padding-top:14px">
        <p class="text-xs font-mono uppercase tracking-widest mb-2.5" style="color:var(--muted)">
            Produção de {{ now()->locale('pt_BR')->translatedFormat('F') }}
        </p>

        @if(empty($uso))
            <p class="text-xs" style="color:var(--muted)">
                Volume de produção ainda não configurado para este cliente.
            </p>
        @else
            <div class="flex flex-col gap-2">
                @foreach($uso as $linha)
                    @php
                        $pct  = min(100, (int) round($linha['used'] / max(1, $linha['quota']) * 100));
                        $cor  = $linha['used'] > $linha['quota'] ? 'var(--red)'
                              : ($linha['left'] === 0 ? 'var(--green)' : 'var(--purple)');
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-2 mb-1">
                            <span class="text-xs font-semibold truncate" style="color:var(--muted2)">{{ $linha['label'] }}</span>
                            <span class="text-xs font-mono flex-shrink-0" style="color:{{ $cor }}">
                                {{ $linha['used'] }}/{{ $linha['quota'] }}
                                @if($linha['used'] > $linha['quota'])
                                    · {{ $linha['used'] - $linha['quota'] }} além
                                @elseif($linha['left'] > 0)
                                    · cabem {{ $linha['left'] }}
                                @else
                                    · completo
                                @endif
                            </span>
                        </div>
                        <div style="height:4px; background:var(--s3); border-radius:2px; overflow:hidden">
                            <div style="height:4px; width:{{ $pct }}%; background:{{ $cor }}; border-radius:2px"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Serviços contratados --}}
    @if($servicos)
        <div style="border-top:1px solid var(--border2); padding-top:14px">
            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Serviços contratados</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($servicos as $svc)
                    <span class="badge" style="font-size:10px">{{ \App\Models\Client::$services[$svc] ?? $svc }}</span>
                @endforeach
            </div>
        </div>
    @endif
</div>
