<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono text-[var(--muted)] uppercase tracking-widest mb-1">
                    <a href="{{ route('contacts.index') }}"
                       class="hover:text-[var(--purple)] transition-colors">Contatos</a> /
                    {{ $contact->name }}
                </p>
                <h1 class="text-2xl font-black text-[var(--text)]">{{ $contact->name }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-{{ $contact->statusColor() }}">{{ $contact->statusLabel() }}</span>
                <a href="{{ route('contacts.edit', $contact) }}" class="btn btn-ghost btn-sm">
                    Editar
                </a>
                <a href="{{ route('opportunities.create', ['contact_id' => $contact->id]) }}"
                   class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest text-white transition-opacity hover:opacity-90"
                   style="background:var(--orange)">
                    + Oportunidade
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex gap-5 items-start">

        {{-- Coluna principal --}}
        <div class="flex-1 min-w-0 flex flex-col gap-5">

            {{-- Dados principais --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Informações do Contato</p>
                </div>
                <div class="px-5 py-4">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        @if($contact->job_title)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Cargo</dt>
                                <dd style="color:var(--text)">{{ $contact->job_title }}</dd>
                            </div>
                        @endif
                        @if($contact->company_name)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Empresa</dt>
                                <dd style="color:var(--text)">{{ $contact->company_name }}</dd>
                            </div>
                        @endif
                        @if($contact->email)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">E-mail</dt>
                                <dd>
                                    <a href="mailto:{{ $contact->email }}" class="hover:underline font-mono text-xs" style="color:var(--purple)">{{ $contact->email }}</a>
                                </dd>
                            </div>
                        @endif
                        @if($contact->whatsapp)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">WhatsApp</dt>
                                <dd class="font-mono text-xs" style="color:var(--text)">{{ $contact->whatsapp }}</dd>
                            </div>
                        @endif
                        @if($contact->phone)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Telefone</dt>
                                <dd class="font-mono text-xs" style="color:var(--text)">{{ $contact->phone }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Origem</dt>
                            <dd><span class="badge">{{ \App\Models\Contact::$sources[$contact->source] ?? $contact->source }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Cadastrado em</dt>
                            <dd class="font-mono text-xs" style="color:var(--muted2)">{{ $contact->created_at->format('d/m/Y') }}</dd>
                        </div>
                    </dl>

                    @if($contact->notes)
                        <div class="mt-5 pt-5" style="border-top:1px solid var(--border2)">
                            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Observações</p>
                            <p class="text-sm whitespace-pre-wrap" style="color:var(--text)">{{ $contact->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Oportunidades --}}
            <div class="card">
                <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                        Oportunidades ({{ $contact->opportunities->count() }})
                    </p>
                    <a href="{{ route('opportunities.create', ['contact_id' => $contact->id]) }}" class="btn btn-ghost btn-xs">
                        + Nova →
                    </a>
                </div>
                @if($contact->opportunities->isEmpty())
                    <div class="px-5 py-6 text-center">
                        <p class="text-sm" style="color:var(--muted)">Nenhuma oportunidade vinculada.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="nonna-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Cliente</th>
                                    <th>Estágio</th>
                                    <th>Data</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($contact->opportunities as $opp)
                                    <tr>
                                        <td>
                                            <a href="{{ route('opportunities.show', $opp) }}"
                                               class="font-semibold text-sm hover:underline" style="color:var(--text)">
                                                {{ $opp->title }}
                                            </a>
                                        </td>
                                        <td class="text-xs" style="color:var(--muted2)">
                                            {{ $opp->client->displayName() ?? '—' }}
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $opp->stageColor() }}">{{ $opp->stageLabel() }}</span>
                                        </td>
                                        <td class="font-mono text-xs" style="color:var(--muted)">
                                            {{ $opp->created_at->format('d/m/Y') }}
                                        </td>
                                        <td class="text-right">
                                            <a href="{{ route('opportunities.show', $opp) }}" class="btn btn-ghost btn-xs">
                                                Ver →
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>

        {{-- Coluna lateral --}}
        <div class="flex flex-col gap-5" style="width:280px; flex-shrink:0">

            {{-- Clientes vinculados --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                        Clientes Vinculados ({{ $contact->clients->count() }})
                    </p>
                </div>
                <div class="px-5 py-4">
                    @if($contact->clients->isEmpty())
                        <p class="text-sm" style="color:var(--muted)">Nenhum cliente vinculado.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach($contact->clients as $client)
                                <li>
                                    <a href="{{ route('clients.show', $client) }}"
                                       class="font-semibold text-sm hover:underline block" style="color:var(--text)">
                                        {{ $client->displayName() }}
                                    </a>
                                    @if($client->pivot->role)
                                        <span class="text-xs" style="color:var(--muted)">
                                            {{ \App\Http\Controllers\ClientContactController::$roles[$client->pivot->role] ?? $client->pivot->role }}
                                        </span>
                                    @endif
                                    @if($client->pivot->is_primary)
                                        <span class="badge badge-purple ml-1">Principal</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
