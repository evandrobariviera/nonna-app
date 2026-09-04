<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Editar Playbook — {{ $playbook->name }}</span>
    </x-slot>

    @include('playbooks._form', [
        'action'          => route('playbooks.update', $playbook),
        'method'          => 'PATCH',
        'playbook'        => $playbook,
        'functionalRoles' => $functionalRoles,
    ])
</x-app-layout>
