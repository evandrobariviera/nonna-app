<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Novo Agente de IA</span>
    </x-slot>

    @include('ai.agents._form', [
        'action'  => route('ai.agents.store'),
        'method'  => 'POST',
        'agent'   => null,
    ])
</x-app-layout>
