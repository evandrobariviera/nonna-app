{{-- Configurações → Pontos de Sprint (App\Services\Tasks\SprintPoints / SprintPointsSettingsController).
     Ponto padrão por tipo + catálogo de formatos por tipo (com palavras-chave que detectam o
     formato pelo título — o PRIMEIRO da lista que bater vence, por isso dá pra reordenar) +
     botão de recalcular as tarefas existentes (ajustes manuais são preservados). --}}
@php
    $typePoints = \App\Models\TaskTypePoint::pluck('points', 'task_type');
    $scoreStatus = \App\Models\TaskTypePoint::pluck('score_status', 'task_type');
    $optimizationPoints = \App\Services\Tasks\SprintPoints::optimizationPoints(app('currentOrganization'));
    // Status que podem ser gatilho de ponto (Backlog/Produção não: ainda não entregou nada).
    $scoreOptions = collect(\App\Models\Task::$statuses)->only(['revisao_interna', 'aprovacao', 'despacho_agendamento', 'concluido']);
    $formatsByType = \App\Models\TaskFormat::orderBy('position')->get()->groupBy('task_type');
    $inputStyle = 'background:var(--s2); border:1px solid var(--border2); color:var(--text)';
@endphp

<div class="flex flex-col gap-5">
    {{-- Guia de leitura pro time — linguagem do dia a dia, com exemplo real. O "1 ponto ≈ 15 min"
         da análise de 07/10 serviu só de estimativa pra calibrar os pesos; o usuário pediu pra NÃO
         apresentar isso como regra aqui. --}}
    <div class="card card-body">
        <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
            <div class="max-w-3xl">
                <p class="text-sm font-bold" style="color:var(--text)">Como funcionam os pontos</p>
                <p class="text-xs mt-1" style="color:var(--muted2); line-height:1.7">
                    O ponto mostra o <strong>tamanho</strong> de cada entrega. Contar só a quantidade de tarefas era injusto:
                    um post rápido e um vídeo inteiro valiam a mesma coisa. Com pontos, quem fez o vídeo recebe pelo esforço
                    que ele realmente deu. É como um placar de jogo: cada jogada vale de acordo com a dificuldade.
                </p>
            </div>
            <form method="POST" action="{{ route('settings.points.recalculate') }}"
                  @submit.prevent="if (await $store.confirmDialog.ask('Recalcular os pontos de todas as tarefas com o catálogo atual? Ajustes feitos à mão são mantidos.')) $el.submit()">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1.5">
                    <x-icon name="refresh-cw" size="14" /> Recalcular tarefas
                </button>
            </form>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div class="px-4 py-3" style="background:var(--s2); border-radius:10px">
                <p class="text-xs font-bold mb-1.5" style="color:var(--purple)">1. Quanto vale cada tarefa</p>
                <p class="text-xs" style="color:var(--muted2); line-height:1.7">
                    O sistema decide sozinho, nesta ordem:<br>
                    <strong>a)</strong> alguém ajustou o ponto à mão na tarefa? Vale o ajuste.<br>
                    <strong>b)</strong> a tarefa tem um <strong>formato</strong> escolhido (Post, Reels, Landing...)? Vale o ponto do formato.<br>
                    <strong>c)</strong> não tem? O sistema lê o <strong>título</strong> e procura as palavras-chave dos formatos, de cima pra baixo.
                    "Post Reels Dia das Crianças" vira <em>Reels</em>, porque Reels está acima de Post na lista.<br>
                    <strong>d)</strong> nada bateu? Vale o ponto padrão do tipo (quadro abaixo).
                </p>
            </div>

            <div class="px-4 py-3" style="background:var(--s2); border-radius:10px">
                <p class="text-xs font-bold mb-1.5" style="color:var(--purple)">2. Quando o ponto entra no placar</p>
                <p class="text-xs" style="color:var(--muted2); line-height:1.7">
                    • A tarefa precisa ter passado por <strong>Em Produção</strong>. Puxar a tarefa pra produção é dizer
                    "estou fazendo isso agora". Tarefa que pula direto pra Concluído <strong>não pontua</strong>.<br>
                    • O ponto entra quando a tarefa chega no status do campo <strong>"Pontua em"</strong> do tipo dela (ou passa dele).
                    Exemplo: Criação pontua em Revisão Interna. O designer entrega o carrossel pra revisão e já ganha o ponto, sem esperar o cliente aprovar.<br>
                    • Conta <strong>uma vez só</strong>. Se a tarefa voltar pra Ajuste, o ponto continua. Reentregar não pontua de novo.<br>
                    • O ponto vai pro <strong>Executor</strong> da tarefa.
                </p>
            </div>

            <div class="px-4 py-3" style="background:var(--s2); border-radius:10px">
                <p class="text-xs font-bold mb-1.5" style="color:var(--purple)">3. Otimização de campanha</p>
                <p class="text-xs" style="color:var(--muted2); line-height:1.7">
                    A rotina de tráfego quase nunca vira tarefa. Por isso cada <strong>"Marcar otimização feita"</strong> na página
                    da campanha pontua sozinho pra quem marcou, com o valor definido no quadro abaixo. Dez otimizações no dia
                    viram dez pontos no placar.
                </p>
            </div>

            <div class="px-4 py-3" style="background:var(--s2); border-radius:10px">
                <p class="text-xs font-bold mb-1.5" style="color:var(--purple)">4. De onde vieram esses valores</p>
                <p class="text-xs" style="color:var(--muted2); line-height:1.7">
                    Medimos quanto tempo as tarefas concluídas desde julho ficaram em Em Produção, separadas por formato,
                    e comparamos uns com os outros. Por exemplo: um carrossel levou em média 4 vezes mais tempo que um post,
                    então vale 4 vezes mais. Os pontos mostram o <strong>peso de um formato em relação aos outros</strong>,
                    não um relógio. Landing page e site são ponto de partida, porque o tempo de web é difícil de medir. Ajustem quando sentirem que algo está fora.
                </p>
            </div>
        </div>
    </div>

    {{-- Ponto padrão por tipo --}}
    <form method="POST" action="{{ route('settings.points.types') }}" class="card card-body">
        @csrf @method('PUT')
        <p class="text-sm font-bold mb-1" style="color:var(--text)">Por tipo: ponto padrão e quando pontua</p>
        <p class="text-xs mb-3" style="color:var(--muted2)">O número é o ponto padrão (vale quando nenhum formato do tipo bate com a tarefa). "Pontua em" é o momento em que a tarefa entra no placar. Mudou o número? Use "Recalcular tarefas". O "Pontua em" vale na hora.</p>
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(230px, 1fr))">
            @foreach(\App\Models\Task::$types as $type => $label)
                <div class="flex flex-col gap-2 px-3 py-2" style="background:var(--s2)">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold" style="color:var(--text)">{{ $label }}</span>
                        <input type="number" name="points[{{ $type }}]" min="0" max="500" value="{{ $typePoints[$type] ?? '' }}"
                               class="w-16 px-2 py-1 text-sm text-right" style="{{ $inputStyle }}" title="Ponto padrão">
                    </div>
                    <label class="flex items-center justify-between gap-2">
                        <span class="text-xs" style="color:var(--muted)">Pontua em</span>
                        <select name="score_status[{{ $type }}]" class="px-2 py-1 text-xs" style="{{ $inputStyle }}">
                            @php $current = $scoreStatus[$type] ?? (\App\Services\Tasks\SprintPoints::DEFAULT_SCORE_STATUS[$type] ?? 'concluido'); @endphp
                            @foreach($scoreOptions as $key => $s)
                                <option value="{{ $key }}" @selected($current === $key)>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-3 mt-4 px-3 py-2" style="background:var(--s2)">
            <div>
                <p class="text-xs font-semibold" style="color:var(--text)">Otimização de campanha</p>
                <p class="text-xs" style="color:var(--muted)">Pontos pra quem clica em "Marcar otimização feita" na campanha (cada clique = uma otimização).</p>
            </div>
            <input type="number" name="optimization_points" min="0" max="100" value="{{ $optimizationPoints }}"
                   class="w-16 px-2 py-1 text-sm text-right" style="{{ $inputStyle }}">
        </div>
        <div class="flex justify-end mt-3">
            <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
        </div>
    </form>

    {{-- Formatos por tipo --}}
    @foreach(\App\Models\Task::$types as $type => $label)
        @php $formats = $formatsByType->get($type, collect()); @endphp
        <div class="card card-body" x-data="{ adding: false }">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-bold" style="color:var(--text)">
                    {{ $label }}
                    <span class="text-xs font-mono font-normal" style="color:var(--muted)">· {{ $formats->count() }} formato(s) · padrão {{ $typePoints[$type] ?? 1 }} pt(s)</span>
                </p>
                <button type="button" class="btn btn-ghost btn-xs" @click="adding = !adding">+ Formato</button>
            </div>

            @if($formats->isNotEmpty())
                <div class="flex flex-col gap-1.5">
                    @foreach($formats as $f)
                        <form method="POST" action="{{ route('settings.points.formats.update', $f) }}"
                              class="grid gap-2 items-center grid-cols-1 md:grid-cols-[60px_1fr_70px_2fr_auto] px-2 py-1.5" style="background:var(--s2)">
                            @csrf @method('PATCH')
                            <input type="hidden" name="task_type" value="{{ $type }}">
                            <div class="flex items-center gap-0.5">
                                <button type="submit" form="move-up-{{ $f->id }}" class="btn btn-ghost btn-xs" title="Subir" @disabled($loop->first)>↑</button>
                                <button type="submit" form="move-down-{{ $f->id }}" class="btn btn-ghost btn-xs" title="Descer" @disabled($loop->last)>↓</button>
                            </div>
                            <input name="name" value="{{ $f->name }}" required class="px-2 py-1 text-sm" style="{{ $inputStyle }}">
                            <input type="number" name="points" min="0" max="500" value="{{ $f->points }}" required class="px-2 py-1 text-sm text-right" style="{{ $inputStyle }}" title="Pontos">
                            <input name="keywords" value="{{ $f->keywords }}" placeholder="palavras-chave, separadas por vírgula" class="px-2 py-1 text-xs font-mono" style="{{ $inputStyle }}">
                            <div class="flex items-center gap-1">
                                <button type="submit" class="btn btn-primary btn-xs">Salvar</button>
                                <button type="submit" form="delete-format-{{ $f->id }}" class="btn btn-danger btn-xs" title="Remover">✕</button>
                            </div>
                        </form>
                        <form id="move-up-{{ $f->id }}" method="POST" action="{{ route('settings.points.formats.move', [$f, 'up']) }}" class="hidden">@csrf @method('PATCH')</form>
                        <form id="move-down-{{ $f->id }}" method="POST" action="{{ route('settings.points.formats.move', [$f, 'down']) }}" class="hidden">@csrf @method('PATCH')</form>
                        <form id="delete-format-{{ $f->id }}" method="POST" action="{{ route('settings.points.formats.destroy', $f) }}" class="hidden"
                              @submit.prevent="if (await $store.confirmDialog.ask('Remover o formato {{ addslashes($f->name) }}? Tarefas com ele voltam pro automático.')) $el.submit()">
                            @csrf @method('DELETE')
                        </form>
                    @endforeach
                </div>
            @else
                <p class="text-xs" style="color:var(--muted)">Nenhum formato — toda tarefa desse tipo vale o ponto padrão.</p>
            @endif

            <form x-show="adding" x-cloak method="POST" action="{{ route('settings.points.formats.store') }}"
                  class="grid gap-2 items-center grid-cols-1 md:grid-cols-[1fr_70px_2fr_auto] mt-2 px-2 py-2" style="border:1px dashed var(--border2)">
                @csrf
                <input type="hidden" name="task_type" value="{{ $type }}">
                <input name="name" required placeholder="Nome do formato (ex: Reels)" class="px-2 py-1 text-sm" style="{{ $inputStyle }}">
                <input type="number" name="points" min="0" max="500" required placeholder="pts" class="px-2 py-1 text-sm text-right" style="{{ $inputStyle }}">
                <input name="keywords" placeholder="palavras-chave, separadas por vírgula" class="px-2 py-1 text-xs font-mono" style="{{ $inputStyle }}">
                <button type="submit" class="btn btn-primary btn-xs">Adicionar</button>
            </form>
        </div>
    @endforeach
</div>
