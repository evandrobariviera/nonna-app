{{-- Revisão das sugestões "Atualizar documento com reunião" (DocumentUpdateController::show).
     Enquanto a IA analisa, a página pergunta o status a cada 5s e recarrega quando termina.
     Cada sugestão: motivo + trecho da reunião + antes/depois em texto; a equipe marca quais
     aceita e o servidor aplica no documento atual (versão nova, anterior guardada). --}}
@php
    use App\Services\DocumentUpdate\HtmlPatch;
    $isPlan = $owner instanceof \App\Models\MacroPlan;
    $suggestions = collect($run->suggestions ?? []);
    $retryUrl = $isPlan ? route('macroplans.document.update-store', $owner) : route('projects.document.update-store', $owner);
    $tipoLabel = ['alterar' => 'Alterar', 'incluir' => 'Incluir', 'remover' => 'Remover'];
    $tipoColor = ['alterar' => 'purple', 'incluir' => 'green', 'remover' => 'red'];
@endphp
<x-app-layout>
    <x-slot name="header">Atualizar com reunião: {{ $owner->title }}</x-slot>

    <div class="max-w-4xl"
         x-data="{
             status: @js($run->status),
             init() {
                 if (this.status !== 'processando') return;
                 const timer = setInterval(async () => {
                     try {
                         const res = await fetch(@js(route('document-updates.status', $run)), { headers: { 'Accept': 'application/json' } });
                         if (res.ok && (await res.json()).status !== 'processando') { clearInterval(timer); window.location.reload(); }
                     } catch (e) {}
                 }, 5000);
             },
         }">

        <a href="{{ $backUrl }}" class="text-xs font-semibold flex items-center gap-1 mb-3" style="color:var(--muted)">
            <x-icon name="arrow-left" size="12" /> Voltar para {{ $isPlan ? 'o planejamento' : 'a ' . $kindLabel }}
        </a>

        @if(session('success'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(52,211,153,.08); border:1px solid rgba(52,211,153,.25); color:var(--green); border-radius:var(--radius)">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">
                {{ session('error') }}
            </div>
        @endif

        <div class="card p-5 mb-5">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">{{ ucfirst($kindLabel) }} × reunião</p>
                    <h1 class="text-base font-bold" style="color:var(--text)">{{ $owner->title }}</h1>
                    <p class="text-xs mt-1" style="color:var(--muted)">
                        @if($run->meeting)
                            Reunião <a href="{{ route('meetings.show', $run->meeting) }}" class="underline" style="color:var(--purple)">{{ $run->meeting->title }}</a>
                            de {{ $run->meeting->scheduled_at->format('d/m/Y') }}
                        @else
                            Reunião excluída
                        @endif
                        · pedido por {{ $run->requestedBy?->name ?? '—' }} em {{ $run->created_at->format('d/m H:i') }}
                    </p>
                    @if($run->instruction)
                        <p class="text-xs mt-2" style="color:var(--muted2)"><b>Foco pedido:</b> {{ $run->instruction }}</p>
                    @endif
                </div>
                <x-document-update-status :status="$run->status" />
            </div>
        </div>

        @if($run->isProcessing())
            <div class="card p-6 text-sm flex items-center gap-3" style="color:var(--purple)">
                <x-icon name="sparkles" size="16" class="animate-pulse" />
                <span>A IA está lendo a reunião e conferindo o documento… costuma levar de 1 a 4 minutos. A página atualiza sozinha (e você recebe um aviso no sino).</span>
            </div>

        @elseif($run->status === 'erro')
            <div class="card p-5">
                <p class="text-sm font-semibold mb-1" style="color:var(--red)">Não foi possível analisar o documento.</p>
                <p class="text-xs mb-4" style="color:var(--muted)">{{ $run->error }}</p>
                @if($run->meeting)
                    <form method="POST" action="{{ $retryUrl }}">
                        @csrf
                        <input type="hidden" name="meeting_id" value="{{ $run->meeting_id }}">
                        <input type="hidden" name="instruction" value="{{ $run->instruction }}">
                        <button type="submit" class="btn btn-primary btn-xs flex items-center gap-1"><x-icon name="refresh-cw" size="12" /> Tentar de novo</button>
                    </form>
                @endif
            </div>

        @else
            {{-- Resumo da IA --}}
            <div class="card p-5 mb-5" style="border-left:3px solid var(--purple)">
                <h2 class="card-title mb-2 flex items-center gap-1"><x-icon name="sparkles" size="14" /> O que a reunião muda</h2>
                <p class="text-sm whitespace-pre-line" style="color:var(--text)">{{ $run->summary ?: '—' }}</p>
            </div>

            @if($stale)
                <div class="mb-5 px-4 py-3 text-sm flex items-start gap-2" style="background:rgba(238,121,25,.08); border:1px solid rgba(238,121,25,.3); color:var(--orange); border-radius:var(--radius)">
                    <x-icon name="triangle-alert" size="14" class="mt-0.5 flex-shrink-0" />
                    <span>O documento ganhou uma versão nova depois desta análise. Dá para aplicar mesmo assim: cada trecho é procurado de novo no documento atual, e o que não for encontrado fica de fora (avisamos qual).</span>
                </div>
            @endif

            @if(!empty($run->open_points))
                <div class="card p-5 mb-5">
                    <h2 class="card-title mb-2">Pontos para conferir</h2>
                    <p class="text-xs mb-2" style="color:var(--muted)">Conversados na reunião, mas sem decisão clara. Não viraram sugestão.</p>
                    <ul class="text-sm space-y-1" style="color:var(--text)">
                        @foreach($run->open_points as $point)
                            <li>• {{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('document-updates.apply', $run) }}"
                  x-data="{ accepted: @js($suggestions->filter(fn ($s) => $s['aplicavel'])->pluck('id')->values()), withData: @js((bool) $dataDiff), sending: false }"
                  @submit="sending = true">
                @csrf

                @if($suggestions->isEmpty() && empty($dataDiff))
                    <div class="card p-5 text-sm" style="color:var(--muted)">
                        A IA não encontrou nada nesta reunião que mude o documento.
                    </div>
                @endif

                @foreach($suggestions as $s)
                    @php
                        $applied = $s['status'] ?? null;
                        $border = match ($applied) { 'aplicada' => 'var(--green)', 'falhou' => 'var(--red)', default => 'var(--border2)' };
                    @endphp
                    <div class="card p-5 mb-4" style="border-left:3px solid {{ $border }}">
                        <div class="flex items-start gap-3">
                            @if($run->isReady())
                                <input type="checkbox" name="accepted[]" value="{{ $s['id'] }}" x-model.number="accepted" class="mt-1"
                                       @disabled(!$s['aplicavel']) title="{{ $s['aplicavel'] ? 'Aceitar esta mudança' : 'Trecho não encontrado no documento' }}">
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="badge badge-{{ $tipoColor[$s['tipo']] ?? 'purple' }}">{{ $tipoLabel[$s['tipo']] ?? 'Alterar' }}</span>
                                    @if($s['secao'])<span class="text-xs font-mono uppercase" style="color:var(--muted)">{{ $s['secao'] }}</span>@endif
                                    @if($applied === 'aplicada')<span class="badge badge-green">Aplicada</span>@endif
                                    @if($applied === 'falhou')<span class="badge badge-red">Não aplicada: trecho mudou</span>@endif
                                </div>
                                <p class="text-sm font-semibold" style="color:var(--text)">{{ $s['titulo'] }}</p>
                                @if($s['motivo'])<p class="text-sm mt-1" style="color:var(--muted2)">{{ $s['motivo'] }}</p>@endif
                                @if($s['evidencia'])
                                    <p class="text-xs mt-2 flex items-start gap-1.5 italic" style="color:var(--muted)">
                                        <x-icon name="quote" size="12" class="mt-0.5 flex-shrink-0" /> {{ $s['evidencia'] }}
                                    </p>
                                @endif

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                    <div class="px-3 py-2 text-xs whitespace-pre-line rounded-lg" style="background:rgba(248,113,113,.06); border:1px solid rgba(248,113,113,.2); color:var(--text)">
                                        <p class="font-mono uppercase mb-1" style="color:var(--red)">Antes</p>{{ HtmlPatch::readable($s['trecho_atual']) ?: '—' }}
                                    </div>
                                    <div class="px-3 py-2 text-xs whitespace-pre-line rounded-lg" style="background:rgba(52,211,153,.06); border:1px solid rgba(52,211,153,.25); color:var(--text)">
                                        <p class="font-mono uppercase mb-1" style="color:var(--green)">Depois</p>{{ $s['trecho_novo'] === '' ? '(removido)' : (HtmlPatch::readable($s['trecho_novo']) ?: '—') }}
                                    </div>
                                </div>

                                @if(!$s['aplicavel'] && $run->isReady())
                                    <p class="text-xs mt-2" style="color:var(--orange)">
                                        A IA citou um trecho que não foi encontrado exatamente no documento. Se a mudança fizer sentido, faça pelo
                                        <a href="{{ $editUrl }}" class="underline">editor</a>.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                @if($dataDiff)
                    <div class="card p-5 mb-4" style="border-left:3px solid var(--orange)">
                        <div class="flex items-start gap-3">
                            @if($run->isReady())
                                <input type="checkbox" name="with_data" value="1" x-model="withData" class="mt-1">
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold" style="color:var(--text)">Dados do documento (datas, verba, peças)</p>
                                <p class="text-xs mt-1" style="color:var(--muted)">
                                    {{ $run->data_reason ?: 'A IA ajustou o bloco de dados para bater com as mudanças.' }}
                                    Ao aplicar, as datas e a verba também mudam no App.
                                </p>
                                <div class="mt-3 space-y-2">
                                    @foreach($dataDiff as [$field, $before, $after])
                                        <div class="grid grid-cols-1 md:grid-cols-[140px_1fr_1fr] gap-2 text-xs">
                                            <span class="font-mono" style="color:var(--muted)">{{ $field }}</span>
                                            <span class="whitespace-pre-line px-2 py-1 rounded" style="background:rgba(248,113,113,.06); color:var(--text)">{{ $before }}</span>
                                            <span class="whitespace-pre-line px-2 py-1 rounded" style="background:rgba(52,211,153,.06); color:var(--text)">{{ $after }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($run->isReady() && ($suggestions->isNotEmpty() || $dataDiff))
                    <div class="card px-5 py-4 flex items-center justify-between gap-3 flex-wrap lg:sticky lg:bottom-4">
                        <p class="text-xs" style="color:var(--muted)">
                            <span x-text="accepted.length"></span> de {{ $suggestions->count() }} sugestão(ões) marcada(s).
                            Vira uma versão nova do documento; a anterior fica guardada.
                        </p>
                        <div class="flex items-center gap-2">
                            <button type="submit" form="discard-form" class="btn btn-ghost btn-sm">Descartar</button>
                            <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1" :disabled="sending || (accepted.length === 0 && !withData)">
                                <x-icon name="check" size="14" /> <span x-text="sending ? 'Aplicando…' : 'Aplicar no documento'"></span>
                            </button>
                        </div>
                    </div>
                @endif
            </form>

            @if($run->isReady())
                <form id="discard-form" method="POST" action="{{ route('document-updates.discard', $run) }}">@csrf</form>
            @endif

            @if($run->status === 'aplicado')
                <div class="card px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-xs" style="color:var(--muted)">
                        Aplicado por {{ $run->appliedBy?->name ?? '—' }} em {{ $run->applied_at?->format('d/m/Y H:i') }}.
                        Quer ajustar alguma frase? Use o editor.
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ $editUrl }}" class="btn btn-ghost btn-sm flex items-center gap-1"><x-icon name="pencil" size="14" /> Abrir no editor</a>
                        <a href="{{ $backUrl }}" class="btn btn-primary btn-sm">Ver documento</a>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
