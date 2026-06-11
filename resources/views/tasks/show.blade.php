<x-app-layout>
    <x-slot name="header">Tarefa</x-slot>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    <div x-data="{ editing: false }" class="flex gap-5" style="align-items: start;">

        {{-- ══════════════════════════════════════════════════════════
             COLUNA PRINCIPAL
        ══════════════════════════════════════════════════════════ --}}
        <div class="flex-1 min-w-0 flex flex-col gap-4">

            {{-- CABEÇALHO --}}
            <div class="card card-body-lg">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                            <span class="badge badge-{{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                            @if($task->is_ticket)
                                <span class="badge badge-orange">Ticket</span>
                            @endif
                            @if($task->isOverdue())
                                <span class="badge" style="background:rgba(239,68,68,.08); color:var(--red); border-color:rgba(239,68,68,.25)">Atrasada</span>
                            @endif
                        </div>
                        <h1 class="text-2xl leading-tight" style="color:var(--text); font-weight:700; letter-spacing:-0.02em">{{ $task->title }}</h1>
                    </div>
                    <button @click="editing = !editing"
                        :style="editing ? 'background:var(--purple); color:#fff; border-color:var(--purple)' : ''"
                        class="flex-shrink-0 px-4 py-2 text-xs font-semibold transition-colors"
                        style="border:1px solid var(--border2); color:var(--muted2)">
                        <span x-text="editing ? '✕  Cancelar' : '✎  Editar'"></span>
                    </button>
                </div>

                {{-- Breadcrumb --}}
                @if($task->project)
                    <div class="flex items-center gap-2 text-xs flex-wrap mt-3 pt-3" style="border-top:1px solid var(--border2); color:var(--muted)">
                        <a href="{{ route('clients.show', $task->client) }}"
                           style="color:var(--purple); font-weight:500"
                           onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                            {{ $task->client?->company_name }}
                        </a>
                        <span style="color:var(--border2)">›</span>
                        <a href="{{ route('macroplans.edit', $task->project->macro_plan_id) }}"
                           style="color:var(--muted2)"
                           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted2)'">
                            {{ $task->project->macroPlan?->title }}
                        </a>
                        <span style="color:var(--border2)">›</span>
                        <a href="{{ route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) }}"
                           style="color:var(--muted2)"
                           onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted2)'">
                            {{ $task->project->title }}
                        </a>
                    </div>
                @endif
            </div>

            {{-- FORMULÁRIO DE EDIÇÃO --}}
            <form method="POST" action="{{ route('tasks.update-inline', $task) }}" x-show="editing" x-cloak>
                @csrf @method('PATCH')
                <div class="card card-body-lg flex flex-col gap-5">
                    <p class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Editando tarefa</p>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Título</label>
                        <input type="text" name="title" value="{{ $task->title }}" required
                            class="w-full px-4 py-3 text-sm font-semibold focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)"
                            onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
                    </div>

                    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                        @php
                            $editFields = [
                                ['name'=>'status',          'label'=>'Status',              'opts'=>\App\Models\Task::$statuses,        'val'=>$task->status,          'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v['label']]],
                                ['name'=>'task_type',       'label'=>'Tipo',                'opts'=>\App\Models\Task::$types,           'val'=>$task->task_type,       'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v]],
                                ['name'=>'destination',     'label'=>'Destino',             'opts'=>\App\Models\Task::$destinations,    'val'=>$task->destination,     'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v], 'blank'=>true],
                                ['name'=>'origin',          'label'=>'Origem',              'opts'=>\App\Models\Task::$origins,         'val'=>$task->origin,          'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v]],
                                ['name'=>'approval_method', 'label'=>'Método de Aprovação', 'opts'=>\App\Models\Task::$approvalMethods, 'val'=>$task->approval_method, 'map'=>fn($k,$v)=>['v'=>$k,'l'=>$v], 'blank'=>true],
                            ];
                        @endphp
                        @foreach($editFields as $f)
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">{{ $f['label'] }}</label>
                                <select name="{{ $f['name'] }}" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                                    @if($f['blank'] ?? false)
                                        <option value="">— nenhum —</option>
                                    @endif
                                    @foreach($f['opts'] as $k => $v)
                                        @php $item = ($f['map'])($k,$v); @endphp
                                        <option value="{{ $item['v'] }}" {{ $f['val'] === $k ? 'selected' : '' }}>{{ $item['l'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Situação</label>
                            <input type="text" name="situation" value="{{ $task->situation }}" placeholder="Sub-status..."
                                class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        @foreach([['due_date','Vencimento'],['approval_date','Dt. Aprovação'],['publish_date','Dt. Publicação']] as [$fname,$flabel])
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">{{ $flabel }}</label>
                                <input type="date" name="{{ $fname }}" value="{{ $task->$fname?->format('Y-m-d') }}"
                                    class="w-full px-3 py-2.5 text-sm focus:outline-none"
                                    style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            </div>
                        @endforeach
                    </div>

                    {{-- Executores --}}
                    <div x-data="executorPicker({{ json_encode($task->executors->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role])) }})">
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Executores</label>
                        <div class="flex flex-wrap gap-2 mb-2" x-show="selected.length > 0">
                            <template x-for="(item, idx) in selected" :key="item.id">
                                <div class="flex items-center gap-2 px-3 py-1.5 text-sm"
                                     style="background:var(--s3); border:1px solid var(--border2)">
                                    <span x-text="item.name" style="color:var(--text); font-weight:500"></span>
                                    <select :name="'executor_roles[' + item.id + ']'"
                                        class="text-xs focus:outline-none"
                                        style="background:transparent; color:var(--muted); border:none">
                                        <option value="executor"    :selected="item.role==='executor'">Executor</option>
                                        <option value="responsavel" :selected="item.role==='responsavel'">Responsável</option>
                                        <option value="aprovador"   :selected="item.role==='aprovador'">Aprovador</option>
                                    </select>
                                    <input type="hidden" :name="'executor_ids[]'" :value="item.id">
                                    <button type="button" @click="remove(idx)"
                                        class="text-xs transition-colors" style="color:var(--muted)"
                                        onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
                                </div>
                            </template>
                        </div>
                        <select @change="add($event)" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                            <option value="">+ Adicionar executor</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Descrição / Briefing</label>
                        <textarea name="description" rows="5"
                            placeholder="Briefing, detalhes, links de referência..."
                            class="w-full px-4 py-3 text-sm focus:outline-none resize-none leading-relaxed"
                            style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">{{ $task->description }}</textarea>
                    </div>

                    <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border2)">
                        <button type="submit"
                            class="px-5 py-2.5 text-sm font-semibold text-white"
                            style="background:var(--purple)">
                            Salvar Alterações
                        </button>
                        <button type="button" @click="editing = false"
                            class="text-sm" style="color:var(--muted)">Cancelar</button>
                    </div>
                </div>
            </form>

            {{-- CAMPOS (visualização) --}}
            <div x-show="!editing" class="card card-body-lg">
                <p class="text-xs font-semibold uppercase tracking-widest mb-5" style="color:var(--muted); letter-spacing:.1em">Campos</p>
                <div class="grid grid-cols-2 gap-x-10 gap-y-5 md:grid-cols-3">
                    @php
                        $campos = [
                            ['Tipo',              $task->typeLabel()],
                            ['Destino',           $task->destinationLabel() ?: '—'],
                            ['Origem',            $task->originLabel()],
                            ['Método de Aprov.',  $task->approvalMethodLabel() ?: '—'],
                            ['Aprovação Interna', $task->internal_approval ? 'Sim' : 'Não'],
                            ['Situação',          $task->situation ?: '—'],
                        ];
                    @endphp
                    @foreach($campos as [$label, $value])
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">{{ $label }}</p>
                            <p class="text-sm" style="color:var(--text); font-weight:500; line-height:1.4">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- DATAS (visualização) --}}
            <div x-show="!editing" class="card card-body-lg">
                <p class="text-xs font-semibold uppercase tracking-widest mb-5" style="color:var(--muted); letter-spacing:.1em">Datas</p>
                <div class="grid grid-cols-3 gap-8">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Vencimento</p>
                        <p class="text-base" style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--text)' }}; font-weight:600">
                            {{ $task->due_date?->format('d/m/Y') ?? '—' }}
                        </p>
                        @if($task->due_date)
                            <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $task->due_date->diffForHumans() }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Dt. Aprovação</p>
                        <p class="text-base" style="color:var(--text); font-weight:600">
                            {{ $task->approval_date?->format('d/m/Y') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1.5" style="color:var(--muted); letter-spacing:.08em">Dt. Publicação</p>
                        <p class="text-base" style="color:var(--text); font-weight:600">
                            {{ $task->publish_date?->format('d/m/Y') ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- DESCRIÇÃO --}}
            @if($task->description)
                <div x-show="!editing" class="card card-body-lg">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Briefing / Descrição</p>
                    <div class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.75">{{ $task->description }}</div>
                </div>
            @endif

            {{-- EXECUTORES --}}
            <div x-show="!editing" class="card card-body-lg">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Executores</p>
                @if($task->executors->count() > 0)
                    <div class="flex flex-col gap-3">
                        @foreach($task->executors as $exec)
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0"
                                     style="background:var(--grad)">
                                    {{ strtoupper(substr($exec->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm" style="color:var(--text); font-weight:600">{{ $exec->name }}</p>
                                    <p class="text-xs" style="color:var(--muted)">
                                        {{ \App\Models\TaskExecutor::$roles[$exec->pivot->role] ?? $exec->pivot->role }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm" style="color:var(--muted)">Nenhum executor atribuído.</p>
                @endif
            </div>

            {{-- ANEXOS --}}
            <div class="card card-body-lg">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">
                    Anexos
                    @if($task->attachments->count() > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border2); color:var(--muted2)">{{ $task->attachments->count() }}</span>
                    @endif
                </p>

                <form method="POST" action="{{ route('task-attachments.store', $task) }}"
                      enctype="multipart/form-data"
                      x-data="{ dragging: false }"
                      @dragover.prevent="dragging = true"
                      @dragleave.prevent="dragging = false"
                      @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $el.submit()">
                    @csrf
                    <label
                        :style="dragging ? 'border-color:var(--purple); background:rgba(106,90,205,.04)' : ''"
                        class="flex flex-col items-center justify-center w-full py-7 cursor-pointer transition-colors"
                        style="border:2px dashed var(--border2); color:var(--muted)">
                        <svg class="h-6 w-6 mb-2.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <span class="text-sm font-medium">Clique para anexar ou arraste arquivos</span>
                        <span class="text-xs mt-1" style="color:var(--muted2)">Máx. 50 MB por arquivo</span>
                        <input type="file" name="file" x-ref="fileInput" class="hidden"
                               @change="$el.closest('form').submit()">
                    </label>
                </form>

                @if($task->attachments->count() > 0)
                    <div class="mt-3 flex flex-col gap-1.5">
                        @foreach($task->attachments as $attachment)
                            <div class="flex items-center justify-between gap-3 px-4 py-3"
                                 style="background:var(--s2); border:1px solid var(--border2)">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="text-xl flex-shrink-0">{{ $attachment->icon() }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ $attachment->url() }}" target="_blank"
                                           class="text-sm font-medium truncate block hover:underline"
                                           style="color:var(--text)">
                                            {{ $attachment->filename }}
                                        </a>
                                        <p class="text-xs mt-0.5" style="color:var(--muted)">
                                            {{ $attachment->sizeForHumans() }}
                                            · {{ $attachment->uploadedBy?->name }}
                                            · {{ $attachment->created_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <a href="{{ $attachment->url() }}" target="_blank"
                                       class="text-xs transition-colors" style="color:var(--muted)"
                                       onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--muted)'">
                                        ↓ Baixar
                                    </a>
                                    <form method="POST" action="{{ route('task-attachments.destroy', [$task, $attachment]) }}"
                                          onsubmit="return confirm('Remover anexo?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs transition-colors" style="color:var(--muted)"
                                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ══ COMENTÁRIOS ══ --}}
            <div class="card card-body-lg" id="comentarios">
                <p class="text-xs font-semibold uppercase tracking-widest mb-5" style="color:var(--muted); letter-spacing:.1em">
                    Comentários
                    @if($task->comments->count() > 0)
                        <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:var(--s3); border:1px solid var(--border2); color:var(--muted2)">{{ $task->comments->count() }}</span>
                    @endif
                </p>

                {{-- Lista --}}
                @if($task->comments->count() > 0)
                    <div class="flex flex-col mb-6">
                        @foreach($task->comments as $comment)
                            <div class="flex gap-4 py-4" style="{{ !$loop->last ? 'border-bottom:1px solid var(--border2)' : '' }}">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0 mt-0.5"
                                     style="background:var(--grad)">
                                    {{ strtoupper(substr($comment->user->name, 0, 2)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline gap-3 mb-1.5 flex-wrap">
                                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $comment->user->name }}</span>
                                        <span class="text-xs" style="color:var(--muted)">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                                        <span class="text-xs" style="color:var(--muted2)">{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm whitespace-pre-wrap" style="color:var(--text); line-height:1.65">{{ $comment->body }}</p>
                                </div>
                                @if($comment->user_id === auth()->id())
                                    <form method="POST"
                                          action="{{ route('task-comments.destroy', [$task, $comment]) }}"
                                          onsubmit="return confirm('Remover comentário?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs mt-1 flex-shrink-0 transition-colors" style="color:var(--muted)"
                                            onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">✕</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-5 py-7 text-center" style="border:1px dashed var(--border2)">
                        <p class="text-sm" style="color:var(--muted)">Nenhum comentário ainda.</p>
                    </div>
                @endif

                {{-- Novo comentário --}}
                <form method="POST" action="{{ route('task-comments.store', $task) }}"
                      x-data="{ body: '', rows: 2 }"
                      @submit="if (!body.trim()) { $event.preventDefault(); }">
                    @csrf
                    <div class="flex gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white flex-shrink-0 mt-0.5"
                             style="background:var(--grad)">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div class="flex-1">
                            <textarea
                                name="body"
                                x-model="body"
                                :rows="rows"
                                @focus="rows = 4"
                                placeholder="Adicionar um comentário..."
                                class="w-full px-4 py-3 text-sm focus:outline-none resize-none transition-all"
                                style="background:var(--s3); border:1px solid var(--border2); color:var(--text); line-height:1.65"
                                onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'"
                            ></textarea>
                            <div class="flex justify-end mt-2" x-show="body.trim().length > 0" x-cloak>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-semibold text-white"
                                    style="background:var(--purple)"
                                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                                    Comentar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>{{-- /coluna principal --}}

        {{-- ══════════════════════════════════════════════════════════
             SIDEBAR DIREITA
        ══════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col gap-4" style="width:270px; flex-shrink:0">

            {{-- STATUS --}}
            <div class="card card-body" x-data="{ open: false }">
                <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">Status</p>
                <div class="relative">
                    <button @click="open = !open"
                        class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-semibold"
                        style="background:var(--s3); border:1px solid var(--border2); color:var(--text)">
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full flex-shrink-0"
                                  style="background:var(--{{ $task->statusColor() }})"></span>
                            {{ $task->statusLabel() }}
                        </span>
                        <span style="color:var(--muted); font-size:10px">▾</span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute left-0 right-0 mt-1 z-20 py-1"
                         style="background:var(--s1); border:1px solid var(--border2); box-shadow:0 4px 16px rgba(0,0,0,.1)">
                        @foreach(\App\Models\Task::$statuses as $key => $s)
                            <form method="POST" action="{{ route('tasks.update-inline', $task) }}">
                                @csrf @method('PATCH')
                                @foreach(['title','task_type','origin'] as $f)
                                    <input type="hidden" name="{{ $f }}" value="{{ $task->$f }}">
                                @endforeach
                                <input type="hidden" name="status" value="{{ $key }}">
                                <button type="submit" @click="open = false"
                                    class="w-full flex items-center gap-2.5 px-4 py-2 text-sm text-left transition-colors"
                                    style="color:{{ $task->status === $key ? 'var(--purple)' : 'var(--muted2)' }}; font-weight:{{ $task->status === $key ? '600' : '400' }}"
                                    onmouseover="this.style.background='var(--s3)'" onmouseout="this.style.background='transparent'">
                                    <span class="h-1.5 w-1.5 rounded-full flex-shrink-0"
                                          style="background:var(--{{ $s['color'] }})"></span>
                                    {{ $s['label'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- CONTEXTO --}}
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Contexto</p>
                <div class="flex flex-col gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Cliente</p>
                        @if($task->client)
                            <a href="{{ route('clients.show', $task->client) }}"
                               class="text-sm font-semibold transition-colors" style="color:var(--purple)"
                               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                                {{ $task->client->company_name }}
                            </a>
                        @else
                            <p class="text-sm" style="color:var(--muted)">—</p>
                        @endif
                    </div>

                    @if($task->project?->macroPlan)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Planejamento</p>
                            <a href="{{ route('macroplans.edit', $task->project->macro_plan_id) }}"
                               class="text-sm font-medium leading-snug block transition-colors" style="color:var(--text)"
                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--text)'">
                                {{ $task->project->macroPlan->title }}
                            </a>
                        </div>
                    @endif

                    @if($task->project)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Projeto</p>
                            <a href="{{ route('macroplans.projects.show', [$task->project->macro_plan_id, $task->project]) }}"
                               class="text-sm font-medium leading-snug block transition-colors" style="color:var(--text)"
                               onmouseover="this.style.color='var(--purple)'" onmouseout="this.style.color='var(--text)'">
                                {{ $task->project->title }}
                            </a>
                        </div>
                    @endif

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Sprint</p>
                        @if($task->sprint)
                            <a href="{{ route('sprints.show', $task->sprint) }}"
                               class="text-sm font-semibold transition-colors" style="color:var(--orange)"
                               onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                                {{ $task->sprint->title }}
                            </a>
                        @else
                            <p class="text-sm" style="color:var(--muted)">Backlog</p>
                        @endif
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Origem</p>
                        <span class="badge mt-0.5">{{ $task->originLabel() }}</span>
                    </div>

                    @if($task->destination)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:var(--muted); letter-spacing:.08em">Destino</p>
                            <p class="text-sm font-medium" style="color:var(--text)">{{ $task->destinationLabel() }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- APROVAÇÃO --}}
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Aprovação</p>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">Método</span>
                        <span class="text-sm font-medium" style="color:var(--text)">{{ $task->approvalMethodLabel() ?: '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">Aprov. Interna</span>
                        <span class="text-sm font-semibold"
                              style="color:{{ $task->internal_approval ? 'var(--purple)' : 'var(--muted2)' }}">
                            {{ $task->internal_approval ? 'Sim' : 'Não' }}
                        </span>
                    </div>
                    @if($task->approval_date)
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Data</span>
                            <span class="text-sm font-medium" style="color:var(--text)">{{ $task->approval_date->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- SOLICITANTE --}}
            @if($task->is_ticket && $task->requester_name)
                <div class="card card-body">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Solicitante</p>
                    <div class="flex flex-col gap-3">
                        <div>
                            <p class="text-xs" style="color:var(--muted)">Nome</p>
                            <p class="text-sm font-semibold mt-0.5" style="color:var(--text)">{{ $task->requester_name }}</p>
                        </div>
                        @if($task->requester_whatsapp)
                            <div>
                                <p class="text-xs" style="color:var(--muted)">WhatsApp</p>
                                <a href="https://wa.me/55{{ preg_replace('/\D/', '', $task->requester_whatsapp) }}"
                                   target="_blank"
                                   class="text-sm font-semibold mt-0.5 block transition-colors" style="color:var(--green)"
                                   onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                                    {{ $task->requester_whatsapp }}
                                </a>
                            </div>
                        @endif
                        @if($task->requester_channel)
                            <div>
                                <p class="text-xs" style="color:var(--muted)">Canal</p>
                                <p class="text-sm font-medium mt-0.5" style="color:var(--text)">
                                    {{ \App\Models\Task::$requesterChannels[$task->requester_channel] ?? '' }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- META --}}
            <div class="card card-body">
                <p class="text-xs font-semibold uppercase tracking-widest mb-4" style="color:var(--muted); letter-spacing:.1em">Informações</p>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">Criada por</span>
                        <span class="text-sm font-medium" style="color:var(--text)">
                            {{ explode(' ', $task->createdBy?->name ?? '—')[0] }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">Criada em</span>
                        <span class="text-sm font-medium" style="color:var(--text)">{{ $task->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">Há</span>
                        <span class="text-sm" style="color:var(--muted2)">{{ $task->created_at->diffForHumans() }}</span>
                    </div>
                    @if($task->launched_at)
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Lançada</span>
                            <span class="text-sm font-semibold" style="color:var(--green)">{{ $task->launched_at->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>
            </div>

        </div>{{-- /sidebar --}}
    </div>

</x-app-layout>

@push('scripts')
<script>
function executorPicker(initial = []) {
    return {
        selected: initial,
        add(event) {
            const id   = event.target.value;
            const name = event.target.selectedOptions[0]?.dataset?.name;
            if (!id || this.selected.find(s => s.id == id)) { event.target.value = ''; return; }
            this.selected.push({ id, name, role: 'executor' });
            event.target.value = '';
        },
        remove(idx) { this.selected.splice(idx, 1); }
    }
}
</script>
@endpush
