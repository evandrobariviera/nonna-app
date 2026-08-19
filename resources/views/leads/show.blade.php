<x-app-layout>
    <x-slot name="header">{{ $opportunity->lead->name ?: 'Lead sem nome' }}</x-slot>

    <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
        <div>
            <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">
                <a href="{{ route('leads.index') }}" class="hover:text-[var(--purple)]">Central de Leads</a> /
                {{ $opportunity->lead->name ?: 'Lead sem nome' }}
            </p>
            <h1 class="text-2xl font-black text-[var(--text)]">{{ $opportunity->lead->name ?: 'Lead sem nome' }}</h1>
            <div class="mt-1">
                <span class="badge badge-{{ $opportunity->stageColor() }}">
                    {{ $opportunity->stageLabel() }}
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('leads.update-stage', $opportunity) }}" class="flex items-center gap-2">
            @csrf
            @method('PATCH')
            <select name="stage" onchange="this.form.submit()"
                    style="background:var(--s2); border:1px solid var(--border2); color:var(--text); padding:8px 12px; font-size:13px; outline:none; cursor:pointer">
                @foreach(\App\Models\ClientLeadOpportunity::$stages as $key => $s)
                    <option value="{{ $key }}" {{ $opportunity->stage === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </form>
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
                        {{ strtoupper(substr($opportunity->lead->name ?: '?', 0, 1)) }}
                    </div>
                    <div>
                        <div class="font-semibold text-[var(--text)]">{{ $opportunity->lead->name ?: 'Sem nome' }}</div>
                        @if($opportunity->lead->city || $opportunity->lead->state)
                            <div class="text-xs text-[var(--muted2)]">{{ trim(($opportunity->lead->city ?? '') . ' / ' . ($opportunity->lead->state ?? ''), ' /') }}</div>
                        @endif
                    </div>
                    <div class="ml-auto text-right text-xs text-[var(--muted)]">
                        @if($opportunity->lead->email)
                            <div>{{ $opportunity->lead->email }}</div>
                        @endif
                        @if($opportunity->lead->phone)
                            <div>{{ $opportunity->lead->phone }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Attribution --}}
            <div class="card p-5">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">Atribuição</h3>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div><span class="text-[var(--muted)]">Canal:</span> <span class="text-[var(--text)]">{{ $opportunity->channel?->kindLabel() ?? '—' }}</span></div>
                    <div><span class="text-[var(--muted)]">Fonte:</span> <span class="text-[var(--text)]">{{ $opportunity->source?->displayName() ?? '—' }}</span></div>
                    <div><span class="text-[var(--muted)]">Formulário:</span> <span class="text-[var(--text)]">{{ $opportunity->form_name ?? '—' }}</span></div>
                    <div><span class="text-[var(--muted)]">UTM Source:</span> <span class="text-[var(--text)]">{{ $opportunity->utm_source ?? '—' }}</span></div>
                    <div><span class="text-[var(--muted)]">UTM Medium:</span> <span class="text-[var(--text)]">{{ $opportunity->utm_medium ?? '—' }}</span></div>
                    <div><span class="text-[var(--muted)]">UTM Campaign:</span> <span class="text-[var(--text)]">{{ $opportunity->utm_campaign ?? '—' }}</span></div>
                    @if($opportunity->landing_page_url)
                        <div class="col-span-2">
                            <span class="text-[var(--muted)]">Landing page:</span>
                            <a href="{{ $opportunity->landing_page_url }}" target="_blank" class="text-[var(--purple)] hover:underline break-all">{{ $opportunity->landing_page_url }}</a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Assign / Lost reason --}}
            <div class="card p-5">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-3">Responsável</h3>
                <form method="POST" action="{{ route('leads.update', $opportunity) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <select name="assigned_to" onchange="this.form.submit()"
                                class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--purple)]">
                            <option value="">Sem responsável</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ $opportunity->assigned_to == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($opportunity->stage === 'perdido')
                        <div>
                            <label class="block text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Motivo da Perda</label>
                            <textarea name="lost_reason" rows="2"
                                      class="w-full bg-[var(--s3)] border border-[var(--border2)] text-sm text-[var(--text)] px-4 py-2.5 focus:outline-none focus:border-[var(--red)] resize-none">{{ $opportunity->lost_reason }}</textarea>
                            <button type="submit" class="btn btn-primary btn-sm mt-2">Salvar motivo</button>
                        </div>
                    @endif
                </form>
            </div>

            @include('leads._notes', ['opportunity' => $opportunity, 'notesStoreRoute' => route('leads.notes.store', $opportunity)])

        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">

            @if($opportunity->lead->client)
                <div class="card p-4" style="border-color: rgba(52,211,153,.2);">
                    <h3 class="text-xs font-mono uppercase tracking-widest mb-3" style="color: var(--green);">Cliente</h3>
                    <a href="{{ route('clients.show', $opportunity->lead->client_id) }}"
                       class="text-sm font-semibold text-[var(--text)] hover:text-[var(--purple)] transition-colors">
                        {{ $opportunity->lead->client->displayName() }}
                    </a>
                </div>
            @else
                <div class="card p-4" style="border-color: rgba(238,121,25,.25);">
                    <h3 class="text-xs font-mono uppercase tracking-widest mb-2" style="color: var(--orange);">Sem cliente identificado</h3>
                    <p class="text-xs text-[var(--muted)]">
                        Nenhuma fonte cadastrada bateu com o identificador recebido — este lead ficou em triagem manual.
                    </p>
                </div>
            @endif

            <div class="card p-4 space-y-3">
                <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)]">Timeline</h3>
                <div class="text-xs text-[var(--muted2)]">
                    <div class="text-[var(--muted)] mb-0.5">Convertido em</div>
                    {{ ($opportunity->received_at ?? $opportunity->created_at)->format('d/m/Y H:i') }}
                </div>
                @if($opportunity->won_at)
                    <div class="text-xs text-[var(--green)]">
                        <div class="text-[var(--muted)] mb-0.5">Ganho em</div>
                        {{ $opportunity->won_at->format('d/m/Y') }}
                    </div>
                @endif
                @if($opportunity->lost_at)
                    <div class="text-xs" style="color: var(--red);">
                        <div class="text-[var(--muted)] mb-0.5">Perdido em</div>
                        {{ $opportunity->lost_at->format('d/m/Y') }}
                    </div>
                @endif
            </div>

            @if($opportunity->assignedTo)
                <div class="card p-4">
                    <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-2">Responsável</h3>
                    <div class="text-sm text-[var(--text)]">{{ $opportunity->assignedTo->name }}</div>
                </div>
            @endif

        </div>
    </div>

</x-app-layout>
