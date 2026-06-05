<x-app-layout>
    <x-slot name="header">
        {{ __('Dashboard Principal') }}
    </x-slot>

    <!-- Removido o max-w-7xl para deixar o painel se esticar de forma fluida de ponta a ponta -->
    <div class="w-full">
        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-800">
            
            <div class="p-8 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-x-2">
                            🤝 Carteira de Clientes Ativos (CRM Sincronizado)
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Dados reais obtidos diretamente da base de dados PostgreSQL na Hetzner.</p>
                    </div>
                </div>
                
                @if($clients->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum cliente encontrado no seu banco de dados remoto.</p>
                @else
                    <div class="overflow-x-auto border border-gray-200 dark:border-gray-800 rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID ClickUp</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nome da Empresa</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Website</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">CNPJ</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach($clients as $client)
                                    <tr class="hover:bg-indigo-50/20 dark:hover:bg-indigo-950/20 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ $client->client_task_id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-gray-100">
                                            {{ $client->company_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                            @if($client->website)
                                                <a href="{{ $client->website }}" target="_blank" class="flex items-center gap-x-1">
                                                    {{ $client->website }}
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                                </a>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500 dark:text-gray-400">
                                            {{ $client->cnpj ?? 'Não informado' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>