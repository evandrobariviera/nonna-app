{{-- Fragmento reaproveitado no load inicial (contacts.index) e na busca dinâmica via
     AJAX (ContactController::results(), fetch disparado por live-filter.js). --}}
<div class="card">
    <table class="nonna-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Empresa</th>
                <th>Contato</th>
                <th>Origem</th>
                <th style="width:110px">Status</th>
                <th>Criado em</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $contact)
                <tr>
                    <td>
                        <a href="{{ route('contacts.show', $contact) }}"
                           class="font-semibold text-[var(--text)] hover:text-[var(--purple)] transition-colors">
                            {{ $contact->name }}
                        </a>
                        @if($contact->job_title)
                            <div class="text-xs text-[var(--muted)] mt-0.5">{{ $contact->job_title }}</div>
                        @endif
                    </td>
                    <td class="text-sm text-[var(--muted2)]">
                        {{ $contact->company_name ?: '—' }}
                    </td>
                    <td class="text-sm text-[var(--muted2)]">
                        @if($contact->email)
                            <div>{{ $contact->email }}</div>
                        @endif
                        @if($contact->whatsapp ?: $contact->phone)
                            <div class="text-xs text-[var(--muted)]">{{ $contact->whatsapp ?: $contact->phone }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge">
                            {{ \App\Models\Contact::$sources[$contact->source] ?? $contact->source }}
                        </span>
                    </td>
                    <td class="monday-fill-td relative" style="width:110px">
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
                                    background:{{ \App\Models\Task::colorHex($contact->statusColor()) }}; color:#fff;
                                    font-size:11px; font-weight:700; overflow:hidden; padding:0 8px">
                            <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ $contact->statusLabel() }}</span>
                        </div>
                    </td>
                    <td class="text-xs text-[var(--muted)] font-mono">
                        {{ $contact->created_at->format('d/m/Y') }}
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('opportunities.create', ['contact_id' => $contact->id]) }}" class="btn btn-ghost btn-xs whitespace-nowrap">
                                + Oportunidade
                            </a>
                            <a href="{{ route('contacts.show', $contact) }}" class="btn btn-ghost btn-xs">
                                Ver →
                            </a>
                            <form method="POST" action="{{ route('contacts.destroy', $contact) }}"
                                  @submit.prevent="if (await $store.confirmDialog.ask('Excluir {{ addslashes($contact->name) }}? Só funciona se não houver nenhuma associação (cliente vinculado, oportunidade, acesso ao portal, etc) — senão use o status Inativo.')) $el.submit()">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-xs">
                                    Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-16 text-[var(--muted)]">
                        <div class="text-2xl mb-3 opacity-30">👤</div>
                        <div class="text-sm font-semibold text-[var(--muted2)] mb-1">Nenhum contato cadastrado</div>
                        <div class="text-xs text-[var(--muted)]">Cadastre o primeiro lead para iniciar o funil comercial.</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Paginação --}}
@if($contacts->hasPages())
    <div class="mt-4">
        {{ $contacts->links() }}
    </div>
@endif
