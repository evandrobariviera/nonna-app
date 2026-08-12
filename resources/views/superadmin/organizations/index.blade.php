<x-superadmin-layout>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black" style="color: var(--text)">Organizações</h1>
            <p class="text-sm mt-1" style="color: var(--muted)">{{ $organizations->total() }} organizações cadastradas</p>
        </div>
        <a href="{{ route('superadmin.organizations.create') }}"
           class="btn-primary px-4 py-2 text-sm rounded-lg font-semibold flex items-center gap-2">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Nova Organização
        </a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="border-bottom: 1px solid var(--border); background: var(--s3)">
                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color: var(--muted)">Organização</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color: var(--muted)">Responsável</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color: var(--muted)">Plano</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color: var(--muted)">Status</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color: var(--muted)">Usuários</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold" style="color: var(--muted)">Criada</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($organizations as $org)
                    @php
                        $statusColors = [
                            'active'    => ['bg' => 'rgba(5,150,105,.1)',  'text' => 'var(--green)'],
                            'trial'     => ['bg' => 'rgba(238, 121, 25,.1)',  'text' => 'var(--orange)'],
                            'suspended' => ['bg' => 'rgba(220,38,38,.1)', 'text' => 'var(--red)'],
                            'cancelled' => ['bg' => 'var(--s3)',           'text' => 'var(--muted)'],
                        ];
                        $sc = $statusColors[$org->status] ?? $statusColors['cancelled'];
                    @endphp
                    <tr style="border-bottom: 1px solid var(--border2)">
                        <td class="px-5 py-3.5">
                            <p class="font-semibold" style="color: var(--text)">{{ $org->name }}</p>
                            <p class="text-xs" style="color: var(--muted)">{{ $org->slug }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="text-xs" style="color: var(--text)">{{ $org->owner?->name }}</p>
                            <p class="text-xs" style="color: var(--muted)">{{ $org->owner?->email }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                  style="background: rgba(100, 59, 142,.1); color: var(--purple)">
                                {{ \App\Models\Organization::$plans[$org->plan] ?? $org->plan }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                  style="background: {{ $sc['bg'] }}; color: {{ $sc['text'] }}">
                                {{ \App\Models\Organization::$statuses[$org->status] ?? $org->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs" style="color: var(--muted)">
                            {{ $org->users_count }}
                        </td>
                        <td class="px-5 py-3.5 text-xs" style="color: var(--muted)">
                            {{ $org->created_at->format('d/m/Y') }}
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <a href="{{ route('superadmin.organizations.edit', $org) }}" class="btn btn-ghost btn-xs">
                                Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-10 text-center text-sm" style="color: var(--muted)">
                            Nenhuma organização cadastrada.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($organizations->hasPages())
        <div class="mt-4">{{ $organizations->links() }}</div>
    @endif

</x-superadmin-layout>
