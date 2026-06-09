<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">
                    <a href="{{ route('meetings.index') }}"
                       style="color:var(--muted)"
                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Agenda</a>
                    / {{ $meeting->typeLabel() }}
                </p>
                <h1 class="text-xl font-black" style="color:var(--text)">{{ $meeting->title }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-{{ $meeting->statusColor() }}">{{ $meeting->statusLabel() }}</span>
                <a href="{{ route('meetings.edit', $meeting) }}"
                   class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                   style="border:1px solid var(--border2); color:var(--muted2)"
                   onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                   onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                    Editar
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    {{-- Mudar status rápido --}}
    <div class="flex flex-wrap gap-2 mb-5">
        <span class="text-xs font-mono self-center" style="color:var(--muted)">Mover para:</span>
        @foreach(\App\Models\Meeting::$statuses as $key => $s)
            @if($key !== $meeting->status)
                <form method="POST" action="{{ route('meetings.update-status', $meeting) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $key }}">
                    <button type="submit"
                        class="px-3 py-1.5 text-xs font-mono transition-colors"
                        style="border:1px solid var(--border2); color:var(--muted2)"
                        onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                        onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                        {{ $s['label'] }}
                    </button>
                </form>
            @endif
        @endforeach
    </div>

    <div class="flex gap-5 items-start">

        {{-- Coluna principal --}}
        <div class="flex-1 min-w-0 flex flex-col gap-5">

            {{-- Informações gerais --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Informações</p>
                </div>
                <div class="px-5 py-4">
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Tipo</dt>
                            <dd style="color:var(--text)">{{ $meeting->typeLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Modalidade</dt>
                            <dd style="color:var(--text)">{{ $meeting->modalityLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Data / Hora</dt>
                            <dd style="color:var(--text)">
                                {{ $meeting->scheduled_at->format('d/m/Y') }} às {{ $meeting->scheduled_at->format('H:i') }}
                                @if($meeting->duration_minutes)
                                    <span class="ml-1" style="color:var(--muted)">· {{ $meeting->duration_minutes }} min</span>
                                @endif
                            </dd>
                        </div>
                        @if($meeting->online_link)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Link</dt>
                                <dd>
                                    <a href="{{ $meeting->online_link }}" target="_blank"
                                       class="text-xs font-mono break-all hover:underline" style="color:var(--purple)">
                                        {{ $meeting->online_link }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if($meeting->location)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Local</dt>
                                <dd style="color:var(--text)">{{ $meeting->location }}</dd>
                            </div>
                        @endif
                        @if($meeting->client)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Cliente</dt>
                                <dd>
                                    <a href="{{ route('clients.show', $meeting->client) }}"
                                       class="font-semibold hover:underline" style="color:var(--purple)">
                                        {{ $meeting->client->company_name }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        @if($meeting->opportunity)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Oportunidade</dt>
                                <dd>
                                    <a href="{{ route('opportunities.show', $meeting->opportunity) }}"
                                       class="hover:underline" style="color:var(--purple)">
                                        {{ $meeting->opportunity->title }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Organizador</dt>
                            <dd style="color:var(--text)">{{ $meeting->organizer->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Criado por</dt>
                            <dd style="color:var(--text)">{{ $meeting->createdBy->name ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Pauta --}}
            @if($meeting->agenda)
                <div class="card">
                    <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Pauta</p>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm whitespace-pre-wrap" style="color:var(--text)">{{ $meeting->agenda }}</p>
                    </div>
                </div>
            @endif

            {{-- ATA --}}
            <div class="card">
                <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">ATA da Reunião</p>
                    @if($meeting->ata_recorded_at)
                        <span class="text-xs font-mono" style="color:var(--muted)">
                            Registrada em {{ $meeting->ata_recorded_at->format('d/m/Y H:i') }}
                        </span>
                    @endif
                </div>
                <div class="px-5 py-4">
                    @if($meeting->hasAta())
                        <p class="text-sm whitespace-pre-wrap mb-4" style="color:var(--text)">{{ $meeting->ata }}</p>
                        @if($meeting->next_steps)
                            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Próximos Passos</p>
                            <p class="text-sm whitespace-pre-wrap" style="color:var(--text)">{{ $meeting->next_steps }}</p>
                        @endif
                        <div class="mt-4">
                            <a href="{{ route('meetings.edit', $meeting) }}#ata"
                               class="text-xs font-mono transition-colors" style="color:var(--muted)"
                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                Editar ATA →
                            </a>
                        </div>
                    @else
                        <p class="text-sm mb-4" style="color:var(--muted)">Nenhuma ATA registrada ainda.</p>
                        <a href="{{ route('meetings.edit', $meeting) }}#ata"
                           class="px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                           style="border:1px solid var(--border2); color:var(--muted2)"
                           onmouseover="this.style.borderColor='var(--purple)'; this.style.color='var(--purple)'"
                           onmouseout="this.style.borderColor='var(--border2)'; this.style.color='var(--muted2)'">
                            + Registrar ATA
                        </a>
                    @endif
                </div>
            </div>

        </div>

        {{-- Coluna lateral --}}
        <div class="flex flex-col gap-5" style="width:280px; flex-shrink:0">

            {{-- Participantes --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Participantes</p>
                </div>
                <div class="px-5 py-4">
                    @if($meeting->participants->isEmpty())
                        <p class="text-sm" style="color:var(--muted)">Nenhum participante.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach($meeting->participants as $user)
                                <li class="flex items-center gap-3">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-black text-white flex-shrink-0"
                                         style="background:var(--grad)">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <span class="text-sm" style="color:var(--text)">{{ $user->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Ações --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Ações</p>
                </div>
                <div class="px-5 py-4">
                    <form method="POST" action="{{ route('meetings.destroy', $meeting) }}"
                          onsubmit="return confirm('Remover esta reunião permanentemente?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                            class="w-full px-4 py-2 text-xs font-bold font-mono uppercase tracking-widest transition-colors"
                            style="border:1px solid var(--red); color:var(--red)"
                            onmouseover="this.style.background='rgba(239,68,68,.08)'"
                            onmouseout="this.style.background='transparent'">
                            Remover Reunião
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
