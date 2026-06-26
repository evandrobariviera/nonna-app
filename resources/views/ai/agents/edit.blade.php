<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Editar Agente — {{ $agent->name }}</span>
    </x-slot>

    @include('ai.agents._form', [
        'action' => route('ai.agents.update', $agent),
        'method' => 'PATCH',
        'agent'  => $agent,
    ])
</x-app-layout>
