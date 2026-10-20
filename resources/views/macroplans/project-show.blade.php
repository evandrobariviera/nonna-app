<x-app-layout>
    <x-slot name="header">Projeto — {{ $project->title }}</x-slot>

    <div x-data="{ editingProject: false }">
    {{-- BREADCRUMB + AÇÕES --}}
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div class="flex items-center gap-2 flex-wrap text-xs font-semibold">
            @if($macroplan)
                <a href="{{ route('macroplans.index') }}" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">Planejamentos</a>
                <span style="color:var(--border2)">/</span>
                <a href="{{ route('macroplans.edit', $macroplan) }}" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    {{ $macroplan->client->displayName() }}
                </a>
                <span style="color:var(--border2)">/</span>
                <a href="{{ route('macroplans.edit', [$macroplan, 'bloco' => 'bloco3']) }}" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    {{ $macroplan->title }}
                </a>
            @else
                <a href="{{ route('projects.dashboard') }}" style="color:var(--muted)"
                   onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">Projetos & Campanhas</a>
            @endif
            <span style="color:var(--border2)">/</span>
            <span style="color:var(--text)">{{ $project->title }}</span>
        </div>
        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('projects.update-macroplan', $project) }}" class="flex items-center gap-2">
                @csrf @method('PATCH')
                <label class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">Planejamento</label>
                <select name="macro_plan_id" onchange="this.form.submit()"
                    class="px-2 py-1.5 text-xs focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    <option value="">— nenhum —</option>
                    @foreach($clientMacroplans as $mp)
                        <option value="{{ $mp->id }}" {{ $project->macro_plan_id === $mp->id ? 'selected' : '' }}>
                            {{ $mp->title }}
                        </option>
                    @endforeach
                </select>
            </form>
            <span class="badge badge-{{ $project->statusColor() }}">{{ $project->statusLabel() }}</span>
            <button type="button" @click="$store.taskAssistant.open = true" class="btn btn-ghost btn-xs flex items-center gap-1.5">
                <x-icon name="sparkles" size="14" style="color:var(--purple)" />
                Assistente de Tarefas
            </button>
            @include('projects._apply-playbook-picker', ['project' => $project, 'playbooks' => $playbooks, 'compact' => true])
            <button type="button" @click="editingProject = !editingProject" class="btn btn-ghost btn-xs">
                <span x-text="editingProject ? 'Fechar edição' : 'Editar Projeto'"></span>
            </button>
            <form method="POST"
                  action="{{ $standalone ? route('projects.destroyDirect', $project) : route('macroplans.projects.destroy', [$macroplan, $project]) }}"
                  @submit.prevent="if (await $store.confirmDialog.ask('Excluir o projeto {{ addslashes($project->title) }} e {{ $totalTasks }} tarefa(s) vinculada(s)? Essa ação não pode ser desfeita.')) $el.submit()">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-xs">Excluir Projeto</button>
            </form>
        </div>
    </div>

    <div x-show="editingProject" x-cloak class="card mb-6">
        @include('macroplans._project-edit-form', [
            'project' => $project,
            'formAction' => $standalone ? route('projects.updateDirect', $project) : route('macroplans.projects.update', [$macroplan, $project]),
            'cancelAction' => 'editingProject = false',
        ])
    </div>
    </div>

    @if(session('success'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green)">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 px-4 py-3 text-sm font-semibold"
             style="background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.25); color:var(--red)">
            {{ session('error') }}
        </div>
    @endif

    {{-- 2 colunas (2026-10): documento à esquerda, detalhes/portal/anexos/campanhas numa
         coluna lateral fixa — o HTML é estreito e sozinho na largura toda sobrava cinza. --}}
    @php $projectClient = $project->client ?? $macroplan?->client; @endphp
    <div class="grid gap-5 mb-6 lg:grid-cols-[minmax(0,1fr)_380px] xl:grid-cols-[minmax(0,1fr)_460px] items-start">
    <div class="min-w-0">

    {{-- DOCUMENTO — o HTML da campanha/projeto (campo próprio document_attachment_id). É onde mora o
         briefing, as peças e a mecânica (2026-10), e o que o Assistente de Tarefas lê. --}}
    @php $mainDocument = $project->mainDocument(); @endphp
    <div class="card overflow-hidden" x-data="{ open: {{ $mainDocument ? 'true' : 'false' }} }">
        <div class="px-5 py-3 flex items-center justify-between gap-3 flex-wrap">
            <button type="button" @click="open = !open" class="flex items-center gap-2 text-left min-w-0">
                <x-icon name="file-text" size="14" style="color:var(--purple)" />
                <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">Documento</span>
                <span class="text-xs truncate" style="color:var(--text)">{{ $mainDocument?->filename ?? 'nenhum HTML anexado' }}</span>
                @if($mainDocument)<span class="text-xs" style="color:var(--muted)" x-text="open ? '▴' : '▾'"></span>@endif
            </button>
            <div class="flex items-center gap-2 flex-wrap">
                @if($mainDocument)
                    <a href="{{ $mainDocument->url() }}" target="_blank" rel="noopener" class="btn btn-ghost btn-xs flex items-center gap-1">
                        <x-icon name="external-link" size="12" /> Abrir em nova aba
                    </a>
                @endif
                {{-- Campo próprio do documento (só HTML) — "Anexos" abaixo nunca troca o que
                     aparece aqui. Versão anterior fica nos anexos. --}}
                <form method="POST" action="{{ route('project-attachments.document', $project) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="btn btn-primary btn-xs cursor-pointer flex items-center gap-1">
                        <x-icon name="upload" size="12" /> {{ $mainDocument ? 'Trocar documento' : 'Enviar documento (HTML)' }}
                        <input type="file" name="document" accept=".html,.htm" class="hidden" @change="$el.closest('form').submit()">
                    </label>
                </form>
            </div>
        </div>
        @error('document')
            <p class="px-5 pb-3 text-xs" style="color:var(--red)">{{ $message }}</p>
        @enderror
        @if($mainDocument)
            <iframe x-show="open" x-cloak src="{{ $mainDocument->url() }}" title="Documento {{ $project->type === 'campanha' ? 'da campanha' : 'do projeto' }}"
                    sandbox="allow-scripts allow-popups" loading="lazy"
                    style="width:100%; height:calc(100vh - 140px); min-height:560px; border:0; border-top:1px solid var(--border2); background:#fff; display:block"></iframe>
        @else
            <p class="px-5 pb-4 text-xs" style="color:var(--muted)">
                Envie o HTML {{ $project->type === 'campanha' ? 'da campanha (skill de campanha)' : 'do projeto (skill de projeto)' }} pelo botão
                "Enviar documento (HTML)" — ele aparece aqui e o Assistente de Tarefas passa a ler. Arquivos em "Anexos" não aparecem aqui.
            </p>
        @endif
    </div>

    </div>{{-- /coluna documento --}}

    <aside class="min-w-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">

    {{-- PROGRESSO + DETALHES — mesmo visual do "Faz parte de" da tarefa (tasks/show): rosca,
         datas e régua do período. Contadores data-stat-count são atualizados ao vivo pelo
         drag do quadro (script no fim do arquivo). --}}
    @php
        $isCampaign = $project->type === 'campanha';
        $projHex    = \App\Models\Task::colorHex($project->typeColor());
        $ringLen    = 2 * M_PI * 30;
        $today      = now()->startOfDay();
        $periodPct  = null;
        $periodTxt  = null;
        if ($project->start_date && $project->end_date && $project->end_date->gte($project->start_date)) {
            $span      = max(1, $project->start_date->diffInDays($project->end_date));
            $periodPct = (int) max(0, min(100, round($project->start_date->diffInDays($today, false) / $span * 100)));
            if ($today->lt($project->start_date)) {
                $n = (int) $today->diffInDays($project->start_date);
                $periodTxt = 'Começa em ' . $n . ' dia' . ($n !== 1 ? 's' : '');
            } elseif ($today->gt($project->end_date)) {
                $periodTxt = 'Período encerrado';
            } else {
                $n = (int) $today->diffInDays($project->end_date);
                $periodTxt = $n === 0 ? 'Termina hoje' : 'Faltam ' . $n . ' dia' . ($n !== 1 ? 's' : '');
            }
        }
    @endphp
    <div class="card mb-5" style="border-top:3px solid {{ $projHex }}; overflow:hidden">
        <div class="card-body flex flex-col gap-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-widest flex items-center gap-2" style="color:{{ $projHex }}; letter-spacing:.1em">
                    <span class="icon-badge" style="color:{{ $projHex }}"><x-icon :name="$project->typeIcon()" size="16" /></span>
                    {{ $project->typeLabel() }}
                </p>
                @if($projectClient)
                    <a href="{{ route('clients.show', $projectClient) }}" class="text-xs font-semibold truncate hover:underline" style="color:var(--muted2)">{{ $projectClient->displayName() }}</a>
                @endif
            </div>

            {{-- Rosca de progresso --}}
            <div class="flex items-center gap-4">
                <svg width="76" height="76" viewBox="0 0 76 76" class="flex-shrink-0" role="img" aria-label="{{ $progress }}% concluído">
                    <circle cx="38" cy="38" r="30" fill="none" stroke="var(--s3)" stroke-width="8" />
                    <circle id="project-progress-ring" cx="38" cy="38" r="30" fill="none" stroke="{{ $projHex }}" stroke-width="8" stroke-linecap="round"
                            stroke-dasharray="{{ $ringLen }}" stroke-dashoffset="{{ $ringLen * (1 - $progress / 100) }}"
                            transform="rotate(-90 38 38)" style="transition:stroke-dashoffset .5s" />
                    <text id="project-progress-pct" x="38" y="43" text-anchor="middle" font-size="16" font-weight="800" fill="var(--text)">{{ $progress }}%</text>
                </svg>
                <div class="min-w-0">
                    <p id="project-progress-text" class="text-sm font-bold" style="color:var(--text)">{{ $doneTasks }} de {{ $totalTasks }} tarefa{{ $totalTasks !== 1 ? 's' : '' }}</p>
                    <p class="text-xs" style="color:var(--muted)">concluídas {{ $isCampaign ? 'na campanha' : 'no projeto' }}</p>
                    <p class="text-xs mt-1 font-semibold" style="color:{{ \App\Models\Task::colorHex($project->statusColor()) }}">{{ $project->statusLabel() }}</p>
                </div>
            </div>

            {{-- Contagem por status (compacta) --}}
            <div class="grid grid-cols-4 gap-x-2 gap-y-3 pt-3" style="border-top:1px solid var(--border2)">
                @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
                    @continue($statusKey === 'cancelado')
                    <div class="text-center min-w-0" title="{{ $meta['label'] }}">
                        <div class="text-base font-black leading-none" data-stat-count="{{ $statusKey }}" style="color:{{ \App\Models\Task::colorHex($meta['color']) }}">{{ $kanban[$statusKey]->count() }}</div>
                        <div class="mt-1 truncate" style="color:var(--muted); font-size:10px">{{ \Illuminate\Support\Str::before($meta['label'], ' /') }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Datas + régua do período (marca onde "hoje" está) --}}
            @if($project->start_date || $project->end_date || ($isCampaign && $project->pieces_due_date))
                <div class="flex flex-col gap-2 pt-3" style="border-top:1px solid var(--border2)">
                    @if($isCampaign && $project->pieces_due_date)
                        <div class="flex items-center justify-between">
                            <span class="text-xs" style="color:var(--muted)">Entrega das peças</span>
                            <span class="text-sm font-semibold" style="color:var(--text)">{{ $project->pieces_due_date->format('d/m/Y') }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">{{ $isCampaign ? 'Vai ao ar' : 'Início' }}</span>
                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $project->start_date?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--muted)">Término</span>
                        <span class="text-sm font-semibold" style="color:var(--text)">{{ $project->end_date?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    @if($periodPct !== null)
                        <div class="mt-1">
                            <div class="relative h-1.5 rounded-full" style="background:var(--s3)">
                                <div class="absolute left-0 top-0 h-1.5 rounded-full" style="width:{{ $periodPct }}%; background:{{ $projHex }}; opacity:.45"></div>
                                <div class="absolute rounded-full" title="Hoje"
                                     style="left:calc({{ $periodPct }}% - 5px); top:-3px; width:10px; height:10px; background:{{ $projHex }}; border:2px solid var(--s1)"></div>
                            </div>
                            <p class="text-xs mt-2" style="color:var(--muted2)">{{ $periodTxt }}</p>
                        </div>
                    @endif
                </div>
            @endif

            @if($project->budget)
                <div class="flex items-center justify-between pt-3" style="border-top:1px solid var(--border2)">
                    <span class="text-xs" style="color:var(--muted)">Verba</span>
                    <span class="text-sm font-semibold" style="color:var(--text)">R$ {{ number_format($project->budget, 2, ',', '.') }}</span>
                </div>
            @endif
            @if($project->disciplines)
                <div class="flex flex-wrap gap-1 pt-3" style="border-top:1px solid var(--border2)">
                    @foreach($project->disciplineLabels() as $d)
                        <span class="badge">{{ $d }}</span>
                    @endforeach
                </div>
            @endif
            @if($project->objective)
                <div class="pt-3" style="border-top:1px solid var(--border2)">
                    <p class="text-xs font-semibold mb-1" style="color:var(--muted)">Objetivo</p>
                    <p class="text-xs leading-relaxed" style="color:var(--muted2)">{{ $project->objective }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- PENDÊNCIAS — o que o projeto/campanha está esperando (insumo do cliente, acesso,
         data...). Ver <x-pendencies-panel>. --}}
    <div class="mb-5">
        <x-pendencies-panel :pendable="$project" alias="project" :users="$users" />
    </div>

    {{-- TAREFAS EM FILA, AGRUPADAS POR STATUS — leitura rápida ao lado do documento.
         Concluídas começam fechadas. O quadro completo (arrastar, editar) fica abaixo. --}}
    <div class="card mb-5 overflow-hidden" x-data>
        <div class="px-5 py-3 flex items-center justify-between gap-2" style="border-bottom:1px solid var(--border2)">
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">
                Tarefas <span style="color:var(--muted2)">{{ $totalTasks }}</span>
            </span>
            <button type="button" @click="$dispatch('open-new-task')" class="btn btn-primary btn-xs">+ Nova</button>
        </div>
        @if($totalTasks === 0)
            <p class="px-5 py-4 text-xs" style="color:var(--muted)">Nenhuma tarefa ainda.</p>
        @endif
        @foreach(\App\Models\Task::$statuses as $statusKey => $meta)
            @continue($statusKey === 'cancelado')
            @php $group = $kanban[$statusKey]; $hex = \App\Models\Task::colorHex($meta['color']); @endphp
            @continue($group->isEmpty())
            <div x-data="{ open: {{ $statusKey === 'concluido' ? 'false' : 'true' }} }" style="border-bottom:1px solid var(--border2)">
                <button type="button" @click="open = !open" class="w-full px-5 py-2 flex items-center justify-between gap-2 text-left" style="background:var(--s2)">
                    <span class="flex items-center gap-2 text-xs font-bold" style="color:{{ $hex }}">
                        <span class="rounded-full" style="width:8px; height:8px; background:{{ $hex }}"></span>
                        {{ $meta['label'] }}
                    </span>
                    <span class="text-xs" style="color:var(--muted)">{{ $group->count() }} <span x-text="open ? '▴' : '▾'"></span></span>
                </button>
                <div x-show="open" x-cloak>
                    @foreach($group as $t)
                        @php $late = $t->due_date && $t->due_date->lt($today) && $statusKey !== 'concluido'; @endphp
                        <a href="{{ route('tasks.show', $t) }}" class="block px-5 py-2 transition-colors" style="border-top:1px solid var(--border2)"
                           onmouseover="this.style.background='var(--s2)'" onmouseout="this.style.background=''">
                            <p class="text-xs font-semibold leading-snug" style="color:var(--text)">{{ $t->title }}</p>
                            <p class="text-xs mt-0.5 flex items-center gap-2" style="color:var(--muted); font-size:11px">
                                <span class="truncate">{{ $t->executors->map(fn ($u) => \Illuminate\Support\Str::before($u->name, ' '))->join(', ') ?: 'Sem executor' }}</span>
                                @if($t->due_date)
                                    <span class="flex-shrink-0 font-mono" style="color:{{ $late ? 'var(--red)' : 'var(--muted)' }}">{{ $t->due_date->format('d/m') }}</span>
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- PÁGINA DO CLIENTE — o que aparece no topo da Página do Projeto no
         Portal (Central de Aprovações). Texto separado do objetivo/briefings,
         que são internos. Destaque é opcional. --}}
    <div class="card px-5 py-4 mb-5" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 text-left">
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted); letter-spacing:.1em">
                Página do cliente (Portal)
            </span>
            <span class="text-xs" style="color:var(--muted2)">
                @if($project->client_description || $project->highlight_task_id)
                    Configurada
                @else
                    Sem descrição
                @endif
                <span x-text="open ? '▴' : '▾'"></span>
            </span>
        </button>
        <form x-show="open" x-cloak method="POST" action="{{ route('projects.client-page.update', $project) }}" class="mt-4 flex flex-col gap-4">
            @csrf @method('PATCH')
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text)">Descrição para o cliente</label>
                <textarea name="client_description" rows="3" maxlength="2000"
                    placeholder="Texto curto explicando a ideia do projeto, na linguagem do cliente."
                    class="w-full px-3 py-2 text-sm focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text); resize:vertical">{{ old('client_description', $project->client_description) }}</textarea>
                <p class="text-xs mt-1" style="color:var(--muted)">O cliente lê isso no topo da página do projeto. Objetivo e briefings continuam só internos.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text)">Tarefa em destaque</label>
                <select name="highlight_task_id" class="w-full px-3 py-2 text-sm focus:outline-none"
                    style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                    <option value="">— nenhuma —</option>
                    @foreach($project->tasks->where('status', '!=', 'cancelado') as $t)
                        <option value="{{ $t->id }}" @selected($project->highlight_task_id === $t->id)>{{ $t->title }}</option>
                    @endforeach
                </select>
                <p class="text-xs mt-1" style="color:var(--muted)">Ex.: o conceito da campanha ou o layout da home. Aparece em destaque no topo, depois que passar pela aprovação.</p>
            </div>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <a href="{{ route('approvals.preview.project', $project) }}" target="_blank" rel="noopener"
                   class="btn btn-ghost btn-xs flex items-center gap-1" title="Abrir a página deste projeto como o cliente vê no Portal (só consulta)">
                    <x-icon name="eye" size="12" /> Ver como o cliente vê
                </a>
                <button type="submit" class="btn btn-primary btn-xs">Salvar</button>
            </div>
        </form>
    </div>

    {{-- ANEXOS --}}
    <div class="card px-5 py-4 mb-5">
        <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">
            Anexos
            @if($project->attachments->count() > 0)
                <span class="ml-1 px-1.5 py-0.5" style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--muted2)">{{ $project->attachments->count() }}</span>
            @endif
        </p>
        <form method="POST" action="{{ route('project-attachments.store', $project) }}"
              enctype="multipart/form-data" class="mb-2">
            @csrf
            <label class="flex items-center justify-center w-full py-3 cursor-pointer text-center transition-colors"
                   style="border:1px dashed var(--border2); color:var(--muted); font-size:11px">
                + Anexar arquivo
                <input type="file" name="file" class="hidden" @change="$el.closest('form').submit()">
            </label>
        </form>
        @foreach($project->attachments as $attachment)
            <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs" style="background:var(--s2)">
                <a href="{{ $attachment->url() }}" target="_blank" class="truncate hover:underline" style="color:var(--text)">
                    {{ $attachment->icon() }} {{ $attachment->filename }}
                </a>
                @if($attachment->id === $project->document_attachment_id)
                    <span class="badge badge-purple flex-shrink-0" style="font-size:10px" title="É o que aparece no card Documento">documento</span>
                @endif
                <form method="POST" action="{{ route('project-attachments.destroy', [$project, $attachment]) }}"
                      @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-xs">✕</button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- CAMPANHAS VINCULADAS — campanhas de anúncio (Meta/Google) correlacionadas
         a este projeto. Vínculo é feito na própria página da campanha
         (Projeto Vinculado → campanhas.update-project). --}}
    @if($project->adCampaigns->isNotEmpty())
        <div class="card px-5 py-4 mb-5">
            <p class="text-xs font-semibold uppercase tracking-widest mb-3" style="color:var(--muted); letter-spacing:.1em">
                Campanhas Vinculadas ({{ $project->adCampaigns->count() }})
            </p>
            <div class="flex flex-col gap-2">
                @foreach($project->adCampaigns as $campaign)
                    <a href="{{ route('campaigns.show', $campaign) }}"
                       class="flex items-center justify-between gap-3 px-3 py-2.5 transition-colors"
                       style="background:var(--s2); border:1px solid var(--border2)"
                       onmouseover="this.style.borderColor='var(--purple)'" onmouseout="this.style.borderColor='var(--border)'">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold truncate" style="color:var(--text)">{{ $campaign->name }}</p>
                            <p class="text-xs mt-0.5" style="color:var(--muted)">{{ $campaign->platform }} · {{ $campaign->adAccount?->account_name ?? $campaign->adAccount?->account_id }}</p>
                        </div>
                        <span class="badge badge-{{ $campaign->managementStatusColor() }} flex-shrink-0">{{ $campaign->managementStatusLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    </aside>
    </div>{{-- /grid documento + lateral --}}

    {{-- TAREFAS: Quadro (Kanban) ou Fila (lista) — recolhido quando há documento (a lateral
         já mostra a fila por status); aberto quando não há, pra página não ficar vazia.
         O "+ Nova" da lateral dispara open-new-task e abre o formulário aqui. --}}
    <div x-data="{ addCol: null, editTaskId: null, showDone: false, view: 'quadro', boardOpen: {{ $mainDocument ? 'false' : 'true' }} }"
         @open-new-task.window="addCol = 'backlog'; $nextTick(() => $refs.newTaskForm.scrollIntoView({ behavior: 'smooth', block: 'start' }))">

        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <button type="button" @click="boardOpen = !boardOpen" class="flex items-center gap-2 text-xs font-mono uppercase tracking-widest" style="color:var(--muted)">
                <span :class="boardOpen ? 'rotate-90' : ''" class="transition-transform">▶</span>
                Quadro completo de tarefas
            </button>
            <div class="flex items-center gap-2" x-show="boardOpen" x-cloak>
                <div class="flex items-center" style="border:1px solid var(--border2)">
                    <button @click="view = 'quadro'" type="button"
                        class="text-xs font-mono px-3 py-1.5 transition-all"
                        :style="view === 'quadro' ? 'background:var(--purple); color:#fff' : 'color:var(--muted)'">
                        Quadro
                    </button>
                    <button @click="view = 'fila'" type="button"
                        class="text-xs font-mono px-3 py-1.5 transition-all"
                        :style="view === 'fila' ? 'background:var(--purple); color:#fff' : 'color:var(--muted)'">
                        Fila
                    </button>
                </div>
                <button @click="showDone = !showDone" x-show="view === 'quadro'"
                    class="flex items-center gap-1.5 text-xs font-mono px-3 py-1.5 transition-all"
                    :style="showDone
                        ? 'border:1px solid var(--purple); color:var(--purple)'
                        : 'border:1px solid var(--border2); color:var(--muted)'">
                    <span x-text="showDone ? '⊙ Ocultar finalizadas' : '○ Mostrar finalizadas'"></span>
                </button>
                <button @click="addCol = addCol === 'backlog' ? null : 'backlog'" class="btn btn-primary btn-sm">
                    + Nova Tarefa
                </button>
            </div>
        </div>

        {{-- ── FORMULÁRIO: NOVA TAREFA ── --}}
        <div x-show="addCol !== null" x-cloak x-ref="newTaskForm" class="card px-5 py-5 mb-4" style="border-left:3px solid var(--purple); scroll-margin-top:16px">
            <p class="text-xs font-mono uppercase tracking-widest mb-4" style="color:var(--muted)">Nova Tarefa</p>
            <form method="POST" action="{{ $standalone ? route('tasks.storeStandalone', $project) : route('tasks.store', [$macroplan, $project]) }}"
                  enctype="multipart/form-data"
                  class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @csrf

                {{-- Título --}}
                <div class="col-span-2 md:col-span-4">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">
                        Título <span style="color:var(--orange)">*</span>
                    </label>
                    <input type="text" name="title" required placeholder="O que precisa ser feito?"
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)"
                        onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'">
                </div>

                {{-- Data de Aprovação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Data de Aprovação</label>
                    <input type="date" name="approval_date" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Data de Publicação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Data de Publicação</label>
                    <input type="date" name="publish_date" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Vencimento --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Vencimento</label>
                    <input type="date" name="due_date" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                {{-- Prioridade --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Prioridade</label>
                    <select name="priority" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="normal" selected>Normal</option>
                        @foreach(\App\Models\Task::$priorities as $key => $p)
                            <option value="{{ $key }}">{{ $p['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Responsável --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Responsável</label>
                    <select name="responsavel_id" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— nenhum —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Executores (múltiplos) --}}
                <div x-data="executorPicker()">
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
                    <select @change="add($event)" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">+ Adicionar executor</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Origem --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Origem</label>
                    <select name="origin" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @foreach(\App\Models\Task::$origins as $key => $label)
                            <option value="{{ $key }}" {{ $key === 'projeto' ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Destino --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Destino</label>
                    <select name="destination" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        <option value="">— sem destino —</option>
                        @foreach(\App\Models\Task::$destinations as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Tipo</label>
                    <select name="task_type" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @foreach(\App\Models\Task::$types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @foreach(\App\Models\Task::$statuses as $key => $s)
                            @if($key !== 'cancelado')
                                <option value="{{ $key }}" {{ $key === 'backlog' ? 'selected' : '' }}>{{ $s['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Situação --}}
                <div>
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Situação</label>
                    <select name="situation" class="w-full px-3 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                        @foreach(\App\Models\Task::$situations as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Descrição --}}
                <div class="col-span-2 md:col-span-4">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Descrição</label>
                    <x-rich-editor name="description" min-height="180px" />
                </div>

                {{-- Anexos --}}
                <div class="col-span-2 md:col-span-4">
                    <label class="block text-xs font-mono uppercase tracking-widest mb-1.5" style="color:var(--muted)">Anexos</label>
                    <input type="file" name="files[]" multiple
                        class="w-full px-4 py-2.5 text-sm focus:outline-none"
                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                </div>

                <div class="col-span-2 md:col-span-4 flex items-center gap-3">
                    <button type="submit"
                        class="px-5 py-2.5 text-xs font-bold font-mono uppercase tracking-widest text-white"
                        style="background:var(--purple)">
                        Criar Tarefa
                    </button>
                    <button type="button" @click="addCol = null"
                        class="text-xs font-mono transition-colors" style="color:var(--muted)">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <div x-show="boardOpen" x-cloak>
        <div x-show="view === 'quadro'">
        {{-- ── COLUNAS KANBAN ── --}}
        <div id="project-board" class="flex gap-4 overflow-x-auto pb-2" style="align-items: start;"
             data-kanban-board data-status-field="status">

            @foreach(\App\Models\Task::$statuses as $colKey => $col)
                @continue($colKey === 'cancelado')
                @php $colTasks = $kanban[$colKey]; @endphp
                <div class="flex flex-col gap-2 flex-shrink-0" style="width:270px"
                     data-kanban-column data-status="{{ $colKey }}"
                     @if($colKey === 'concluido') x-show="showDone" x-cloak @endif>

                    {{-- Header da coluna — sólido estilo Monday --}}
                    <div class="flex items-center justify-between px-3 py-2.5 rounded"
                         style="background:{{ \App\Models\Task::colorHex($col['color']) }}">
                        <span class="text-xs font-bold uppercase tracking-wider" style="color:#fff; letter-spacing:.06em">
                            {{ $col['label'] }}
                        </span>
                        <span class="text-xs font-bold" data-kanban-count style="color:rgba(255,255,255,.75)">
                            {{ $colTasks->count() }}
                        </span>
                    </div>

                    {{-- Cards --}}
                    <div class="flex flex-col gap-2" data-kanban-list>
                    @forelse($colTasks as $task)
                        @php
                            $statusUrl = $standalone ? route('tasks.updateStatusStandalone', [$project, $task]) : route('tasks.update-status', [$macroplan, $project, $task]);
                        @endphp
                        <div class="card px-4 py-3"
                             data-kanban-card data-id="{{ $task->id }}" data-update-url="{{ $statusUrl }}"
                             style="{{ $task->isOverdue() ? 'border-left:3px solid var(--red)' : '' }}">

                            {{-- VIEW do card --}}
                            <div x-show="editTaskId !== '{{ $task->id }}'">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-icon-chip :icon="$task->typeIcon()" :color="$task->statusColor()" size="30" />
                                        <p class="text-xs font-semibold leading-snug min-w-0" style="color:var(--text)">
                                            {{ $task->title }}
                                        </p>
                                    </div>
                                    <button @click="editTaskId = '{{ $task->id }}'" class="btn btn-ghost btn-xs flex-shrink-0">
                                        ✎
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 mb-2">
                                    @if($task->destination)
                                        <span class="text-xs font-mono" style="color:var(--muted2)">{{ $task->destinationLabel() }}</span>
                                    @endif
                                </div>

                                {{-- Executores --}}
                                @if($task->executors->count() > 0)
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        @foreach($task->executors as $exec)
                                            <span class="text-xs px-1.5 py-0.5 font-mono"
                                                  style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--{{ $exec->pivot->role === 'executor' ? 'orange' : ($exec->pivot->role === 'aprovador' ? 'purple' : 'text') }})">
                                                {{ explode(' ', $exec->name)[0] }}
                                                @if($exec->pivot->role !== 'executor')
                                                    <span style="color:var(--muted)"> · {{ \App\Models\TaskExecutor::$roles[$exec->pivot->role] ?? '' }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif($task->executor)
                                    <div class="mb-2">
                                        <span class="text-xs font-mono" style="color:var(--muted)">
                                            {{ explode(' ', $task->executor->name)[0] }}
                                        </span>
                                    </div>
                                @endif

                                {{-- Datas --}}
                                <div class="flex flex-wrap gap-2 mb-2">
                                    @if($task->due_date)
                                        <span class="text-xs font-mono"
                                              style="color:{{ $task->isOverdue() ? 'var(--red)' : 'var(--muted)' }}">
                                            Venc: {{ $task->due_date->format('d/m') }}
                                        </span>
                                    @endif
                                    @if($task->publish_date)
                                        <span class="text-xs font-mono" style="color:var(--muted)">
                                            Pub: {{ $task->publish_date->format('d/m') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Status + badges --}}
                                <div class="flex items-center flex-wrap gap-1">
                                    <span class="badge badge-{{ $task->statusColor() }}" style="font-size:10px">
                                        {{ $task->statusLabel() }}
                                    </span>
                                    @if($task->internal_approval)
                                        <span class="badge" style="font-size:10px; color:var(--purple)">Aprov. Int.</span>
                                    @endif
                                </div>

                                {{-- Mover entre colunas --}}
                                <div class="flex flex-wrap gap-1 mt-2 pt-2" style="border-top:1px solid var(--border2)">
                                    @foreach(\App\Models\Task::$statuses as $targetKey => $targetCol)
                                        @if($targetKey !== $colKey)
                                            <form method="POST" action="{{ $standalone ? route('tasks.updateStatusStandalone', [$project, $task]) : route('tasks.update-status', [$macroplan, $project, $task]) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $targetKey }}">
                                                <button type="submit" class="btn btn-ghost btn-xs">
                                                    → {{ $targetCol['label'] }}
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                    <a href="{{ route('tasks.show', $task) }}" class="btn btn-ghost btn-xs">
                                        Abrir →
                                    </a>
                                    <form method="POST" action="{{ $standalone ? route('tasks.destroyStandalone', [$project, $task]) : route('tasks.destroy', [$macroplan, $project, $task]) }}"
                                          @submit.prevent="if (await $store.confirmDialog.ask('Remover?')) $el.submit()">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-xs">
                                            ✕
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- ── FORMULÁRIO DE EDIÇÃO INLINE ── --}}
                            <template x-if="editTaskId === '{{ $task->id }}'">
                                <form method="POST" action="{{ $standalone ? route('tasks.updateStandalone', [$project, $task]) : route('tasks.update', [$macroplan, $project, $task]) }}"
                                      class="space-y-2"
                                      x-data="executorPicker({{ json_encode($task->executors->map(fn($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role])) }})">
                                    @csrf @method('PATCH')

                                    <input type="text" name="title" value="{{ $task->title }}" required
                                        class="w-full px-3 py-2 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">

                                    <div class="grid grid-cols-2 gap-1.5">
                                        <select name="task_type"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                            @foreach(\App\Models\Task::$types as $key => $label)
                                                <option value="{{ $key }}" {{ $task->task_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <select name="destination"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                            <option value="">— destino —</option>
                                            @foreach(\App\Models\Task::$destinations as $key => $label)
                                                <option value="{{ $key }}" {{ $task->destination === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>

                                        <select name="status"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                            @foreach(\App\Models\Task::$statuses as $key => $s)
                                                <option value="{{ $key }}" {{ $task->status === $key ? 'selected' : '' }}>{{ $s['label'] }}</option>
                                            @endforeach
                                        </select>

                                        <select name="origin"
                                            class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                            @foreach(\App\Models\Task::$origins as $key => $label)
                                                <option value="{{ $key }}" {{ $task->origin === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>


                                        <div class="flex items-center gap-1.5 px-2">
                                            <input type="hidden" name="internal_approval" value="0">
                                            <input type="checkbox" name="internal_approval" value="1"
                                                id="ia_{{ $task->id }}"
                                                {{ $task->internal_approval ? 'checked' : '' }}
                                                class="w-3.5 h-3.5" style="accent-color:var(--purple)">
                                            <label for="ia_{{ $task->id }}" class="text-xs font-mono" style="color:var(--muted)">Aprov. Interna</label>
                                        </div>
                                    </div>

                                    {{-- Datas --}}
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <div>
                                            <p class="text-xs font-mono mb-0.5" style="color:var(--muted)">Vencimento</p>
                                            <input type="date" name="due_date"
                                                value="{{ $task->due_date?->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        </div>
                                        <div>
                                            <p class="text-xs font-mono mb-0.5" style="color:var(--muted)">Dt. Aprovação</p>
                                            <input type="date" name="approval_date"
                                                value="{{ $task->approval_date?->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        </div>
                                        <div>
                                            <p class="text-xs font-mono mb-0.5" style="color:var(--muted)">Dt. Publicação</p>
                                            <input type="date" name="publish_date"
                                                value="{{ $task->publish_date?->format('Y-m-d') }}"
                                                class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                                style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        </div>
                                    </div>

                                    {{-- Executores --}}
                                    <div>
                                        <p class="text-xs font-mono mb-1" style="color:var(--muted)">Executores</p>
                                        <div class="flex flex-wrap gap-1 mb-1.5" x-show="selected.length > 0">
                                            <template x-for="(item, idx) in selected" :key="item.id">
                                                <div class="flex items-center gap-1 px-1.5 py-0.5 text-xs"
                                                     style="background:var(--s3); border:1px solid var(--border); border-radius:8px">
                                                    <span x-text="item.name" style="color:var(--text)"></span>
                                                    <select :name="'executor_roles[' + item.id + ']'" @change="setRole(item, $event.target.value, $event.target)"
                                                        class="text-xs focus:outline-none ml-1"
                                                        style="background:var(--s3); color:var(--muted); border:none">
                                                        <option value="executor" :selected="item.role === 'executor'">Executor</option>
                                                        <option value="responsavel" :selected="item.role === 'responsavel'">Responsável</option>
                                                        <option value="aprovador" :selected="item.role === 'aprovador'">Aprovador</option>
                                                    </select>
                                                    <input type="hidden" :name="'executor_ids[]'" :value="item.id">
                                                    <button type="button" @click="remove(idx)" class="btn btn-danger btn-xs">✕</button>
                                                </div>
                                            </template>
                                        </div>
                                        <p x-show="roleError" x-text="roleError" x-cloak class="text-xs mb-1" style="color:var(--red)"></p>
                                        <select @change="add($event)" class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                            style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                            <option value="">+ Adicionar executor</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}" data-name="{{ $u->name }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Situação --}}
                                    <select name="situation" class="w-full px-2 py-1.5 text-xs focus:outline-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">
                                        @foreach(\App\Models\Task::$situations as $key => $label)
                                            <option value="{{ $key }}" {{ ($task->situation ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>

                                    {{-- Descrição --}}
                                    <textarea name="description" rows="2"
                                        placeholder="Descrição (opcional)"
                                        class="w-full px-2 py-2 text-xs focus:outline-none resize-none"
                                        style="background:var(--s3); border:1px solid var(--border); border-radius:8px; color:var(--text)">{{ $task->description }}</textarea>

                                    <div class="flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-xs">
                                            Salvar
                                        </button>
                                        <button type="button" @click="editTaskId = null" class="btn btn-ghost btn-xs">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </template>

                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs rounded"
                             style="border:1px dashed var(--border2); color:var(--muted)">
                            Sem tarefas
                        </div>
                    @endforelse
                    </div>

                </div>
            @endforeach

        </div>

        {{-- Tarefas canceladas --}}
        @if($cancelled->count() > 0)
            <div x-show="showDone" x-cloak class="mt-5">
            <div x-data="{ open: false }">
                <button @click="open = !open"
                    class="text-xs font-mono transition-colors flex items-center gap-2" style="color:var(--muted)"
                    onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                    <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
                    {{ $cancelled->count() }} tarefa{{ $cancelled->count() !== 1 ? 's' : '' }} cancelada{{ $cancelled->count() !== 1 ? 's' : '' }}
                </button>
                <div x-show="open" x-cloak class="mt-2 flex flex-col gap-1">
                    @foreach($cancelled as $task)
                        <div class="px-4 py-2 text-xs" style="background:var(--s2); border:1px solid var(--border2); color:var(--muted); text-decoration:line-through">
                            {{ $task->title }}
                        </div>
                    @endforeach
                </div>
            </div>
            </div>{{-- /x-show showDone --}}
        @endif
        </div>{{-- /x-show view === 'quadro' --}}

        {{-- ── FILA (lista) ── --}}
        <div x-show="view === 'fila'" x-cloak x-data="taskBulk()">
            @php
                $filaTasks = $project->tasks->where('status', '!=', 'cancelado')->values();
                $filaProjects = collect([$project->loadMissing('client')]);
            @endphp
            @include('partials._task-bulk-bar', ['users' => $users, 'projects' => $filaProjects])
            @if($filaTasks->isEmpty())
                <div class="px-4 py-6 text-center text-xs rounded"
                     style="border:1px dashed var(--border2); color:var(--muted)">
                    Sem tarefas
                </div>
            @else
                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="nonna-table">
                            @include('partials._task-thead')
                            <tbody x-data="{ groupOpen: true }">
                                @foreach($filaTasks as $task)
                                    @include('partials._fila-task-tr', ['task' => $task, 'activeSprint' => $activeSprint, 'sprints' => $sprints])
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        </div>{{-- /x-show boardOpen --}}
    </div>{{-- /x-data kanban --}}
    <x-badge-fill-menu />
    <x-person-fill-menu :users="$users" />

    {{-- BRIEFINGS --}}
    @if($project->disciplines)
        <div x-data="{ open: false }" class="mt-6">
            <button @click="open = !open"
                class="text-xs font-mono uppercase tracking-widest transition-colors flex items-center gap-2"
                style="color:var(--muted)"
                onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
                Briefings por Head <span class="normal-case tracking-normal" style="opacity:.7">· conteúdo anterior (o atual fica no Documento)</span>
            </button>
            <div x-show="open" x-cloak class="mt-3 grid gap-3"
                 style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))">
                @foreach($project->disciplines as $disc)
                    @if($project->hasBriefingFor($disc))
                        <div class="card px-4 py-4">
                            <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">
                                {{ \App\Models\Project::$disciplines[$disc] ?? $disc }}
                            </p>
                            <p class="text-xs whitespace-pre-wrap leading-relaxed" style="color:var(--text)">{{ $project->{'briefing_' . $disc} }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- BRIEF CRIATIVO (campanhas com brief detalhado) --}}
    @if($project->isCampaignDetailed())
        <div x-data="{ open: true }" class="mt-6">
            <button @click="open = !open"
                class="text-xs font-mono uppercase tracking-widest transition-colors flex items-center gap-2"
                style="color:var(--muted)"
                onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--muted)'">
                <span :class="open ? 'rotate-90' : ''" class="transition-transform">▶</span>
                Brief Criativo <span class="normal-case tracking-normal" style="opacity:.7">· conteúdo anterior (o atual fica no Documento)</span>
            </button>
            <div x-show="open" x-cloak class="mt-3 space-y-4">
                @if($project->big_idea_titulo || $project->big_idea_manifesto)
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--purple)">Big Idea</p>
                        @if($project->big_idea_titulo)
                            <p class="text-base font-bold mb-2" style="color:var(--text)">{{ $project->big_idea_titulo }}</p>
                        @endif
                        @if($project->big_idea_manifesto)
                            <p class="text-sm whitespace-pre-wrap leading-relaxed" style="color:var(--muted2)">{{ $project->big_idea_manifesto }}</p>
                        @endif
                    </div>
                @endif

                @if($project->territorio_alternativo)
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Território Alternativo (rota B)</p>
                        <p class="text-sm whitespace-pre-wrap leading-relaxed" style="color:var(--muted2)">{{ $project->territorio_alternativo }}</p>
                    </div>
                @endif

                @if($project->racional_estrategico)
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Racional Estratégico</p>
                        <p class="text-sm whitespace-pre-wrap leading-relaxed" style="color:var(--text)">{{ $project->racional_estrategico }}</p>
                    </div>
                @endif

                @if(!empty($project->tom_comunicacao) || $project->frase_voz)
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">Linha de Comunicação</p>
                        @if(!empty($project->tom_comunicacao))
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                @foreach($project->tom_comunicacao as $tom)
                                    <span class="px-2.5 py-1 text-xs font-mono rounded-full" style="background:rgba(100, 59, 142,.12); color:var(--purple)">{{ $tom }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if($project->frase_voz)
                            <p class="text-sm italic" style="color:var(--muted2)">"{{ $project->frase_voz }}"</p>
                        @endif
                    </div>
                @endif

                @if(!empty($project->angulos))
                    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))">
                        @foreach($project->angulos as $ang)
                            <div class="card px-4 py-4">
                                <p class="text-xs font-bold mb-1" style="color:var(--purple)">{{ $ang['titulo'] ?? '' }}</p>
                                <p class="text-xs leading-relaxed" style="color:var(--muted2)">{{ $ang['texto'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($project->assinatura)
                    <div class="card px-5 py-4 text-center">
                        <p class="text-base font-bold" style="color:var(--text)">{{ $project->assinatura }}</p>
                    </div>
                @endif

                @if(!empty($project->mecanica))
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Mecânica da Campanha</p>
                        <div class="space-y-3">
                            @foreach($project->mecanica as $i => $passo)
                                <div class="flex gap-3">
                                    <span class="text-xs font-mono flex-shrink-0" style="color:var(--purple)">{{ $i + 1 }}.</span>
                                    <div>
                                        <p class="text-sm font-bold" style="color:var(--text)">{{ $passo['titulo'] ?? '' }}</p>
                                        <p class="text-xs leading-relaxed" style="color:var(--muted2)">{{ $passo['texto'] ?? '' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($project->ponto_atencao)
                    <div class="px-4 py-3 text-xs rounded" style="background:rgba(238, 121, 25,.06); border:1px solid rgba(238, 121, 25,.2); color:var(--orange)">
                        {{ $project->ponto_atencao }}
                    </div>
                @endif

                @if(!empty($project->referencias_visuais))
                    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr))">
                        @foreach($project->referencias_visuais as $ref)
                            <div class="card px-4 py-4">
                                <p class="text-xs font-mono uppercase tracking-widest mb-2" style="color:var(--muted)">{{ $ref['label'] ?? '' }}</p>
                                <p class="text-xs leading-relaxed" style="color:var(--muted2)">{{ $ref['texto'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($project->pecas))
                    <div class="card px-5 py-4">
                        <p class="text-xs font-mono uppercase tracking-widest mb-3" style="color:var(--muted)">Desdobramento em Peças</p>
                        <ul class="space-y-2">
                            @foreach($project->pecas as $peca)
                                <li style="border-bottom:1px solid var(--border2)" class="pb-2">
                                    <p class="text-sm font-bold" style="color:var(--text)">{{ $peca['nome'] ?? '' }}</p>
                                    <p class="text-xs leading-relaxed" style="color:var(--muted2)">{{ $peca['direcionamento'] ?? '' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- HISTÓRICO — só ações (status, datas, planejamento, "briefing editado"),
         nunca o conteúdo (ver ProjectObserver). --}}
    <x-activity-history :activities="$project->activities" class="card-body mt-6" />

    {{-- @push precisa ficar dentro do <x-app-layout> — fora dele, o slot já foi
         renderizado e o @stack('scripts') do layout já rodou, então o script nunca
         aparece na página (Alpine perde a função, e o kanban-dnd do board também). --}}
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
            // Se já tem executor, não assume esse papel por padrão — força a pessoa a
            // escolher outro papel em vez de trocar o executor sem querer.
            const role = this.countRole('executor') > 0 ? 'aprovador' : 'executor';
            this.selected.push({ id, name, role });
            event.target.value = '';
        },
        setRole(item, newRole, selectEl) {
            if ((newRole === 'executor' || newRole === 'responsavel') && this.countRole(newRole, item.id) > 0) {
                selectEl.value = item.role; // reverte a seleção visual
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

document.addEventListener('DOMContentLoaded', function () {
    var board = document.getElementById('project-board');
    if (!board) return;

    initKanbanDnd('#project-board');

    board.addEventListener('kanban:moved', function (evt) {
        var fromStatus = evt.detail.fromColumn.dataset.status;
        var toStatus = evt.detail.toColumn.dataset.status;
        var fromStat = document.querySelector('[data-stat-count="' + fromStatus + '"]');
        var toStat = document.querySelector('[data-stat-count="' + toStatus + '"]');
        if (fromStat) fromStat.textContent = String(parseInt(fromStat.textContent, 10) - 1);
        if (toStat) toStat.textContent = String(parseInt(toStat.textContent, 10) + 1);

        var total = 0, done = 0;
        document.querySelectorAll('[data-stat-count]').forEach(function (el) {
            var status = el.dataset.statCount;
            var n = parseInt(el.textContent, 10) || 0;
            total += n;
            if (status === 'concluido') done = n;
        });
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;

        document.getElementById('project-progress-pct').textContent = pct + '%';
        document.getElementById('project-progress-text').textContent =
            done + ' de ' + total + (total !== 1 ? ' tarefas' : ' tarefa');
        var ring = document.getElementById('project-progress-ring');
        var len = parseFloat(ring.getAttribute('stroke-dasharray'));
        ring.setAttribute('stroke-dashoffset', String(len * (1 - pct / 100)));
    });
});
</script>
    @endpush

    @include('projects._task-assistant-drawer', [
        'project'         => $project,
        'assistantAgent'  => $assistantAgent,
        'playbooks'       => $playbooks,
        'chatMessages'    => $chatMessages,
        'team'            => $team,
    ])
</x-app-layout>
