<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">CRM</p>
                <h1 class="text-2xl font-black text-[var(--text)]">Oportunidades</h1>
            </div>
            <a href="{{ route('opportunities.create') }}" class="btn btn-primary">
                + Nova Oportunidade
            </a>
        </div>
    </x-slot>

    <div class="py-8 px-6" x-data="kanban()">

        @if(session('success'))
            <div class="mb-6 px-4 py-3 text-sm border" style="background: rgba(52,211,153,.06); border-color: rgba(52,211,153,.25); color: #34d399;">
                {{ session('success') }}
            </div>
        @endif

        {{-- Kanban Board — padrão Mirbal (classes .kb-* em app.css) --}}
        <div id="opportunities-board" class="kb-board" style="min-height: 70vh;"
             data-kanban-board data-status-field="stage">

            @php
                $stageColors = [
                    'novo_lead'           => 'var(--muted)',
                    'qualificacao'        => 'var(--purple)',
                    'diagnostico_reuniao' => 'var(--purple)',
                    'proposta_enviada'    => 'var(--orange)',
                    'negociando'          => 'var(--orange)',
                ];
                $openStages = ['novo_lead', 'qualificacao', 'diagnostico_reuniao', 'proposta_enviada', 'negociando'];
            @endphp

            @foreach($openStages as $stage)
                @php
                    $stageCards = $opportunities->get($stage, collect());
                    $stageMeta  = \App\Models\Opportunity::$stages[$stage];
                @endphp
                <div class="kb-col" style="--kb-bar: {{ $stageColors[$stage] ?? 'var(--purple)' }}; width:280px"
                     data-kanban-column data-status="{{ $stage }}">

                    <div class="kb-col-head">
                        <h5 class="card-title truncate">{{ $stageMeta['label'] }}</h5>
                        <span class="kb-count" data-kanban-count>{{ $stageCards->count() }}</span>
                    </div>

                    <div class="kb-list" data-kanban-list>
                        @forelse($stageCards as $opp)
                            <div class="kb-card cursor-pointer group"
                                 data-kanban-card data-id="{{ $opp->id }}" data-update-url="{{ route('opportunities.update-stage', $opp) }}"
                                 x-data="{ open: false }">

                                {{-- Tipo (quando não é cliente novo) + serviços de interesse --}}
                                @if(($opp->type ?? 'novo_cliente') !== 'novo_cliente' || $opp->services_interest)
                                    <div class="kb-card-top flex-wrap">
                                        @if(($opp->type ?? 'novo_cliente') !== 'novo_cliente')
                                            <span class="badge tag-orange">{{ $opp->typeLabel() }}</span>
                                        @endif
                                        @foreach(array_slice($opp->services_interest ?? [], 0, 3) as $svc)
                                            <span class="badge tag-purple">{{ \App\Models\Client::$services[$svc] ?? $svc }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <a href="{{ route('opportunities.show', $opp) }}" class="kb-card-title group-hover:text-[var(--purple)] transition-colors">
                                    {{ $opp->title }}
                                </a>
                                <p class="kb-card-desc">
                                    {{ $opp->contact->name }}@if($opp->contact->company_name) · {{ $opp->contact->company_name }}@endif
                                </p>

                                <div class="kb-card-foot">
                                    <span>{{ $opp->created_at->diffForHumans() }}</span>
                                    @if($opp->proposed_fee)
                                        <span class="font-medium" style="color:var(--green)">R$ {{ number_format($opp->proposed_fee, 0, ',', '.') }}/mês</span>
                                    @endif
                                </div>

                                {{-- Mover etapa / ganho / perdido — aparece ao passar o mouse --}}
                                <div class="flex flex-wrap items-center gap-1 mt-2.5 pt-2.5 opacity-0 group-hover:opacity-100 transition-opacity" style="border-top:1px solid var(--border2)">
                                    @if($stage !== 'novo_lead')
                                        @php $prevStage = $openStages[array_search($stage, $openStages) - 1]; @endphp
                                        <button onclick="moveStage('{{ $opp->id }}', '{{ $prevStage }}')" class="btn btn-ghost btn-xs">← Voltar</button>
                                    @endif
                                    @if($stage !== 'negociando')
                                        @php $nextStage = $openStages[array_search($stage, $openStages) + 1]; @endphp
                                        <button onclick="moveStage('{{ $opp->id }}', '{{ $nextStage }}')" class="btn btn-ghost btn-xs">Avançar →</button>
                                    @endif
                                    <button type="button" class="btn btn-soft-green btn-xs"
                                            @click.stop="openWin(@js([
                                                'winUrl'        => route('opportunities.win', $opp),
                                                'title'         => $opp->title,
                                                'typeLabel'     => $opp->typeLabel(),
                                                'createsClient' => $opp->createsClient(),
                                                'companyName'   => $opp->contact->company_name,
                                                'clientId'      => $opp->client_id,
                                            ]))">
                                        ✓ Ganho
                                    </button>
                                    <button type="button" class="btn btn-soft-red btn-xs"
                                            @click.stop="openLose(@js([
                                                'loseUrl' => route('opportunities.lose', $opp),
                                                'title'   => $opp->title,
                                            ]))">
                                        ✗ Perdido
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="kb-empty">Nenhuma oportunidade</div>
                        @endforelse
                    </div>

                </div>
            @endforeach

        </div>

        {{-- Ganhas / Perdidas recentes --}}
        @if($closed->isNotEmpty())
            <div class="mt-10">
                <h2 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Encerradas recentemente</h2>
                <div class="card">
                    <div class="overflow-x-auto">
                    <table class="nonna-table">
                        <thead>
                            <tr>
                                <th>Oportunidade</th>
                                <th>Contato</th>
                                <th style="width:110px">Resultado</th>
                                <th>Valor</th>
                                <th>Encerrada</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($closed as $opp)
                                <tr>
                                    <td class="font-semibold text-[var(--text)]">{{ $opp->title }}</td>
                                    <td class="text-sm text-[var(--muted2)]">{{ $opp->contact->name }}</td>
                                    <td class="monday-fill-td relative" style="width:110px"
                                        title="{{ $opp->stage === 'perdido' && $opp->lost_reason ? $opp->lost_reason : '' }}">
                                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                                                    background:{{ $opp->stage === 'ganho' ? '#059669' : '#dc2626' }}; color:#fff;
                                                    font-size:11px; font-weight:700">
                                            {{ $opp->stage === 'ganho' ? 'Ganho' : 'Perdido' }}
                                        </div>
                                    </td>
                                    <td class="text-sm font-mono text-[var(--muted2)]">
                                        {{ $opp->proposed_fee ? 'R$ ' . number_format($opp->proposed_fee, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-xs font-mono text-[var(--muted)]">
                                        {{ ($opp->won_at ?? $opp->lost_at)?->format('d/m/Y') ?? '—' }}
                                    </td>
                                    <td>
                                        @if($opp->stage === 'ganho' && $opp->client_id)
                                            <a href="{{ route('clients.show', $opp->client_id) }}" class="btn btn-ghost btn-xs">
                                                Ver Cliente →
                                            </a>
                                        @else
                                            <a href="{{ route('opportunities.show', $opp) }}" class="btn btn-ghost btn-xs">
                                                Ver →
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Modal: Fechar Negócio (kanban) --}}
        <div x-show="winOpp" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(0,0,0,0.7);"
             @keydown.escape.window="winOpp = null">
            <div class="card p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-black text-[var(--text)] mb-1">Fechar Negócio</h3>
                <p class="text-xs text-[var(--muted)] mb-4" x-text="winOpp?.title"></p>
                <form :action="winOpp?.winUrl" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <template x-if="winOpp && winOpp.createsClient">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                        Nome da empresa / Razão Social *
                                    </label>
                                    <input type="text" name="company_name" :value="winOpp?.companyName" required
                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                        Verba de mídia mensal
                                    </label>
                                    <input type="text" name="monthly_ad_budget" placeholder="Ex: R$ 3.000 / mês"
                                           class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                </div>
                                <p class="text-xs text-[var(--muted)]">Serviços contratados = serviços de interesse da oportunidade (ajustáveis depois na ficha do cliente).</p>
                            </div>
                        </template>
                        <template x-if="winOpp && !winOpp.createsClient">
                            <div>
                                <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                    Cliente vinculado
                                </label>
                                <select name="client_id"
                                        class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                    <option value="">— resolver pelo contato —</option>
                                    @foreach($clients as $c)
                                        <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-[var(--muted)] mt-1">Não cria cliente novo — só fecha como ganha.</p>
                            </div>
                        </template>
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                Observações internas
                            </label>
                            <textarea name="notes" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-none"></textarea>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button type="submit" class="flex-1 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                style="background: var(--green);">
                            Confirmar Ganho →
                        </button>
                        <button type="button" @click="winOpp = null"
                                class="px-4 py-2.5 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal: Marcar Perdido (kanban) --}}
        <div x-show="loseOpp" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center" style="background: rgba(0,0,0,0.7);"
             @keydown.escape.window="loseOpp = null">
            <div class="card p-6 w-full max-w-sm mx-4">
                <h3 class="text-lg font-black text-[var(--text)] mb-1">Marcar como Perdido</h3>
                <p class="text-xs text-[var(--muted)] mb-4" x-text="loseOpp?.title"></p>
                <form :action="loseOpp?.loseUrl" method="POST">
                    @csrf
                    <div>
                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                            Motivo da perda
                        </label>
                        <textarea name="lost_reason" rows="3" placeholder="Preço, concorrente, timing, desistência..."
                                  class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--red)] resize-none"></textarea>
                    </div>
                    <div class="flex gap-3 mt-4">
                        <button type="submit" class="flex-1 py-2.5 text-xs font-bold font-mono uppercase tracking-widest"
                                style="background: rgba(248,113,113,.15); border: 1px solid var(--red); color: var(--red);">
                            Confirmar Perda
                        </button>
                        <button type="button" @click="loseOpp = null"
                                class="px-4 py-2.5 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
    function kanban() {
        return {
            winOpp: null,
            loseOpp: null,
            openWin(data) { this.loseOpp = null; this.winOpp = data; },
            openLose(data) { this.winOpp = null; this.loseOpp = data; },
        };
    }

    function moveStage(id, stage) {
        fetch(`/oportunidades/${id}/stage`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify({ stage }),
        }).then(r => r.json()).then(data => {
            if (data.ok) window.location.reload();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('opportunities-board')) {
            initKanbanDnd('#opportunities-board');
        }
    });
    </script>
</x-app-layout>
