{{-- Fragmento reaproveitado no load inicial (tickets.index) e na busca dinâmica via
     AJAX (TicketController::results(), fetch disparado por live-filter.js). --}}
<p class="text-sm text-right mb-2" style="color:var(--muted)">
    {{ $tickets->total() }} ticket{{ $tickets->total() !== 1 ? 's' : '' }}
</p>

<div x-data="taskBulk()" x-cloak>
    @include('partials._task-bulk-bar')

    {{-- ── MOBILE: cards (seleção em massa é desktop-only) ── --}}
    <div class="card overflow-hidden md:hidden">
        @forelse($tickets as $ticket)
            @include('partials._task-card', ['task' => $ticket, 'context' => 'ticket'])
        @empty
            <div class="tab-placeholder">
                <div class="tab-placeholder-icon"><x-icon name="ticket" size="32" /></div>
                <div class="tab-placeholder-title">Nenhum ticket encontrado</div>
                <div class="tab-placeholder-desc">Ajuste os filtros ou crie um novo ticket.</div>
            </div>
        @endforelse
    </div>

    {{-- ── DESKTOP: tabela ── --}}
    <div class="card overflow-hidden hidden md:block">
        <div class="overflow-x-auto">
            <table class="nonna-table">
                @include('partials._task-thead')
                <tbody>
                    @forelse($tickets as $ticket)
                        @include('partials._task-tr', ['task' => $ticket, 'context' => 'ticket'])
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="tab-placeholder">
                                    <div class="tab-placeholder-icon"><x-icon name="ticket" size="32" /></div>
                                    <div class="tab-placeholder-title">Nenhum ticket encontrado</div>
                                    <div class="tab-placeholder-desc">Ajuste os filtros ou crie um novo ticket.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>{{-- /x-data taskBulk --}}

<div class="mt-4">{{ $tickets->links() }}</div>
