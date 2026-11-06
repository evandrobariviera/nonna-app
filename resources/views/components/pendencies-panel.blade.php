{{-- Bloco "Pendências" de uma Tarefa/Ticket, Projeto ou Planejamento. Abertas primeiro
     (prazo mais próximo no topo); resolvidas/canceladas recolhidas no fim.
     Pendência ≠ Trava: aqui é "esta origem ESPERA algo" — ver App\Models\Pendency. --}}
@props(['pendable', 'alias', 'users', 'returnTo' => null])

@php
    $panel = app(\App\Services\PendencyService::class)->panelData($pendable);
    // Depois de resolver/cobrar, volta pro bloco (não pro topo da página).
    $returnTo = $returnTo ?? url()->current() . '#pendencias';
    // Sugestões do "Atualizar com reunião" (só campanha/projeto/planejamento): o que a reunião
    // deixou para alguém fazer, ainda não registrado nem dispensado.
    $suggested = in_array($alias, ['project', 'macro_plan'], true)
        ? app(\App\Services\DocumentUpdate\DocumentUpdateService::class)->suggestedPendencies($pendable)
        : collect();
@endphp

<div class="card card-body-lg" id="pendencias"
     x-data="{ adding: {{ $errors->pendency->any() ? 'true' : 'false' }}, showClosed: false }"
     @pendency-form-close="adding = false">
    <div class="flex items-center justify-between mb-4">
        <p class="card-title flex items-center gap-2">
            <span class="icon-badge">
                <x-icon name="hourglass" size="16" />
            </span>
            Pendências
            @if($panel['open']->isNotEmpty())
                <span class="ml-1.5 px-1.5 py-0.5 text-xs" style="background:rgba(238,121,25,.12); border:1px solid rgba(238,121,25,.3); border-radius:8px; color:var(--orange)">
                    {{ $panel['open']->count() }}
                </span>
            @endif
        </p>
        <button type="button" class="text-xs font-semibold" style="color:var(--purple)" x-show="!adding" @click="adding = true">+ Nova</button>
    </div>

    @if($errors->pendency->any())
        <div class="mb-3 px-3 py-2 text-xs" style="background:rgba(220,38,38,.06); border:1px solid rgba(220,38,38,.25); color:var(--red); border-radius:6px">
            {{ $errors->pendency->first() }}
        </div>
    @endif

    <div x-show="adding" x-cloak class="mb-4 p-3" style="background:var(--s2); border:1px solid var(--border2); border-radius:8px">
        @include('pendencies._form', [
            'users'         => $users,
            'contacts'      => $panel['contacts'],
            'pendableAlias' => $alias,
            'pendableId'    => $pendable->getKey(),
            'returnTo'      => $returnTo,
        ])
    </div>

    @foreach($suggested as $group)
        {{-- Recolhido por padrão: só o contador chama atenção; abre quem for registrar. --}}
        <details class="mb-4 p-3" style="background:rgba(238,121,25,.05); border:1px dashed rgba(238,121,25,.4); border-radius:8px"
                 @if(session('pendency_suggestions_open') === $group['run']->id) open @endif>
            <summary class="text-xs font-semibold cursor-pointer flex items-center gap-1" style="color:var(--orange)">
                <x-icon name="sparkles" size="12" /> {{ count($group['items']) }} pendência(s) sugerida(s) pela reunião
                <span class="font-normal" style="color:var(--muted)">· {{ $group['run']->meeting?->title ?? 'Reunião' }}{{ $group['run']->meeting?->scheduled_at ? ' (' . $group['run']->meeting->scheduled_at->format('d/m') . ')' : '' }}</span>
            </summary>
            <div class="flex items-center justify-between gap-2 flex-wrap mt-2 mb-1">
                <p class="text-[11px]" style="color:var(--muted)">
                    O que ficou para alguém fazer. <b>Tarefa</b> = a gente produz algo (peça, texto, proposta, ajuste). <b>Pendência</b> = esperar ou cobrar algo de alguém (enviar, confirmar, liberar, decidir). O que não pede ação, dispense.
                    <a href="{{ route('document-updates.show', $group['run']) }}" class="underline">ver análise</a>
                </p>
                <form method="POST" action="{{ route('document-updates.pendency-dismiss', $group['run']) }}">
                    @csrf
                    <input type="hidden" name="key" value="all">
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <button type="submit" class="text-[11px] underline" style="color:var(--muted2)">Dispensar todas</button>
                </form>
            </div>
            <ul class="flex flex-col">
                @foreach($group['items'] as $item)
                    @php
                        $isTask  = $item['natureza'] === 'tarefa';
                        $canTask = $alias === 'project'; // tarefa mora em campanha/projeto
                        $taskDetail = \App\Services\DocumentUpdate\DocumentUpdateService::taskDetail($item, $group['run']);
                        // No planejamento: escolhe a campanha/projeto e abre o Nova Tarefa de lá já preenchido.
                        $planTargets = $alias === 'macro_plan'
                            ? $pendable->projects->map(fn ($p) => ['title' => $p->title, 'url' => route('macroplans.projects.show', [$pendable, $p]) . '?' . http_build_query(['reuniao_run' => $group['run']->id, 'reuniao_item' => $item['key']])])->values()
                            : collect();
                    @endphp
                    <li class="py-2 {{ $loop->last ? '' : 'border-b' }}" style="border-color:var(--border)" x-data="{ open: false, pick: false, target: '' }" @pendency-form-close.stop="open = false">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm" style="color:var(--text)">
                                    <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 mr-1 align-middle"
                                          style="border-radius:5px; {{ $isTask ? 'background:rgba(100,59,142,.12); color:var(--purple)' : 'background:rgba(238,121,25,.12); color:var(--orange)' }}">{{ $isTask ? 'Tarefa' : 'Pendência' }}</span>
                                    {{ $item['titulo'] }}
                                </p>
                                <p class="text-[11px] mt-0.5" style="color:var(--muted)">
                                    @unless($isTask)
                                        {{ \App\Models\Pendency::$kinds[$item['tipo']]['label'] ?? 'Outro' }}
                                        · deve: {{ \App\Models\Pendency::$owedBy[$item['quem_deve']]['label'] ?? '—' }}{{ $item['quem'] ? ' (' . $item['quem'] . ')' : '' }}
                                        @if($item['contexto']) · @endif
                                    @endunless
                                    {{ $item['contexto'] }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0 flex-wrap justify-end" x-show="!open">
                                @if($canTask)
                                    <button type="button" class="btn btn-sm {{ $isTask ? 'btn-primary' : 'btn-ghost' }}"
                                            @click="$dispatch('task-from-meeting', @js($taskDetail))">{{ $isTask ? 'Criar tarefa' : 'Tarefa' }}</button>
                                @elseif($planTargets->isNotEmpty())
                                    <button type="button" class="btn btn-sm {{ $isTask ? 'btn-primary' : 'btn-ghost' }}" @click="pick = !pick">{{ $isTask ? 'Criar tarefa' : 'Tarefa' }}</button>
                                @endif
                                <button type="button" class="btn btn-sm {{ $isTask && ($canTask || $planTargets->isNotEmpty()) ? 'btn-ghost' : 'btn-primary' }}" @click="open = true">{{ $isTask && ($canTask || $planTargets->isNotEmpty()) ? 'Pendência' : 'Registrar pendência' }}</button>
                                <form method="POST" action="{{ route('document-updates.pendency-dismiss', $group['run']) }}">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $item['key'] }}">
                                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                    <button type="submit" class="text-xs px-2" style="color:var(--muted2)" title="Não pede ação de ninguém — some da lista">Dispensar</button>
                                </form>
                            </div>
                        </div>
                        @if($planTargets->isNotEmpty())
                            <div x-show="pick" x-cloak class="mt-2 p-3 flex items-center gap-2 flex-wrap" style="background:var(--s2); border:1px solid var(--border2); border-radius:8px">
                                <span class="text-xs" style="color:var(--muted)">Em qual campanha ou projeto?</span>
                                <select x-model="target" class="field text-xs px-2 py-1.5 flex-1 min-w-0">
                                    <option value="">Escolha…</option>
                                    @foreach($planTargets as $t)
                                        <option value="{{ $t['url'] }}">{{ $t['title'] }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-primary btn-sm" :disabled="!target" @click="window.location = target">Abrir o formulário</button>
                                <button type="button" class="btn btn-ghost btn-sm" @click="pick = false">Cancelar</button>
                            </div>
                        @endif
                        <div x-show="open" x-cloak class="mt-2 p-3" style="background:var(--s2); border:1px solid var(--border2); border-radius:8px">
                            @include('pendencies._form', [
                                'users'         => $users,
                                'contacts'      => $panel['contacts'],
                                'pendableAlias' => $alias,
                                'pendableId'    => $pendable->getKey(),
                                'returnTo'      => $returnTo,
                                'prefill'       => \App\Services\DocumentUpdate\DocumentUpdateService::pendencyPrefill($item, $group['run'], $users, $panel['contacts']),
                                'extraHidden'   => ['document_update_run' => $group['run']->id, 'document_update_item' => $item['key']],
                            ])
                        </div>
                    </li>
                @endforeach
            </ul>
        </details>
    @endforeach

    @if($panel['open']->isEmpty() && !$errors->pendency->any() && $suggested->isEmpty())
        <p class="text-xs" style="color:var(--muted)" x-show="!adding">
            Nada pendente. Use <strong>+ Nova</strong> quando o trabalho depender de algo do cliente, de um terceiro ou de alguém do time.
        </p>
    @endif

    <div class="flex flex-col">
        @foreach($panel['open'] as $p)
            @include('pendencies._item', ['p' => $p, 'contacts' => $panel['contacts'], 'users' => $users, 'showOrigin' => false, 'last' => $loop->last, 'returnTo' => $returnTo])
        @endforeach
    </div>

    @if($panel['closed']->isNotEmpty())
        <button type="button" class="text-xs mt-3" style="color:var(--muted2)" @click="showClosed = !showClosed"
                x-text="showClosed ? 'Ocultar resolvidas' : 'Ver {{ $panel['closed']->count() }} resolvida(s)'"></button>
        <div x-show="showClosed" x-cloak class="flex flex-col mt-2">
            @foreach($panel['closed'] as $p)
                @include('pendencies._item', ['p' => $p, 'contacts' => $panel['contacts'], 'users' => $users, 'showOrigin' => false, 'last' => $loop->last, 'returnTo' => $returnTo])
            @endforeach
        </div>
    @endif
</div>
