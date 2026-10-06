{{-- Configurações → Pontos de Sprint (App\Services\Tasks\SprintPoints / SprintPointsSettingsController).
     Ponto padrão por tipo + catálogo de formatos por tipo (com palavras-chave que detectam o
     formato pelo título — o PRIMEIRO da lista que bater vence, por isso dá pra reordenar) +
     botão de recalcular as tarefas existentes (ajustes manuais são preservados). --}}
@php
    $typePoints = \App\Models\TaskTypePoint::pluck('points', 'task_type');
    $formatsByType = \App\Models\TaskFormat::orderBy('position')->get()->groupBy('task_type');
    $inputStyle = 'background:var(--s2); border:1px solid var(--border2); color:var(--text)';
@endphp

<div class="flex flex-col gap-5">
    <div class="card card-body">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="max-w-2xl">
                <p class="text-sm font-bold" style="color:var(--text)">Como a tarefa ganha pontos</p>
                <p class="text-xs mt-1" style="color:var(--muted2); line-height:1.7">
                    1) Se alguém ajustou à mão na tarefa, vale o ajuste. 2) Se a tarefa tem um <strong>formato</strong>
                    escolhido, vale o ponto do formato. 3) Senão, o sistema procura no <strong>título</strong> as
                    palavras-chave dos formatos do tipo, de cima pra baixo, e o primeiro que bater vence
                    (por isso "Post Reels" tem que achar Reels antes de Post). 4) Nada bateu: vale o ponto padrão do tipo.
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
    </div>

    {{-- Ponto padrão por tipo --}}
    <form method="POST" action="{{ route('settings.points.types') }}" class="card card-body">
        @csrf @method('PUT')
        <p class="text-sm font-bold mb-1" style="color:var(--text)">Ponto padrão por tipo</p>
        <p class="text-xs mb-3" style="color:var(--muted2)">Usado quando nenhum formato do tipo bate com a tarefa.</p>
        <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(170px, 1fr))">
            @foreach(\App\Models\Task::$types as $type => $label)
                <label class="flex items-center justify-between gap-2 px-3 py-2" style="background:var(--s2)">
                    <span class="text-xs font-semibold" style="color:var(--text)">{{ $label }}</span>
                    <input type="number" name="points[{{ $type }}]" min="0" max="100" value="{{ $typePoints[$type] ?? '' }}"
                           class="w-16 px-2 py-1 text-sm text-right" style="{{ $inputStyle }}">
                </label>
            @endforeach
        </div>
        <div class="flex justify-end mt-3">
            <button type="submit" class="btn btn-primary btn-sm">Salvar pontos padrão</button>
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
                            <input type="number" name="points" min="0" max="100" value="{{ $f->points }}" required class="px-2 py-1 text-sm text-right" style="{{ $inputStyle }}" title="Pontos">
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
                <input type="number" name="points" min="0" max="100" required placeholder="pts" class="px-2 py-1 text-sm text-right" style="{{ $inputStyle }}">
                <input name="keywords" placeholder="palavras-chave, separadas por vírgula" class="px-2 py-1 text-xs font-mono" style="{{ $inputStyle }}">
                <button type="submit" class="btn btn-primary btn-xs">Adicionar</button>
            </form>
        </div>
    @endforeach
</div>
