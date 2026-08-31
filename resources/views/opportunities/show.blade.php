<x-app-layout>
    <x-slot name="header">{{ $opportunity->title }}</x-slot>

    {{-- BREADCRUMB + AÇÕES --}}
    <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">
                <a href="{{ route('opportunities.index') }}" class="hover:text-[var(--purple)]">Oportunidades</a> /
                {{ $opportunity->title }}
            </p>
            <h1 class="text-2xl font-black text-[var(--text)]">{{ $opportunity->title }}</h1>
            <div class="mt-1 flex items-center gap-2">
                <span class="badge badge-{{ $opportunity->stageColor() }}">
                    {{ $opportunity->stageLabel() }}
                </span>
                <span class="badge" style="font-size:10px;">{{ $opportunity->typeLabel() }}</span>
            </div>
        </div>

        @if($opportunity->isOpen())
                <div class="flex items-center gap-3" x-data="{ winModal: false, loseModal: false }">

                    <a href="{{ route('opportunities.edit', $opportunity) }}"
                       class="px-4 py-2 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] hover:border-[var(--purple)] transition-colors">
                        Editar
                    </a>

                    <button @click="loseModal = true"
                            class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest border border-[var(--red)] text-[var(--red)] hover:bg-red-900/20 transition-colors">
                        Marcar Perdido
                    </button>

                    <button @click="winModal = true"
                            class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white"
                            style="background: var(--green);">
                        Fechar Negócio ✓
                    </button>

                    {{-- Win Modal --}}
                    <div x-show="winModal" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center"
                         style="background: rgba(0,0,0,0.7);">
                        <div class="card p-6 w-full max-w-md mx-4">
                            <h3 class="text-lg font-black text-[var(--text)] mb-1">Fechar Negócio</h3>
                            <p class="text-xs text-[var(--muted)] mb-4">{{ $opportunity->typeLabel() }}</p>
                            <form method="POST" action="{{ route('opportunities.win', $opportunity) }}">
                                @csrf
                                <div class="space-y-4">
                                    @if($opportunity->createsClient())
                                    <div>
                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                            Nome da empresa / Razão Social *
                                        </label>
                                        <input type="text" name="company_name"
                                               value="{{ $opportunity->contact->company_name }}" required
                                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                            Serviços contratados
                                        </label>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach(\App\Models\Client::$services as $key => $label)
                                                <label class="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" name="contracted_services[]" value="{{ $key }}"
                                                           {{ in_array($key, $opportunity->services_interest ?? []) ? 'checked' : '' }}
                                                           class="accent-[var(--purple)]">
                                                    <span class="text-sm text-[var(--muted2)]">{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                            Verba de mídia mensal
                                        </label>
                                        <input type="text" name="monthly_ad_budget"
                                               value="{{ $opportunity->proposed_ad_budget ? 'R$ ' . number_format($opportunity->proposed_ad_budget, 0, ',', '.') . ' / mês' : '' }}"
                                               placeholder="Ex: R$ 3.000 / mês"
                                               class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                    </div>
                                    @else
                                    <div>
                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                            Cliente vinculado
                                        </label>
                                        <select name="client_id"
                                                class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                                            <option value="">— resolver pelo contato —</option>
                                            @foreach($clients as $c)
                                                <option value="{{ $c->id }}"
                                                    {{ $opportunity->client_id === $c->id ? 'selected' : '' }}>
                                                    {{ $c->displayName() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-[var(--muted)] mt-1">Não cria cliente novo — só fecha como ganha.</p>
                                    </div>
                                    @endif
                                    <div>
                                        <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                            Observações internas
                                        </label>
                                        <textarea name="notes" rows="2"
                                                  class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)] resize-none"></textarea>
                                    </div>
                                </div>
                                <div class="flex gap-3 mt-5">
                                    <button type="submit"
                                            class="flex-1 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                                            style="background: var(--green);">
                                        {{ $opportunity->createsClient() ? 'Criar Cliente →' : 'Fechar como Ganha →' }}
                                    </button>
                                    <button type="button" @click="winModal = false"
                                            class="px-4 py-2.5 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Lose Modal --}}
                    <div x-show="loseModal" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center"
                         style="background: rgba(0,0,0,0.7);">
                        <div class="card p-6 w-full max-w-sm mx-4">
                            <h3 class="text-lg font-black text-[var(--text)] mb-4">Marcar como Perdido</h3>
                            <form method="POST" action="{{ route('opportunities.lose', $opportunity) }}">
                                @csrf
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">
                                        Motivo da perda
                                    </label>
                                    <textarea name="lost_reason" rows="3"
                                              placeholder="Preço, concorrente, timing, desistência..."
                                              class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--red)] resize-none"></textarea>
                                </div>
                                <div class="flex gap-3 mt-4">
                                    <button type="submit"
                                            class="flex-1 py-2.5 text-xs font-bold font-mono uppercase tracking-widest"
                                            style="background: rgba(248,113,113,.15); border: 1px solid var(--red); color: var(--red);">
                                        Confirmar Perda
                                    </button>
                                    <button type="button" @click="loseModal = false"
                                            class="px-4 py-2.5 text-xs font-mono border border-[var(--border2)] text-[var(--muted2)] hover:text-[var(--text)] transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
        @endif
    </div>

    <div class="py-8 px-6 grid grid-cols-3 gap-6">

        {{-- Main info --}}
        <div class="col-span-2 space-y-6">

            {{-- Contact card --}}
            <div class="card p-5">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">Contato</h3>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-black text-white flex-shrink-0"
                         style="background: var(--purple);">
                        {{ strtoupper(substr($opportunity->contact->name, 0, 1)) }}
                    </div>
                    <div>
                        <a href="{{ route('contacts.show', $opportunity->contact) }}"
                           class="font-semibold text-[var(--text)] hover:text-[var(--purple)] transition-colors">
                            {{ $opportunity->contact->name }}
                        </a>
                        @if($opportunity->contact->job_title)
                            <div class="text-xs text-[var(--muted)]">{{ $opportunity->contact->job_title }}</div>
                        @endif
                        @if($opportunity->contact->company_name)
                            <div class="text-xs text-[var(--muted2)]">{{ $opportunity->contact->company_name }}</div>
                        @endif
                    </div>
                    <div class="ml-auto text-right text-xs text-[var(--muted)]">
                        @if($opportunity->contact->email)
                            <div>{{ $opportunity->contact->email }}</div>
                        @endif
                        @if($opportunity->contact->whatsapp)
                            <div>{{ $opportunity->contact->whatsapp }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Services --}}
            @if($opportunity->services_interest)
                <div class="card p-5">
                    <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">Serviços de Interesse</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($opportunity->services_interest as $svc)
                            <span class="badge badge-purple">
                                {{ \App\Models\Client::$services[$svc] ?? $svc }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Notes --}}
            @if($opportunity->notes)
                <div class="card p-5">
                    <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">Observações</h3>
                    <p class="text-sm text-[var(--muted2)] leading-relaxed whitespace-pre-wrap">{{ $opportunity->notes }}</p>
                </div>
            @endif

            {{-- Lost reason --}}
            @if($opportunity->stage === 'perdido' && $opportunity->lost_reason)
                <div class="card p-5" style="border-color: rgba(248,113,113,.25);">
                    <h3 class="text-xs font-mono uppercase tracking-widest mb-3" style="color: var(--red);">Motivo da Perda</h3>
                    <p class="text-sm text-[var(--muted2)] leading-relaxed">{{ $opportunity->lost_reason }}</p>
                </div>
            @endif

        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">

            <div class="card p-4 space-y-3">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">Valores</h3>
                <div>
                    <div class="text-xs text-[var(--muted)] mb-1">Fee mensal</div>
                    <div class="text-lg font-bold" style="color: var(--green);">
                        {{ $opportunity->proposed_fee ? 'R$ ' . number_format($opportunity->proposed_fee, 0, ',', '.') : '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-[var(--muted)] mb-1">Verba de mídia</div>
                    <div class="text-base font-semibold text-[var(--text)]">
                        {{ $opportunity->proposed_ad_budget ? 'R$ ' . number_format($opportunity->proposed_ad_budget, 0, ',', '.') : '—' }}
                    </div>
                </div>
                @if($opportunity->contract_months)
                    <div>
                        <div class="text-xs text-[var(--muted)] mb-1">Prazo do contrato</div>
                        <div class="text-base font-semibold text-[var(--text)]">
                            {{ $opportunity->contract_months }} meses
                        </div>
                        @if($opportunity->proposed_fee && $opportunity->contract_months)
                            <div class="text-xs text-[var(--muted)] mt-0.5">
                                Total: R$ {{ number_format($opportunity->proposed_fee * $opportunity->contract_months, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>
                @endif
                @if($opportunity->proposal_url)
                    <div class="pt-2 border-t border-[var(--border)]">
                        <a href="{{ $opportunity->proposal_url }}" target="_blank"
                           class="flex items-center gap-2 text-xs font-mono text-[var(--purple)] hover:text-[var(--text)] transition-colors">
                            Ver Orçamento / Proposta →
                        </a>
                    </div>
                @endif
            </div>

            <div class="card p-4 space-y-3">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">Timeline</h3>
                <div class="text-xs text-[var(--muted2)]">
                    <div class="text-[var(--muted)] mb-0.5">Criada em</div>
                    {{ $opportunity->created_at->format('d/m/Y') }}
                </div>
                @if($opportunity->won_at)
                    <div class="text-xs text-[var(--green)]">
                        <div class="text-[var(--muted)] mb-0.5">Fechada em</div>
                        {{ $opportunity->won_at->format('d/m/Y') }}
                    </div>
                @endif
                @if($opportunity->lost_at)
                    <div class="text-xs" style="color: var(--red);">
                        <div class="text-[var(--muted)] mb-0.5">Perdida em</div>
                        {{ $opportunity->lost_at->format('d/m/Y') }}
                    </div>
                @endif
            </div>

            @if($opportunity->client_id)
                <div class="card p-4" style="border-color: rgba(52,211,153,.2);">
                    <h3 class="text-xs font-mono uppercase tracking-widest mb-3" style="color: var(--green);">Cliente Criado</h3>
                    <a href="{{ route('clients.show', $opportunity->client_id) }}"
                       class="text-sm font-semibold text-[var(--text)] hover:text-[var(--purple)] transition-colors">
                        {{ $opportunity->client?->displayName() ?? '—' }}
                    </a>
                    <div class="mt-2">
                        <a href="{{ route('clients.show', $opportunity->client_id) }}"
                           class="text-xs font-mono text-[var(--purple)] hover:text-[var(--text)] transition-colors">
                            Ver Ficha do Cliente →
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>
