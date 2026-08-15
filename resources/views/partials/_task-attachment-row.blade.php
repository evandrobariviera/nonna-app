{{-- Linha de anexo (Insumo ou Entregável) — compartilhada pelas duas seções de
     tasks/show.blade.php. Espera: $task, $attachment.
     Miniatura pra imagem (isImage()), ícone genérico pros demais tipos; botão
     Visualizar abre lightbox (imagem) ou nova aba (outros tipos); botão Baixar
     usa downloadUrl() — força download de verdade em vez de só abrir o arquivo. --}}
<tr>
    <td>
        <div class="flex items-center gap-3">
            @if($attachment->isImage())
                <button type="button"
                        onclick="openAttachmentLightbox('{{ $attachment->url() }}', @js($attachment->filename))"
                        class="flex-shrink-0" style="width:40px; height:40px; border-radius:6px; overflow:hidden; border:1px solid var(--border2); padding:0; cursor:zoom-in">
                    <img src="{{ $attachment->url() }}" alt="{{ $attachment->filename }}"
                         style="width:100%; height:100%; object-fit:cover; display:block">
                </button>
            @else
                <span class="flex items-center justify-center flex-shrink-0"
                      style="width:40px; height:40px; border-radius:6px; background:var(--s3); border:1px solid var(--border); border-radius:8px; font-size:18px">
                    {{ $attachment->icon() }}
                </span>
            @endif
            <div class="min-w-0 flex items-center gap-2 flex-wrap">
                <span class="font-medium truncate" style="color:var(--text)">{{ $attachment->filename }}</span>
                @if($attachment->is_deliverable)
                    <span class="px-1.5 py-0.5 text-xs font-semibold flex-shrink-0"
                          style="background:rgba(100, 59, 142,.12); color:var(--purple)">
                        Enviado · Rodada #{{ $attachment->round_number }}
                    </span>
                @endif
            </div>
        </div>
    </td>
    <td style="color:var(--muted2)">{{ $attachment->sizeForHumans() }}</td>
    <td style="color:var(--muted2)">{{ $attachment->uploaderName() ?? '—' }}</td>
    <td style="color:var(--muted2)">{{ $attachment->created_at->format('d/m/Y H:i') }}</td>
    <td class="text-right">
        <div class="flex items-center justify-end gap-3">
            @if($attachment->isImage())
                <button type="button" title="Visualizar" class="btn btn-ghost btn-xs"
                        onclick="openAttachmentLightbox('{{ $attachment->url() }}', @js($attachment->filename))">
                    👁
                </button>
            @else
                <a href="{{ $attachment->url() }}" target="_blank" title="Visualizar" class="btn btn-ghost btn-xs">👁</a>
            @endif
            <a href="{{ $attachment->downloadUrl() }}" title="Baixar" class="btn btn-ghost btn-xs">↓</a>
            <form method="POST" action="{{ route('task-attachments.destroy', [$task, $attachment]) }}"
                  @submit.prevent="if (await $store.confirmDialog.ask('Remover anexo?')) $el.submit()">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-xs">✕</button>
            </form>
        </div>
    </td>
</tr>
