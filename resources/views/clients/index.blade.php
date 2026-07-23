<x-app-layout>
    <x-slot name="header">Clientes</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(220,38,38,.08); border:1px solid rgba(220,38,38,.25); color:var(--red)">
            {{ session('error') }}
        </div>
    @endif

    {{-- TOOLBAR --}}
    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
        <form method="GET" action="{{ route('clients.index') }}" class="flex items-center gap-3 flex-1 min-w-0">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar empresa, CNPJ ou e-mail..."
                class="flex-1 min-w-0 px-4 py-2 text-sm font-medium"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--text); outline:none; max-width:360px">

            <select name="status" onchange="this.form.submit()"
                style="background:var(--s2); border:1px solid var(--border2); color:var(--muted2); padding:8px 12px; font-size:13px; font-family:'Syne',sans-serif; outline:none; cursor:pointer">
                <option value="">Todos os status</option>
                @foreach(App\Models\Client::$statuses as $key => $s)
                    <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('clients.create') }}" class="btn btn-primary flex items-center gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Novo Cliente
        </a>
    </div>

    {{-- TABELA --}}
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <div>
                <p class="stat-label" style="margin-bottom:2px">CRM · App</p>
                <h2 class="text-sm font-bold" style="color:var(--text)">
                    {{ $clients->total() }} cliente{{ $clients->total() !== 1 ? 's' : '' }}
                    {{ request('status') ? '· ' . App\Models\Client::$statuses[request('status')]['label'] : '' }}
                </h2>
            </div>
        </div>

        @if($clients->isEmpty())
            <div class="px-6 py-16 text-center" style="color:var(--muted)">
                <p class="text-sm mb-3">Nenhum cliente encontrado.</p>
                <a href="{{ route('clients.create') }}" class="text-sm font-semibold" style="color:var(--purple)">
                    Cadastrar primeiro cliente →
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Segmento</th>
                            <th>Contato</th>
                            <th>Serviços</th>
                            <th style="width:110px">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clients as $client)
                            <tr>
                                <td>
                                    <a href="{{ route('clients.show', $client) }}"
                                       class="font-bold text-sm hover:underline"
                                       style="color:var(--text)">
                                        {{ $client->company_name }}
                                    </a>
                                    @if($client->trade_name)
                                        <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $client->trade_name }}</p>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-xs" style="color:var(--muted2)">{{ $client->segment ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($client->responsible_name)
                                        <p class="text-xs font-semibold" style="color:var(--text)">{{ $client->responsible_name }}</p>
                                        <p class="text-xs" style="color:var(--muted); font-family:'IBM Plex Mono',monospace">{{ $client->contact_email }}</p>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($client->contracted_services)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($client->contracted_services, 0, 3) as $svc)
                                                <span class="badge">{{ App\Models\Client::$services[$svc] ?? $svc }}</span>
                                            @endforeach
                                            @if(count($client->contracted_services) > 3)
                                                <span class="badge">+{{ count($client->contracted_services) - 3 }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                <x-status-dropdown-cell :options="\App\Models\Client::$statuses" :current="$client->status"
                                    :action="route('clients.update-status', $client)" :width="110" />
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('clients.edit', $client) }}"
                                           class="text-xs font-semibold transition-colors"
                                           style="color:var(--muted)"
                                           onmouseover="this.style.color='var(--purple)'"
                                           onmouseout="this.style.color='var(--muted)'">
                                            Editar
                                        </a>
                                        <form method="POST" action="{{ route('clients.destroy', $client) }}"
                                              onsubmit="return confirm('Excluir {{ addslashes($client->company_name) }}? Só funciona se não houver nenhuma associação (contratos, contas, tarefas, etc) — senão use o status Inativo.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold transition-colors"
                                                style="color:var(--muted)"
                                                onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($clients->hasPages())
                <div class="px-6 py-4 border-t flex items-center justify-between" style="border-color:var(--border)">
                    <span class="text-xs" style="color:var(--muted); font-family:'IBM Plex Mono',monospace">
                        {{ $clients->firstItem() }}–{{ $clients->lastItem() }} de {{ $clients->total() }}
                    </span>
                    <div class="flex gap-2">
                        @if($clients->onFirstPage())
                            <span class="badge opacity-30">← Anterior</span>
                        @else
                            <a href="{{ $clients->previousPageUrl() }}" class="badge hover:border-purple-500 transition-colors">← Anterior</a>
                        @endif
                        @if($clients->hasMorePages())
                            <a href="{{ $clients->nextPageUrl() }}" class="badge hover:border-purple-500 transition-colors">Próxima →</a>
                        @else
                            <span class="badge opacity-30">Próxima →</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

</x-app-layout>
