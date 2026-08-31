{{--
    Painel "Variáveis disponíveis" — chips que copiam o token no clique.
    Uso:
      <x-variable-hints context="opportunity" />          (single-brace, com descrição)
      <x-variable-hints :tokens="['{{cliente}}', ...]" />  (lista literal, sem descrição)
--}}
@props(['context' => null, 'tokens' => null])

@php
    $items = [];
    if ($context) {
        foreach (\App\Support\TemplateVariables::for($context) as $token => $meta) {
            $items[] = ['full' => '{' . $token . '}', 'title' => $meta[0] . ' — ex: ' . $meta[1]];
        }
    } elseif (is_array($tokens)) {
        foreach ($tokens as $t) {
            $items[] = ['full' => $t, 'title' => ''];
        }
    }
@endphp

@if(!empty($items))
    <div x-data="{ copied: null }" class="text-xs">
        <p class="text-[var(--muted)] mb-1.5">
            Variáveis disponíveis <span class="opacity-60">— clique pra copiar</span>
        </p>
        <div class="flex flex-wrap gap-1.5">
            @foreach($items as $i => $item)
                <button type="button"
                        @click="navigator.clipboard.writeText(@js($item['full'])); copied = {{ $i }}; setTimeout(() => copied = null, 1200)"
                        class="px-1.5 py-0.5 rounded font-mono transition-colors"
                        style="background: var(--s3); border: 1px solid var(--border2)"
                        @if($item['title']) title="{{ $item['title'] }}" @endif>
                    <span x-show="copied !== {{ $i }}" style="color: var(--purple)">{{ $item['full'] }}</span>
                    <span x-show="copied === {{ $i }}" x-cloak style="color: var(--green)">copiado!</span>
                </button>
            @endforeach
        </div>
    </div>
@endif
