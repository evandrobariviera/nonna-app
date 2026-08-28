<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full gap-2">
            <div class="min-w-0">
                <p class="text-xs font-mono uppercase tracking-widest mb-0.5" style="color:var(--muted)">
                    <a href="{{ route('meetings.index') }}"
                       style="color:var(--muted)"
                       onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">← Agenda</a>
                    / {{ $meeting->typeLabel() }}
                </p>
                <h1 class="text-base sm:text-xl font-black truncate" style="color:var(--text)">{{ $meeting->title }}</h1>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
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

    <div class="flex flex-col lg:flex-row gap-5 lg:items-start">

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

            {{-- Pauta — gerada pelo agente de IA junto com a ATA (structure_ata) ou escrita
                 à mão; renderiza como cards quando reconhece o Markdown (##/###/checklist),
                 senão cai pro texto cru. Checklist marcado = já identificado na ATA,
                 desmarcado = pendência a discutir na Revisão Interna. --}}
            @if($meeting->agenda)
                <div class="card">
                    <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                        <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">Pauta</p>
                        <a href="{{ route('meetings.edit', $meeting) }}#agenda" class="btn btn-ghost btn-xs">Editar →</a>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        @forelse($agendaCards as $card)
                            <div class="p-4" style="background:var(--s3); border:1px solid var(--border2); border-radius:10px">
                                @if($card['title'])
                                    <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--purple)">{{ $card['title'] }}</p>
                                @endif
                                @foreach($card['blocks'] as $block)
                                    @if($block['type'] === 'h3')
                                        <p class="text-sm font-semibold mb-2" style="color:var(--text)">{{ $block['text'] }}</p>
                                    @elseif($block['type'] === 'p')
                                        <p class="text-sm mb-2" style="color:var(--text)">{!! $block['html'] !!}</p>
                                    @elseif($block['type'] === 'ul')
                                        <ul class="list-disc pl-5 text-sm mb-2" style="color:var(--text)">
                                            @foreach($block['items'] as $item)
                                                <li class="mb-1">{!! $item !!}</li>
                                            @endforeach
                                        </ul>
                                    @elseif($block['type'] === 'ol')
                                        <ol class="list-decimal pl-5 text-sm mb-2" style="color:var(--text)">
                                            @foreach($block['items'] as $item)
                                                <li class="mb-1">{!! $item !!}</li>
                                            @endforeach
                                        </ol>
                                    @elseif($block['type'] === 'checklist')
                                        <div class="space-y-1.5">
                                            @foreach($block['items'] as $item)
                                                <div class="flex items-start gap-2.5 px-3 py-2"
                                                     style="border-radius:8px; background:{{ $item['done'] ? 'var(--s2)' : 'rgba(238,121,25,0.06)' }}; border:1px solid {{ $item['done'] ? 'var(--border2)' : 'rgba(238,121,25,0.25)' }}">
                                                    <span class="flex-shrink-0" style="width:14px; height:14px; margin-top:3px; border-radius:4px; border:2px solid {{ $item['done'] ? '#10B981' : 'var(--orange)' }}; background:{{ $item['done'] ? '#10B981' : 'transparent' }}"></span>
                                                    <span class="text-sm flex-1" style="color:var(--text)">
                                                        {!! $item['html'] !!}
                                                        @if($item['resp'])
                                                            <span class="block text-xs font-mono mt-0.5" style="color:{{ $item['done'] ? 'var(--muted)' : 'var(--orange)' }}">{{ $item['resp'] }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @empty
                            <p class="text-sm whitespace-pre-wrap" style="color:var(--text)">{{ $meeting->agenda }}</p>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- ATA --}}
            <div class="card">
                <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">ATA da Reunião</p>
                    @if($meeting->ata_recorded_at)
                        <span class="text-xs font-mono" style="color:var(--muted)">
                            Atualizada em {{ $meeting->ata_recorded_at->format('d/m/Y H:i') }}
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
                        <div class="mt-4 flex gap-3">
                            <a href="{{ route('meetings.edit', $meeting) }}#ata" class="btn btn-ghost btn-xs">
                                Editar ATA →
                            </a>
                            <a href="{{ route('meetings.ata-print', $meeting) }}" target="_blank" class="btn btn-ghost btn-xs">
                                Ver ATA formatada →
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

            <x-notification-status-list :notifications="$meetingNotifications" />

            {{-- Tarefas — nascidas direto da Reunião, antes até de existir Planejamento
                 (ver Task::meeting()); se a Reunião entrar num Macro depois, elas
                 acompanham sozinhas (MeetingObserver). --}}
            <div class="card" x-data="{ showTaskForm: false }">
                <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid var(--border2)">
                    <p class="text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                        Tarefas
                        @if($meeting->tasks->count() > 0)
                            <span class="ml-1 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $meeting->tasks->count() }}</span>
                        @endif
                    </p>
                    @if($meeting->client_id)
                        <button type="button" @click="showTaskForm = !showTaskForm" class="btn btn-ghost btn-xs">+ Nova Tarefa</button>
                    @endif
                </div>
                <div class="px-5 py-4">
                    @if(!$meeting->client_id)
                        <p class="text-sm" style="color:var(--muted)">Vincule um cliente à reunião pra poder criar tarefas a partir dela.</p>
                    @else
                        <div x-show="showTaskForm" x-cloak class="mb-4 pb-4" style="border-bottom:1px solid var(--border2)">
                            <form method="POST" action="{{ route('meetings.tasks.store', $meeting) }}"
                                  enctype="multipart/form-data" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                @csrf
                                <div class="col-span-2 md:col-span-4">
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                                        Título <span style="color:var(--orange)">*</span>
                                    </label>
                                    <input type="text" name="title" required autofocus placeholder="Próximo passo combinado na reunião..."
                                        class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                </div>

                                <div class="col-span-2 md:col-span-4">
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Descrição</label>
                                    <x-rich-editor name="description" min-height="120px" />
                                </div>

                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Tipo</label>
                                    <select name="task_type" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        @foreach(\App\Models\Task::$types as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Destino</label>
                                    <select name="destination" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        <option value="">— sem destino —</option>
                                        @foreach(\App\Models\Task::$destinations as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Prioridade</label>
                                    <select name="priority" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        <option value="normal" selected>Normal</option>
                                        @foreach(\App\Models\Task::$priorities as $key => $p)
                                            @if($key !== 'normal')
                                                <option value="{{ $key }}">{{ $p['label'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Responsável</label>
                                    <select name="responsavel_id" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        <option value="">— nenhum —</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Vencimento</label>
                                    <input type="date" name="due_date" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Data de Aprovação</label>
                                    <input type="date" name="approval_date" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                </div>
                                <div>
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Data de Publicação</label>
                                    <input type="date" name="publish_date" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                </div>

                                {{-- Executores (múltiplos) --}}
                                <div class="col-span-2 md:col-span-4" x-data="executorPicker()">
                                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Executores</label>
                                    <div class="flex flex-wrap gap-2 mb-2" x-show="selected.length > 0">
                                        <template x-for="(item, idx) in selected" :key="item.id">
                                            <div class="flex items-center gap-1 px-2 py-1 text-xs"
                                                 style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                                <span x-text="item.name" style="color:var(--text)"></span>
                                                <select :name="'executor_roles[' + item.id + ']'" @change="setRole(item, $event.target.value, $event.target)"
                                                    class="text-xs focus:outline-none ml-1"
                                                    style="background:var(--s3); color:var(--muted); border:none">
                                                    <option value="executor" :selected="item.role === 'executor'">Executor</option>
                                                    <option value="aprovador" :selected="item.role === 'aprovador'">Aprovador</option>
                                                </select>
                                                <input type="hidden" :name="'executor_ids[]'" :value="item.id">
                                                <button type="button" @click="remove(idx)" class="btn btn-danger btn-xs ml-1">✕</button>
                                            </div>
                                        </template>
                                    </div>
                                    <p x-show="roleError" x-text="roleError" x-cloak class="text-xs mb-1.5" style="color:var(--red)"></p>
                                    <select @change="add($event)" class="w-full px-3 py-2 text-sm focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        <option value="">+ Adicionar executor</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-span-2 md:col-span-4 flex items-center justify-end gap-2 pt-1">
                                    <button type="button" @click="showTaskForm = false" class="btn btn-ghost btn-xs">Cancelar</button>
                                    <button type="submit" class="btn btn-primary btn-xs">Criar Tarefa</button>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if($meeting->tasks->isEmpty())
                        <p class="text-sm" style="color:var(--muted)">Nenhuma tarefa vinculada ainda.</p>
                    @else
                        <ul class="flex flex-col gap-2">
                            @foreach($meeting->tasks as $task)
                                <li>
                                    <a href="{{ route('tasks.show', $task) }}"
                                       class="flex items-center gap-2 text-sm hover:underline" style="color:var(--text)">
                                        <span class="badge badge-{{ $task->statusColor() }} flex-shrink-0">{{ $task->statusLabel() }}</span>
                                        <span class="truncate">{{ $task->title }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>

        {{-- Coluna lateral --}}
        <div class="flex flex-col gap-5 w-full lg:w-[280px] lg:flex-shrink-0">

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
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        @if(str_starts_with($attachment->mime_type ?? '', 'audio/') || str_starts_with($attachment->mime_type ?? '', 'video/'))
                                            <form method="POST" action="{{ route('meeting-attachments.transcribe', [$meeting, $attachment]) }}"
                                                  @submit.prevent="if (await $store.confirmDialog.ask('Transcrever este áudio via IA e substituir a Transcrição atual da reunião?')) $el.submit()">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-xs" title="Transcrever via IA">🎙️ Transcrever</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('meeting-attachments.destroy', [$meeting, $attachment]) }}"
                                              @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) $el.submit()">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs">✕</button>
                                        </form>
                                    </div>
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

    {{-- @push precisa ficar dentro do <x-app-layout> — fora dele, o slot já foi
         renderizado e o @stack('scripts') do layout já rodou, então o script nunca
         aparece na página (Alpine perde a função e quebra o x-data inteiro). --}}
    @push('scripts')
    <script>
    function executorPicker(initial = []) {
        return {
            selected: initial,
            roleError: '',
            // 'executor' e 'responsavel' são papéis capados em 1 por tarefa — 'aprovador'/
            // 'observador' não têm limite aqui.
            countRole(role, excludeId = null) {
                return this.selected.filter(s => s.role === role && s.id != excludeId).length;
            },
            add(event) {
                const id   = event.target.value;
                const name = event.target.selectedOptions[0]?.dataset?.name;
                if (!id || this.selected.find(s => s.id == id)) {
                    event.target.value = '';
                    return;
                }
                const role = this.countRole('executor') > 0 ? 'aprovador' : 'executor';
                this.selected.push({ id, name, role });
                event.target.value = '';
            },
            setRole(item, newRole, selectEl) {
                if ((newRole === 'executor' || newRole === 'responsavel') && this.countRole(newRole, item.id) > 0) {
                    selectEl.value = item.role;
                    this.roleError = newRole === 'executor'
                        ? 'Já existe um Executor nessa tarefa — troque o outro primeiro.'
                        : 'Já existe um Responsável nessa tarefa — troque o outro primeiro.';
                    return;
                }
                item.role = newRole;
                this.roleError = '';
            },
            remove(idx) {
                this.selected.splice(idx, 1);
                this.roleError = '';
            }
        }
    }
    </script>
    @endpush
</x-app-layout>
