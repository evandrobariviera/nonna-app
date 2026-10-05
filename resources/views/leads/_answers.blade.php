{{-- Conteúdo enviado pelo lead (assunto, mensagem, perguntas do Lead Ad...) — usado no show interno e no Portal. --}}
@php
    $renderAnswers = function (array $answers) {
        $messages = array_values(array_filter($answers, fn ($a) => \App\Services\Leads\FormAnswers::isMessage($a['label'])));
        $others   = array_values(array_filter($answers, fn ($a) => !\App\Services\Leads\FormAnswers::isMessage($a['label'])));
        return [$messages, $others];
    };
    [$messages, $others] = $renderAnswers($opportunity->answers());
    $previous = array_reverse($opportunity->previous_submissions ?? []);
@endphp

<div class="card p-5">
    <h3 class="text-xs font-mono uppercase tracking-widest mb-4" style="color: var(--muted)">
        Conteúdo do formulário
        @if($opportunity->form_name)
            <span class="normal-case tracking-normal font-sans" style="color: var(--muted2)">· {{ $opportunity->form_name }}</span>
        @endif
    </h3>

    @if(!$messages && !$others)
        <p class="text-sm" style="color: var(--muted)">Nenhum conteúdo além dos dados de contato chegou com este lead.</p>
    @else
        <div class="space-y-4">
            @foreach($messages as $a)
                <div>
                    <div class="text-xs mb-1" style="color: var(--muted)">{{ $a['label'] }}</div>
                    <div class="text-sm whitespace-pre-wrap px-4 py-3 rounded-lg" style="background: var(--s2); border: 1px solid var(--border2); color: var(--text); line-height: 1.65">{{ $a['value'] }}</div>
                </div>
            @endforeach

            @if($others)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    @foreach($others as $a)
                        <div class="min-w-0">
                            <div class="text-xs" style="color: var(--muted)">{{ $a['label'] }}</div>
                            <div class="text-sm break-words whitespace-pre-wrap" style="color: var(--text)">{{ $a['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($previous)
        <details class="mt-5 pt-4" style="border-top: 1px solid var(--border2)">
            <summary class="text-xs cursor-pointer" style="color: var(--purple)">
                {{ count($previous) }} {{ count($previous) === 1 ? 'envio anterior' : 'envios anteriores' }} desta pessoa
            </summary>
            <div class="mt-3 space-y-4">
                @foreach($previous as $sub)
                    <div class="pl-3" style="border-left: 2px solid var(--border2)">
                        <div class="text-xs mb-2" style="color: var(--muted)">
                            {{ $sub['received_at'] ? \Carbon\Carbon::parse($sub['received_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}
                            @if($sub['form_name'] ?? null) · {{ $sub['form_name'] }} @endif
                        </div>
                        @foreach($sub['answers'] ?? [] as $a)
                            <div class="text-sm mb-1" style="color: var(--text)">
                                <span style="color: var(--muted)">{{ $a['label'] }}:</span>
                                <span class="whitespace-pre-wrap">{{ $a['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
