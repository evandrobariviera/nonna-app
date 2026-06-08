<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    {{-- ── STAT CARDS ── --}}
    <div class="grid grid-cols-2 gap-4 mb-6 md:grid-cols-4">
        <div class="stat-card">
            <div class="stat-label">Clientes Ativos</div>
            <div class="stat-value grad-text">{{ $clients->count() }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Campanhas</div>
            <div class="stat-value" style="color:var(--muted2)">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Sprints em curso</div>
            <div class="stat-value" style="color:var(--muted2)">—</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Tickets abertos</div>
            <div class="stat-value" style="color:var(--muted2)">—</div>
        </div>
    </div>

    {{-- ── TABELA DE CLIENTES ── --}}
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <div>
                <p class="stat-label" style="margin-bottom:4px">CRM · PostgreSQL</p>
                <h2 class="text-base font-bold" style="color:var(--text)">Carteira de Clientes Ativos</h2>
            </div>
            <span class="badge badge-green">Sincronizado</span>
        </div>

        @if($clients->isEmpty())
            <div class="px-6 py-12 text-center" style="color:var(--muted)">
                <p class="text-sm">Nenhum cliente encontrado na base de dados remota.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="nonna-table">
                    <thead>
                        <tr>
                            <th>ID ClickUp</th>
                            <th>Empresa</th>
                            <th>Website</th>
                            <th>CNPJ</th>
                            <th>Últ. Sync</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clients as $client)
                            <tr>
                                <td>
                                    <span class="font-mono text-xs badge badge-purple">
                                        {{ $client->client_task_id }}
                                    </span>
                                </td>
                                <td>
                                    <span class="font-bold text-sm" style="color:var(--text)">
                                        {{ $client->company_name }}
                                    </span>
                                </td>
                                <td>
                                    @if($client->website)
                                        <a href="{{ $client->website }}" target="_blank"
                                           class="flex items-center gap-1 text-xs transition-colors"
                                           style="color:var(--purple)"
                                           onmouseover="this.style.color='var(--orange)'"
                                           onmouseout="this.style.color='var(--purple)'">
                                            {{ parse_url($client->website, PHP_URL_HOST) ?? $client->website }}
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    @else
                                        <span style="color:var(--muted)">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-mono text-xs" style="color:var(--muted2)">
                                        {{ $client->cnpj ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="font-mono text-xs" style="color:var(--muted)">
                                        {{ $client->last_synced_at ? \Carbon\Carbon::parse($client->last_synced_at)->diffForHumans() : '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-app-layout>
