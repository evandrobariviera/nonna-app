{{-- "Atualizar documentos com esta reunião" — a partir da reunião, escolhe qual planejamento,
     campanha ou projeto do cliente a IA deve conferir (DocumentUpdateController::fromMeeting). --}}
<x-app-layout>
    <x-slot name="header">Atualizar documentos com a reunião</x-slot>

    <div class="max-w-3xl">
        <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-semibold flex items-center gap-1 mb-3" style="color:var(--muted)">
            <x-icon name="arrow-left" size="12" /> Voltar para a reunião
        </a>

        @if(session('error'))
            <div class="mb-5 px-4 py-3 text-sm font-semibold" style="background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25); color:var(--red); border-radius:var(--radius)">
                {{ session('error') }}
            </div>
        @endif

        <div class="card p-5 mb-5">
            <p class="text-xs font-mono uppercase tracking-widest mb-1" style="color:var(--muted)">Reunião</p>
            <h1 class="text-base font-bold" style="color:var(--text)">{{ $meeting->title }}</h1>
            <p class="text-xs mt-1" style="color:var(--muted)">
                {{ $meeting->scheduled_at->format('d/m/Y') }} · {{ $meeting->typeLabel() }} · {{ $meeting->client?->displayName() ?? 'sem cliente' }}
            </p>
            <p class="text-sm mt-3" style="color:var(--text)">
                Escolha qual documento a IA deve conferir com a ATA e a transcrição desta reunião. Ela sugere as mudanças e
                você aceita uma a uma antes de qualquer coisa mudar.
            </p>
        </div>

        @if(blank($meeting->ata) && blank($meeting->transcricao))
            <div class="card p-5 text-sm" style="color:var(--muted)">
                Esta reunião ainda não tem ATA nem transcrição. Registre uma das duas primeiro.
            </div>
        @elseif($owners->isEmpty())
            <div class="card p-5 text-sm" style="color:var(--muted)">
                Nenhum planejamento, campanha ou projeto em andamento deste cliente tem documento anexado.
            </div>
        @else
            <div class="card p-5" x-data="{ open: null }">
                @foreach($owners as $item)
                    @php
                        $o = $item['owner'];
                        $isPlan = $o instanceof \App\Models\MacroPlan;
                        $storeUrl = $isPlan ? route('macroplans.document.update-store', $o) : route('projects.document.update-store', $o);
                    @endphp
                    <div class="py-3" @if(!$loop->first) style="border-top:1px solid var(--border2)" @endif>
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate" style="color:var(--text)">{{ $o->title }}</p>
                                <p class="text-xs" style="color:var(--muted)">
                                    {{ ucfirst($item['kind']) }}
                                    @if($isPlan && $o->period_start) · {{ $o->period_start->format('d/m') }} a {{ $o->period_end?->format('d/m/Y') }}@endif
                                    @if(!$isPlan && $o->start_date) · {{ $o->start_date->format('d/m') }}{{ $o->end_date ? ' a ' . $o->end_date->format('d/m/Y') : '' }}@endif
                                    @if($item['cited']) · <span style="color:var(--purple)">citado nesta reunião</span>@endif
                                </p>
                            </div>
                            <button type="button" class="btn btn-ghost btn-xs flex items-center gap-1" @click="open = open === @js($o->id) ? null : @js($o->id)">
                                <x-icon name="sparkles" size="12" /> Conferir com a IA
                            </button>
                        </div>
                        <form method="POST" action="{{ $storeUrl }}" x-show="open === @js($o->id)" x-cloak class="mt-3" x-data="{ sending: false }" @submit="sending = true">
                            @csrf
                            <input type="hidden" name="meeting_id" value="{{ $meeting->id }}">
                            <textarea name="instruction" rows="2" maxlength="2000" class="field w-full px-3 py-2 text-sm"
                                      placeholder="Algum foco ou cuidado? (opcional)"></textarea>
                            <div class="flex justify-end mt-2">
                                <button type="submit" class="btn btn-primary btn-xs flex items-center gap-1" :disabled="sending">
                                    <x-icon name="sparkles" size="12" /> <span x-text="sending ? 'Enviando…' : 'Conferir documento'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
