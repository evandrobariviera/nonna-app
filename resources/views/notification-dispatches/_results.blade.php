{{-- Fragmento reaproveitado no load inicial e na busca dinâmica via AJAX
     (NotificationDispatchController::results(), fetch disparado por live-filter.js). --}}
@if($dispatches->isEmpty())
    <div class="py-12 text-center" style="border:1px dashed var(--border2)">
        <p class="text-sm" style="color:var(--muted)">Nenhum envio registrado com esses filtros.</p>
    </div>
@else
    <div class="flex flex-col gap-2">
        @foreach($dispatches as $dispatch)
            @php $color = 'var(--' . $dispatch->statusColor() . ')'; @endphp
            <div class="card" x-data="{ open: false }">
                <div class="flex items-center gap-3 px-4 py-3 cursor-pointer" @click="open = !open">
                    <span class="h-2 w-2 rounded-full flex-shrink-0" style="background:{{ $color }}"></span>
                    <div style="flex:1; min-width:0">
                        <div class="flex items-center gap-x-3 gap-y-1 flex-wrap">
                            <span class="text-sm font-semibold" style="color:{{ $color }}">{{ $dispatch->statusLabel() }}</span>
                            <span class="text-sm" style="color:var(--text)">{{ $dispatch->typeLabel() }}</span>
                            <span class="badge">{{ $dispatch->channelLabel() }}</span>
                            @if($dispatch->is_test)
                                <span class="badge">Teste</span>
                            @endif
                            @if($dispatch->related_type === \App\Models\ClientLeadOpportunity::class && $dispatch->related_id)
                                <a href="{{ route('leads.show', $dispatch->related_id) }}" @click.stop
                                   class="text-xs font-semibold" style="color:var(--purple)">Ver lead →</a>
                            @endif
                        </div>
                        <div class="text-xs mt-0.5 truncate" style="color:var(--muted)">
                            {{ $dispatch->created_at->format('d/m/Y H:i') }}
                            · {{ $dispatch->client_name ?: '—' }}
                            @if($dispatch->contact_name)
                                · {{ $dispatch->contact_name }}
                            @endif
                            @if($dispatch->recipient)
                                · {{ $dispatch->recipient }}
                            @endif
                        </div>
                        @if($dispatch->reason)
                            <div class="text-xs mt-1" style="color:{{ $color }}">{{ $dispatch->reason }}</div>
                        @endif
                    </div>
                    @if($dispatch->message_body)
                        <x-icon name="chevron-right" size="14" class="flex-shrink-0 transition-transform duration-200"
                             x-bind:style="open ? 'transform:rotate(90deg)' : ''"
                             style="color:var(--muted)" />
                    @endif
                </div>

                @if($dispatch->message_body)
                    <div x-show="open" x-cloak style="padding:0 1rem 1rem">
                        <div class="p-3" style="background:var(--s3)">
                            <p class="text-xs font-semibold mb-1" style="color:var(--muted)">Mensagem</p>
                            <pre class="text-sm whitespace-pre-wrap" style="color:var(--text); font-family:inherit; line-height:1.6">{{ $dispatch->message_body }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-5">{{ $dispatches->links() }}</div>
@endif
