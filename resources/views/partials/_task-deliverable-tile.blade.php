{{-- Miniatura quadrada de Entregável (tasks/show.blade.php) — arrastável pra mudar
     a ordem que o cliente vê na aprovação. Espera: $task, $attachment.
     Os data-* são os mesmos da linha de anexo (_task-attachment-row): o visualizador
     (openAttachmentLightbox) e o remover (removeAttachment) servem aos dois.
     A imagem é a miniatura leve (thumbUrl); o número da posição vem de contador CSS,
     então se renumera sozinho ao arrastar ou remover. --}}
<div data-attachment
     data-id="{{ $attachment->id }}"
     data-kind="{{ $attachment->kind }}"
     data-src="{{ $attachment->url() }}"
     data-download="{{ $attachment->downloadUrl() }}"
     data-filename="{{ $attachment->filename }}"
     data-type="{{ $attachment->isImage() ? 'image' : ($attachment->isVideo() ? 'video' : 'file') }}"
     data-icon="{{ $attachment->icon() }}"
     class="deliv-tile"
     draggable="true"
     title="{{ $attachment->filename }} · {{ $attachment->sizeForHumans() }} · {{ $attachment->uploaderName() ?? '—' }} · {{ $attachment->created_at->format('d/m/Y H:i') }}">
    <button type="button" class="deliv-thumb" onclick="openAttachmentLightbox(this)" aria-label="Visualizar {{ $attachment->filename }}">
        @if($attachment->isImage())
            <img src="{{ $attachment->thumbUrl() }}" alt="" loading="lazy" decoding="async" draggable="false">
        @else
            <span class="deliv-icon">{{ $attachment->icon() }}</span>
        @endif
    </button>

    @if($attachment->is_deliverable)
        <span class="deliv-round">Rodada #{{ $attachment->round_number }}</span>
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
