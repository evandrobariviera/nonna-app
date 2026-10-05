{{-- Card "Histórico" — trilha de ações (quem fez o quê e quando), compartilhado
     por Tarefa (TaskActivity), Projeto (ProjectActivity) e Planejamento
     (MacroPlanActivity). Cada item precisa de actionLabel(), from_label, to_label,
     user e created_at. Só ações, nunca conteúdo de texto. --}}
@props(['activities', 'maxHeight' => '360px'])

@if($activities->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'card']) }}>
        <p class="text-xs font-semibold uppercase tracking-widest mb-4 flex items-center gap-2" style="color:var(--muted); letter-spacing:.1em">
            <span class="icon-badge">
                <x-icon name="history" size="16" />
            </span>
            Histórico
        </p>
        <div class="flex flex-col" style="max-height:{{ $maxHeight }}; overflow-y:auto">
            @foreach($activities as $activity)
                <div class="flex gap-3">
                    {{-- Trilha: ponto + linha conectando ao próximo (o próprio ponto
                         mais recente vem preenchido em roxo, os demais só contorno). --}}
                    <div class="flex flex-col items-center flex-shrink-0">
                        <span class="h-2.5 w-2.5 rounded-full flex-shrink-0"
                              style="{{ $loop->first ? 'background:var(--purple)' : 'background:var(--s1); border:2px solid var(--border2)' }}"></span>
                        @if(!$loop->last)
                            <span class="flex-1" style="width:1px; min-height:10px; background:var(--border2)"></span>
                        @endif
                    </div>
                    <div class="text-xs min-w-0 {{ !$loop->last ? 'pb-4' : '' }}">
                        <p style="color:var(--text); font-weight:500; line-height:1.4; overflow-wrap:anywhere">
                            {{ $activity->actionLabel() }}
                            @if($activity->from_label && $activity->to_label)
                                <span style="color:var(--muted2)">— {{ $activity->from_label }} → {{ $activity->to_label }}</span>
                            @elseif($activity->to_label)
                                <span style="color:var(--muted2)">— {{ $activity->to_label }}</span>
                            @endif
                        </p>
                        <p class="mt-0.5" style="color:var(--muted)">
                            {{ $activity->user?->name ? explode(' ', $activity->user->name)[0] : 'Sistema' }}
                            · {{ $activity->created_at->format('d/m/Y H:i') }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
