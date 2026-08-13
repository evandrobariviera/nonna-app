<x-app-layout>
    <x-slot name="header">
        <span class="text-sm font-semibold" style="color:var(--text)">Logs — {{ $automation->name }}</span>
    </x-slot>

    <div style="max-width:900px">

        <div class="mb-5">
            <a href="{{ route('automations.index') }}"
               class="text-xs transition-colors" style="color:var(--muted)"
               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                ← Automações
            </a>
        </div>

        {{-- Resumo --}}
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="card" style="padding:.75rem 1.25rem; text-align:center">
                <div class="text-2xl font-bold" style="color:var(--text)">{{ $logs->total() }}</div>
                <div class="text-xs mt-1" style="color:var(--muted)">Total de execuções</div>
            </div>
            <div class="card" style="padding:.75rem 1.25rem; text-align:center">
                <div class="text-2xl font-bold" style="color:var(--green)">
                    {{ $logs->getCollection()->where('status','success')->count() }}
                </div>
                <div class="text-xs mt-1" style="color:var(--muted)">Bem-sucedidas (pág. atual)</div>
            </div>
            <div class="card" style="padding:.75rem 1.25rem; text-align:center">
                <div class="text-2xl font-bold" style="color:var(--red)">
                    {{ $logs->getCollection()->where('status','failed')->count() }}
                </div>
                <div class="text-xs mt-1" style="color:var(--muted)">Com erro (pág. atual)</div>
            </div>
        </div>

        @if($logs->isEmpty())
            <div class="py-12 text-center" style="border:1px dashed var(--border2)">
                <p class="text-sm" style="color:var(--muted)">Nenhuma execução registrada ainda.</p>
            </div>
        @else
            <div class="flex flex-col gap-2">
                @foreach($logs as $log)
                    @php
                        $dotColor = match($log->status) {
                            'success' => 'var(--green)',
                            'failed'  => 'var(--red)',
                            default   => 'var(--orange)',
                        };
                    @endphp
                    <div class="card" x-data="{ open: false }">
                        <div class="flex items-center gap-3 px-4 py-3 cursor-pointer" @click="open = !open">
                            <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:{{ $dotColor }}"></span>
                            <div style="flex:1; min-width:0">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="text-sm font-semibold" style="color:{{ $dotColor }}">{{ $log->statusLabel() }}</span>
                                    <span class="text-xs" style="color:var(--muted)">{{ $log->entity_type }}:{{ Str::limit($log->entity_id, 8, '…') }}</span>
                                    @if($log->duration_ms)
                                        <span class="text-xs" style="color:var(--muted2)">{{ $log->duration_ms }}ms</span>
                                    @endif
                                </div>
                                <div class="text-xs mt-0.5" style="color:var(--muted)">
                                    {{ $log->ran_at?->format('d/m/Y H:i:s') ?? $log->created_at->format('d/m/Y H:i:s') }}
                                </div>
                            </div>
                            <x-icon name="chevron-right" size="14" class="flex-shrink-0 transition-transform duration-200"
                                 x-bind:style="open ? 'transform:rotate(90deg)' : ''"
                                 style="color:var(--muted)" />
                        </div>

                        <div x-show="open" x-cloak style="padding:0 1rem 1rem">
                            @if($log->output)
                                <div class="p-3 mb-2" style="background:var(--s3)">
                                    <p class="text-xs font-semibold mb-1" style="color:var(--muted)">Output</p>
                                    <pre class="text-sm whitespace-pre-wrap" style="color:var(--text); font-family:inherit; line-height:1.6">{{ $log->output }}</pre>
                                </div>
                            @endif
                            @if($log->error_message)
                                <div class="p-3 mb-2" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.2)">
                                    <p class="text-xs font-semibold mb-1" style="color:var(--red)">Erro</p>
                                    <pre class="text-sm whitespace-pre-wrap" style="color:var(--red); font-family:inherit">{{ $log->error_message }}</pre>
                                </div>
                            @endif
                            @if($log->input_snapshot)
                                <details>
                                    <summary class="text-xs cursor-pointer" style="color:var(--muted)">Contexto enviado</summary>
                                    <pre class="text-xs mt-2 p-2 overflow-x-auto" style="color:var(--muted); background:var(--s2)">{{ json_encode($log->input_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5">{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>
