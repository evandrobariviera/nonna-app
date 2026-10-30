{{--
  Componente: <x-status-flow> — corrente de status em setas, o padrão pra mudar status na
  página de detalhe de qualquer coisa (tarefa, reunião, planejamento, campanha/projeto,
  contrato). Clique salva na hora via window.inlinePatch, sem formulário.

  Props:
    options — mapa key => ['label' => ..., 'color' => ...] (o $statuses do model)
    current — status atual
    action  — URL do PATCH. O controller precisa responder JSON quando wantsJson()
    exits   — keys que ficam fora da corrente, à direita: saída/exceção, não etapa do fluxo
    short   — rótulos curtos, key => texto (opcional)
    reload  — true recarrega a página depois de salvar, quando ela depende do status
    hint    — frase curta embaixo da corrente (opcional)

  Dispara o evento "status-changed" (window) com {key, label, color}, pra outro pedaço da
  página acompanhar sem recarregar.
--}}
@props(['options', 'current', 'action', 'exits' => [], 'short' => [], 'reload' => false, 'field' => 'status', 'hint' => null])

@php
    $flow    = collect($options)->except($exits);
    $exitSet = collect($options)->only($exits);
    $colors  = collect($options)->map(fn ($s) => \App\Models\Task::colorHex($s['color']));
    $labels  = collect($options)->map(fn ($s) => $s['label']);
    $chevron = 9;
    // Traço branco ao redor da forma já recortada: border normal ignora o clip-path.
    $outline = 'filter:drop-shadow(1px 0 0 #fff) drop-shadow(-1px 0 0 #fff) drop-shadow(0 1px 0 #fff) drop-shadow(0 -1px 0 #fff);';
@endphp

<div {{ $attributes->merge(['class' => 'card card-body']) }}
     x-data="{
         statusKey: @js($current),
         saving: false,
         colors: @js($colors),
         labels: @js($labels),
         async set(key) {
             if (this.statusKey === key || this.saving) return;
             this.saving = true;
             const { ok, message, cancelled } = await window.inlinePatch(@js($action), { {{ $field }}: key });
             this.saving = false;
             if (ok) {
                 this.statusKey = key;
                 this.$dispatch('status-changed', { key, label: this.labels[key], color: this.colors[key] });
                 if (@js($reload)) window.location.reload();
             } else if (!cancelled) {
                 alert(message || 'Não foi possível mudar o status.');
             }
         },
     }">
    <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:var(--muted); letter-spacing:.08em">Status</p>
    <div class="flex flex-wrap sm:flex-nowrap items-center gap-1.5">
        {{-- Celular: a corrente ocupa a linha toda (arrasta pro lado) e as saídas descem --}}
        <div class="flex w-full sm:w-auto sm:flex-1 min-w-0" style="overflow-x:auto">
            @foreach($flow as $key => $s)
                @php
                    $clip = $loop->first
                        ? "polygon(0 0, calc(100% - {$chevron}px) 0, 100% 50%, calc(100% - {$chevron}px) 100%, 0 100%)"
                        : ($loop->last
                            ? "polygon(0 0, 100% 0, 100% 100%, 0 100%, {$chevron}px 50%)"
                            : "polygon(0 0, calc(100% - {$chevron}px) 0, 100% 50%, calc(100% - {$chevron}px) 100%, 0 100%, {$chevron}px 50%)");
                    // Numa linha só: quebra de linha crua dentro da string do :style trava o Alpine.
                    $staticStyle = "clip-path:{$clip};min-width:82px;{$outline}cursor:pointer;padding-left:" . ($loop->first ? '13px' : '15px') . ';padding-right:' . ($loop->last ? '13px' : '15px') . ';margin-left:' . ($loop->first ? '0' : '-' . $chevron . 'px') . ';letter-spacing:.01em;';
                @endphp
                <button type="button" @click="set('{{ $key }}')"
                    class="flex-shrink-0 py-3 text-[13px] font-semibold text-center transition-colors"
                    :style="'{{ $staticStyle }}' + (statusKey === '{{ $key }}' ? 'background:' + colors['{{ $key }}'] + ';color:#fff;' : 'background:var(--s2);color:var(--muted2);')"
                    @mouseover="if (statusKey !== '{{ $key }}') $el.style.background = 'var(--s3)'"
                    @mouseout="if (statusKey !== '{{ $key }}') $el.style.background = 'var(--s2)'">
                    {{ $short[$key] ?? $s['label'] }}
                </button>
            @endforeach
        </div>

        @foreach($exitSet as $key => $s)
            <button type="button" @click="set('{{ $key }}')" class="flex-shrink-0 px-4 py-3 text-[13px] font-semibold transition-colors"
                :style="'letter-spacing:.01em;cursor:pointer;' + (statusKey === '{{ $key }}' ? 'border:1px solid ' + colors['{{ $key }}'] + ';background:' + colors['{{ $key }}'] + ';color:#fff;' : 'border:1px solid var(--border2);background:transparent;color:var(--muted2);')"
                @mouseover="if (statusKey !== '{{ $key }}') $el.style.background = 'var(--s2)'"
                @mouseout="if (statusKey !== '{{ $key }}') $el.style.background = 'transparent'">
                {{ $short[$key] ?? $s['label'] }}
            </button>
        @endforeach
    </div>
    @if($hint)
        <p class="text-xs mt-2" style="color:var(--muted)">{{ $hint }}</p>
    @endif
</div>
