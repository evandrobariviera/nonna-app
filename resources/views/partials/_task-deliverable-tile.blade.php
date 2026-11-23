{{-- Miniatura quadrada de Entregável (tasks/show.blade.php) — arrastável pra mudar
     a ordem que o cliente vê na aprovação. Espera: $task, $attachment, $pos.
     Os data-* são os mesmos da linha de anexo (_task-attachment-row): o visualizador
     (openAttachmentLightbox) e o remover (removeAttachment) servem aos dois.
     Mostra a situação da peça na aprovação (ajuste por peça, 23/11), o que o
     cliente pediu nela e — com ajuste pedido — o botão "Substituir". --}}
@php
    $status = $attachment->approval_status ?? 'none';
    // Pedido do cliente: na própria peça (ajuste pedido) ou, na v2 ainda não
    // enviada, o pedido da versão anterior que ela está atendendo.
    $asks = $attachment->approval_status === 'changes_requested'
        ? $attachment->feedbacks
        : ($attachment->approval_status === null && $attachment->replaces ? $attachment->replaces->feedbacks : collect());
    $asks = $asks->where('status', 'changes_requested')->filter(fn ($f) => filled($f->comment));
@endphp
<div data-attachment
     data-id="{{ $attachment->id }}"
     data-kind="{{ $attachment->kind }}"
     data-status="{{ $status }}"
     data-pos="{{ $pos }}"
     data-src="{{ $attachment->url() }}"
     data-download="{{ $attachment->downloadUrl() }}"
     data-filename="{{ $attachment->filename }}"
     data-type="{{ $attachment->isImage() ? 'image' : ($attachment->isVideo() ? 'video' : 'file') }}"
     data-icon="{{ $attachment->icon() }}"
     class="deliv-tile deliv-{{ $status }}"
     x-show="filter === 'all' || filter === $el.dataset.status"
     draggable="true"
     title="{{ $attachment->filename }} · {{ $attachment->sizeForHumans() }} · {{ $attachment->uploaderName() ?? '—' }} · {{ $attachment->created_at->format('d/m/Y H:i') }}">
    <button type="button" class="deliv-thumb" onclick="openAttachmentLightbox(this)" aria-label="Visualizar {{ $attachment->filename }}">
        @if($attachment->isImage())
            <img src="{{ $attachment->thumbUrl() }}" alt="" loading="lazy" decoding="async" draggable="false">
        @else
            <span class="deliv-icon">{{ $attachment->icon() }}</span>
        @endif
    </button>

    @if($attachment->version > 1)
        <span class="deliv-ver">v{{ $attachment->version }}</span>
    @endif

    <div class="deliv-state">
        <span>{{ $attachment->approvalLabel() }}@if($attachment->is_deliverable && $attachment->round_number) · R{{ $attachment->round_number }}@endif</span>
    </div>

    @if($asks->isNotEmpty())
        <div class="deliv-ask" title="{{ $asks->map(fn ($f) => ($f->token?->contact?->name ? \Illuminate\Support\Str::before($f->token->contact->name, ' ') . ': ' : '') . $f->comment)->implode("\n") }}">
            @foreach($asks as $f)
                <p><strong>{{ \Illuminate\Support\Str::before($f->token?->contact?->name ?? 'Cliente', ' ') }}:</strong> {{ $f->comment }}</p>
            @endforeach
        </div>
    @elseif($attachment->approval_status === 'changes_requested')
        <div class="deliv-ask"><p>Ajuste geral — veja o Retorno do Cliente.</p></div>
    @endif

    @if($attachment->approval_status === 'changes_requested')
        <form method="POST" action="{{ route('task-attachments.replace', [$task, $attachment]) }}" enctype="multipart/form-data" class="deliv-replace">
            @csrf
            <label title="Subir a versão corrigida desta peça — ela entra no lugar desta, na mesma posição">
                ⟳ Substituir
                <input type="file" name="file" class="hidden" @change="$el.closest('form').submit()">
            </label>
        </form>
    @endif

    <div class="deliv-foot">
        <span class="deliv-name">{{ $attachment->filename }}</span>
        <div class="deliv-actions">
            <a href="{{ $attachment->downloadUrl() }}" title="Baixar" draggable="false">↓</a>
            <form method="POST" action="{{ route('task-attachments.destroy', [$task, $attachment]) }}"
                  @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) removeAttachment($el)">
                @csrf @method('DELETE')
                <button type="submit" title="Remover" class="deliv-del">✕</button>
            </form>
        </div>
    </div>
</div>
