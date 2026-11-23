<?php

namespace App\Http\Controllers;

use App\Models\DeliverableFeedback;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\TaskApprovalService;
use App\Support\ImageThumbnail;
use App\Support\UploadOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipStream\ZipStream;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task, TaskApprovalService $approvalService)
    {
        $request->validate([
            'files'   => 'required|array|min:1', // carrossel/múltiplas peças de uma vez
            'files.*' => 'file|max:153600', // 150 MB
            'kind'    => 'required|in:insumo,entregavel',
        ]);

        $disk = config('filesystems.default', 'r2');

        foreach ($request->file('files') as $file) {
            $mimeType = $file->getMimeType();
            $path = $file->store("tasks/{$task->id}", UploadOptions::forStore($mimeType, $disk));

            // Disco r2 está com throw=false — store() devolve false quando o
            // upload pro Cloudflare falha. Sem esse guard, gravava um
            // TaskAttachment com disk_path vazio (anexo "fantasma").
            if ($path === false) {
                return back()->with('warning', "Falha ao enviar \"{$file->getClientOriginalName()}\" — o arquivo não foi anexado. Tente de novo; se for um arquivo grande, pode levar um tempo.");
            }

            // Miniatura leve só de imagem — lida do arquivo local do upload, antes
            // de ele sumir (não baixa de volta do R2).
            $thumb = str_starts_with((string) $mimeType, 'image/') && $file->getSize() <= 30 * 1024 * 1024
                ? ImageThumbnail::make((string) file_get_contents($file->getRealPath()), $disk, "tasks/{$task->id}/thumbs")
                : null;

            TaskAttachment::create([
                'thumb_path'  => $thumb,
                'task_id'     => $task->id,
                'filename'    => $file->getClientOriginalName(),
                'disk_path'   => $path,
                'disk'        => $disk,
                'mime_type'   => $mimeType,
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'kind'        => $request->kind,
            ]);
        }

        // A tarefa pode já estar em Aprovação + situação "Enviar para o cliente"
        // esperando só o anexo — reavalia agora que o(s) arquivo(s) chegaram, porque
        // o upload não passa pelo TaskObserver (não muda status/situação da tarefa).
        $roundCreated = $approvalService->maybeAutoSubmitOnApprovalTransition($task);

        $redirect = redirect()->route('tasks.show', $task);
        $label = $request->file('files') && count($request->file('files')) > 1 ? 'Arquivos anexados.' : 'Arquivo anexado.';

        return $roundCreated ? $redirect : $redirect->with('success', $label);
    }

    // Zip de todos os Entregáveis da tarefa — streamado direto (sem escrever zip
    // temporário em disco), lendo cada arquivo do R2 sob demanda. maennchen/zipstream-php
    // em vez de ZipArchive porque a imagem Docker não tem a extensão nativa `zip` instalada.
    public function downloadDeliverablesZip(Task $task)
    {
        // Sem as versões anteriores (substituídas) — o zip é o pacote atual.
        $attachments = $task->attachments()->where('kind', 'entregavel')
            ->where(fn ($q) => $q->whereNull('approval_status')->orWhere('approval_status', '!=', 'superseded'))
            ->get();

        abort_if($attachments->isEmpty(), 404);

        // php.ini limita a 60s (max_execution_time) — suficiente pra requests normais,
        // mas baixar+re-empacotar vários entregáveis grandes (ex: artes de adesivo em
        // alta resolução) do R2 pode passar disso, matando o PHP no meio do stream e
        // entregando um .zip truncado/corrompido pro navegador. Só essa rota precisa
        // de mais tempo — não mexe no limite global (nginx.conf tem o mesmo ajuste em
        // fastcgi_read_timeout, senão o nginx corta antes do PHP).
        set_time_limit(300);

        $zipFilename = Str::slug($task->title) . '-entregaveis.zip';

        return response()->streamDownload(function () use ($attachments) {
            $zip = new ZipStream(
                outputName: 'entregaveis.zip',
                sendHttpHeaders: false, // headers já vão pelo response()->streamDownload()
            );

            $usedNames = [];
            foreach ($attachments as $attachment) {
                $name = $attachment->filename;
                if (isset($usedNames[$name])) {
                    $usedNames[$name]++;
                    $pathinfo = pathinfo($name);
                    $suffix   = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
                    $base     = $pathinfo['filename'] ?? $name;
                    $name     = "{$base} ({$usedNames[$name]}){$suffix}";
                } else {
                    $usedNames[$name] = 1;
                }

                $stream = Storage::disk($attachment->disk)->readStream($attachment->disk_path);
                if ($stream) {
                    $zip->addFileFromStream(fileName: $name, stream: $stream);
                }
            }

            $zip->finish();
        }, $zipFilename, ['Content-Type' => 'application/zip']);
    }

    /**
     * "Substituir": sobe a versão corrigida de uma peça. A nova herda a posição e
     * fica ligada à anterior (v2, v3...); a anterior vira "Versão anterior" e sai
     * da grade (continua no histórico da rodada em que foi enviada). Só a nova vai
     * na próxima rodada — as aprovadas não voltam pro cliente.
     */
    public function replace(Request $request, Task $task, TaskAttachment $attachment, TaskApprovalService $approvalService)
    {
        abort_unless($attachment->task_id === $task->id && $attachment->kind === 'entregavel', 403);

        $request->validate(['file' => 'required|file|max:153600']);

        if ($attachment->approval_status === 'approved') {
            return back()->with('warning', 'Essa peça já foi aprovada pelo cliente — não precisa substituir.');
        }
        if ($attachment->approval_status === 'pending') {
            return back()->with('warning', 'Essa peça está com o cliente agora. Espere a resposta (ou cancele a rodada na Central) antes de substituir.');
        }

        $file = $request->file('file');
        $disk = config('filesystems.default', 'r2');
        $mimeType = $file->getMimeType();
        $path = $file->store("tasks/{$task->id}", UploadOptions::forStore($mimeType, $disk));
        if ($path === false) {
            return back()->with('warning', "Falha ao enviar \"{$file->getClientOriginalName()}\" — tente de novo.");
        }

        $thumb = str_starts_with((string) $mimeType, 'image/') && $file->getSize() <= 30 * 1024 * 1024
            ? ImageThumbnail::make((string) file_get_contents($file->getRealPath()), $disk, "tasks/{$task->id}/thumbs")
            : null;

        TaskAttachment::create([
            'task_id'     => $task->id,
            'filename'    => $file->getClientOriginalName(),
            'disk_path'   => $path,
            'disk'        => $disk,
            'mime_type'   => $mimeType,
            'size'        => $file->getSize(),
            'uploaded_by' => Auth::id(),
            'kind'        => 'entregavel',
            'thumb_path'  => $thumb,
            'replaces_id' => $attachment->id,
            'version'     => ($attachment->version ?: 1) + 1,
            'sort_order'  => $attachment->sort_order,
        ]);

        // Ainda não foi pro cliente (approval_status vazio): a antiga some de vez da
        // grade do mesmo jeito — quem vale é a nova.
        $attachment->update(['approval_status' => 'superseded']);

        $roundCreated = $approvalService->maybeAutoSubmitOnApprovalTransition($task);
        $redirect = redirect()->route('tasks.show', $task);

        return $roundCreated ? $redirect : $redirect->with('success', 'Peça substituída — a versão nova vai na próxima rodada.');
    }

    // Ordem dos entregáveis arrastada na tarefa — é a ordem que o cliente vê na
    // aprovação (TaskAttachment::inDisplayOrder), inclusive em rodada já enviada.
    public function reorder(Request $request, Task $task)
    {
        $data = $request->validate([
            'ids'   => 'required|array|max:500',
            'ids.*' => 'string',
        ]);

        $valid = TaskAttachment::where('task_id', $task->id)
            ->whereIn('id', $data['ids'])
            ->pluck('id')
            ->all();

        foreach (array_values(array_intersect($data['ids'], $valid)) as $i => $id) {
            TaskAttachment::where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        abort_unless($attachment->task_id === $task->id, 403);

        // Excluir um entregável que já tem retorno do cliente apagaria o
        // feedback junto (FK com cascadeOnDelete) — perderia o histórico da
        // rodada. Se precisa de uma versão nova, é só anexar o arquivo
        // corrigido; o antigo fica registrado na rodada em que foi enviado.
        if (DeliverableFeedback::where('attachment_id', $attachment->id)->exists()) {
            $message = 'Esse arquivo já tem retorno do cliente registrado e não pode ser removido — anexe o material corrigido como um novo arquivo.';

            return request()->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('warning', $message);
        }

        Storage::disk($attachment->disk)->delete(array_filter([$attachment->disk_path, $attachment->thumb_path]));
        $attachment->delete();

        // A tela da tarefa remove a linha via fetch, sem recarregar a página inteira.
        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Anexo removido.');
    }
}
