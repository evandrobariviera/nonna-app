<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Novo Playbook</span>
    </x-slot>

    @include('playbooks._form', [
        'action'          => route('playbooks.store'),
        'method'          => 'POST',
        'playbook'        => null,
        'functionalRoles' => $functionalRoles,
    ])
</x-app-layout>
