{{-- Fragmento reaproveitado no load inicial (macroplans.index) e na busca dinâmica via
     AJAX (MacroPlanController::results(), fetch disparado por live-filter.js). --}}
<div class="card">
    @if($macroplans->isEmpty())
        <div class="px-6 py-16 text-center" style="color:var(--muted)">
            <p class="text-sm mb-3">Nenhum planejamento encontrado.</p>
            <a href="{{ route('macroplans.create') }}" class="text-sm font-semibold" style="color:var(--purple)">
                Criar primeiro planejamento →
            </a>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="nonna-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Cliente</th>
                        <th>Período</th>
                        <th>Projetos</th>
                        <th>Responsável</th>
                        <th style="width:120px">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($macroplans as $plan)
                        <tr>
                            <td>
                                <a href="{{ route('macroplans.edit', $plan) }}"
                                   class="font-semibold text-sm hover:underline" style="color:var(--text)">
                                    {{ $plan->title }}
                                </a>
                                @if($plan->isLaunched())
                                    <span class="badge badge-green ml-1">ClickUp</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('clients.show', $plan->client) }}"
                                   class="text-xs font-semibold hover:underline" style="color:var(--purple)">
                                    {{ $plan->client->company_name }}
                                </a>
                            </td>
                            <td class="text-xs font-mono whitespace-nowrap" style="color:var(--muted2)">
                                {{ $plan->period_start->format('d/m/Y') }}<br>
                                {{ $plan->period_end->format('d/m/Y') }}
                            </td>
                            <td class="text-sm" style="color:var(--muted2)">
                                {{ $plan->projects_count ?? $plan->projects->count() }}
                            </td>
                            <td class="text-xs" style="color:var(--muted2)">{{ $plan->responsible->name ?? '—' }}</td>
                            <x-status-dropdown-cell :options="\App\Models\MacroPlan::$statuses" :current="$plan->status"
                                :action="route('macroplans.update-status', $plan)" :width="120" />
                            <td class="text-right">
                                <a href="{{ route('macroplans.edit', $plan) }}" class="btn btn-ghost btn-xs">
                                    Editar →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($macroplans->hasPages())
            <div class="px-6 py-4 flex items-center justify-between" style="border-top:1px solid var(--border)">
                <span class="text-xs font-mono" style="color:var(--muted)">
                    {{ $macroplans->firstItem() }}–{{ $macroplans->lastItem() }} de {{ $macroplans->total() }}
                </span>
                <div class="flex gap-2">
                    @if($macroplans->onFirstPage())
                        <span class="badge opacity-30">← Anterior</span>
                    @else
                        <a href="{{ $macroplans->previousPageUrl() }}" class="badge">← Anterior</a>
                    @endif
                    @if($macroplans->hasMorePages())
                        <a href="{{ $macroplans->nextPageUrl() }}" class="badge">Próxima →</a>
                    @else
                        <span class="badge opacity-30">Próxima →</span>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>
