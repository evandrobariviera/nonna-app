{{-- Thread de notas + histórico de estágio do lead — reaproveitado pelo show
     interno (app/Http/Controllers/LeadController) e pelo show do Portal
     (app/Http/Controllers/Portal/LeadController). Espera $opportunity (com
     'notes.user'/'notes.contact' carregados) e $notesStoreRoute. --}}
<div class="card p-5" id="notas">
    <h3 class="text-xs font-mono uppercase tracking-widest text-[var(--muted)] mb-4">
        Notas
        @if($opportunity->notes->isNotEmpty())
            <span class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full" style="background:var(--s3); color:var(--muted2)">{{ $opportunity->notes->where('to_stage', null)->count() }}</span>
        @endif
    </h3>

    @if($opportunity->notes->isNotEmpty())
        <div class="flex flex-col mb-5">
            @foreach($opportunity->notes as $note)
                @if($note->isStageChange())
                    <div class="py-2 text-xs text-center" style="color: var(--muted); border-bottom: 1px dashed var(--border)">
                        {{ $note->author()?->name ?? '—' }} · {{ $note->body }} · {{ $note->created_at->format('d/m/Y H:i') }}
                    </div>
                @else
                    <div class="py-3" style="border-bottom:1px solid var(--border2)">
                        <div class="flex items-baseline gap-3 mb-1 flex-wrap">
                            <span class="text-sm font-semibold" style="color: var(--text)">{{ $note->author()?->name ?? '—' }}</span>
                            <span class="text-xs" style="color: var(--muted)">{{ $note->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="text-sm whitespace-pre-wrap" style="color: var(--text); line-height: 1.65">{{ $note->body }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @else
        <p class="text-sm mb-5" style="color: var(--muted)">Nenhuma nota ainda.</p>
    @endif

    <form method="POST" action="{{ $notesStoreRoute }}" x-data="{ body: '' }">
        @csrf
        <textarea name="body" x-model="body" rows="3"
                  placeholder="Escreva uma nota..."
                  class="w-full px-4 py-3 text-sm focus:outline-none resize-none rounded-lg"
                  style="background: var(--s2); border: 1px solid var(--border2); color: var(--text); line-height: 1.65"
                  onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'"></textarea>
        <div class="flex justify-end mt-2" x-show="body.trim().length > 0" x-cloak>
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                    style="background: var(--purple)"
                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                Adicionar nota
            </button>
        </div>
    </form>
</div>
