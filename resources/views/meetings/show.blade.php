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
                <a href="{{ route('meetings.edit', $meeting) }}" class="btn btn-ghost btn-sm">
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

    @if(session('warning'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(238, 121, 25,.08); border:1px solid rgba(238, 121, 25,.25); color:var(--orange)">
            ⚠ {{ session('warning') }}
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
                        onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--muted2)'">
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
                                        {{ $meeting->client->displayName() }}
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
                        @if($meeting->macroPlan)
                            <div>
                                <dt class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">Planejamento</dt>
                                <dd class="flex items-center gap-2">
                                    <a href="{{ route('macroplans.edit', $meeting->macroPlan) }}"
                                       class="font-semibold hover:underline" style="color:var(--purple)">
                                        {{ $meeting->macroPlan->title }}
                                    </a>
                                    <span class="badge badge-{{ $meeting->macroPlan->statusColor() }}">{{ $meeting->macroPlan->statusLabel() }}</span>
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
                            <a href="{{ route('meetings.edit', $meeting) }}#ata" class="btn btn-ghost btn-xs">
                                Editar ATA →
                            </a>
                        </div>
                    @else
                        <p class="text-sm mb-4" style="color:var(--muted)">Nenhuma ATA registrada ainda.</p>
                        <a href="{{ route('meetings.edit', $meeting) }}#ata" class="btn btn-ghost btn-sm">
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
                                    <x-user-avatar :user="$user" size="7" />
                                    <span class="text-sm" style="color:var(--text)">{{ $user->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Contatos --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Contatos</p>
                </div>
                <div class="px-5 py-4">
                    @if($meeting->contacts->isEmpty())
                        <p class="text-sm" style="color:var(--muted)">Nenhum contato vinculado.</p>
                    @else
                        <ul class="space-y-2">
                            @foreach($meeting->contacts as $contact)
                                <li>
                                    <p class="text-sm font-semibold" style="color:var(--text)">{{ $contact->name }}</p>
                                    @if($contact->company_name)
                                        <p class="text-xs" style="color:var(--muted)">{{ $contact->company_name }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Anexos --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                        Anexos
                        @if($meeting->attachments->count() > 0)
                            <span class="ml-1 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $meeting->attachments->count() }}</span>
                        @endif
                    </p>
                </div>
                <div class="px-5 py-4">
                    <form method="POST" action="{{ route('meeting-attachments.store', $meeting) }}"
                          enctype="multipart/form-data"
                          x-data="{ dragging: false }"
                          @dragover.prevent="dragging = true"
                          @dragleave.prevent="dragging = false"
                          @drop.prevent="dragging = false; $refs.meetingFileInput.files = $event.dataTransfer.files; $el.submit()">
                        @csrf
                        <label
                            :style="dragging ? 'border-color:var(--purple); background:rgba(100, 59, 142,.04)' : ''"
                            class="flex flex-col items-center justify-center w-full py-5 cursor-pointer transition-colors"
                            style="border:2px dashed var(--border2); color:var(--muted)">
                            <span class="text-xs font-medium">Clique para anexar ou arraste</span>
                            <span class="text-xs mt-1" style="color:var(--muted2)">Máx. 100 MB por arquivo</span>
                            <input type="file" name="files[]" multiple x-ref="meetingFileInput" class="hidden"
                                   @change="$el.closest('form').submit()">
                        </label>
                    </form>

                    @if($meeting->attachments->isNotEmpty())
                        <div class="mt-3 flex flex-col gap-1.5">
                            @foreach($meeting->attachments as $attachment)
                                <div class="flex items-center justify-between gap-2 px-3 py-2"
                                     style="background:var(--s2); border:1px solid var(--border2)">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-base flex-shrink-0">{{ $attachment->icon() }}</span>
                                        <a href="{{ $attachment->url() }}" target="_blank"
                                           class="text-xs font-medium truncate hover:underline"
                                           style="color:var(--text)">
                                            {{ $attachment->filename }}
                                        </a>
                                    </div>
                                    <form method="POST" action="{{ route('meeting-attachments.destroy', [$meeting, $attachment]) }}"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-xs flex-shrink-0">✕</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Ações --}}
            <div class="card">
                <div class="px-5 py-4" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Ações</p>
                </div>
                <div class="px-5 py-4">
                    @if($meeting->contacts->isNotEmpty() || $meeting->participants->isNotEmpty())
                        <form method="POST" action="{{ route('meetings.notify', $meeting) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm w-full flex items-center justify-center gap-1.5">
                                <x-icon name="megaphone" size="14" />
                                Notificar Participantes
                            </button>
                        </form>
                    @else
                        <p class="text-xs mb-2" style="color:var(--muted)">Vincule contatos ou participantes internos para poder notificar.</p>
                    @endif
                    <form method="POST" action="{{ route('meetings.destroy', $meeting) }}"
                          @submit.prevent="if (await $store.confirmDialog.ask('Remover esta reunião permanentemente?')) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm w-full">
                            Remover Reunião
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

</x-app-layout>
