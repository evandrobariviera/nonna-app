<?php

namespace App\Http\Controllers;

use App\Models\DeliverableFeedback;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\TaskApprovalService;
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

            TaskAttachment::create([
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
        $attachments = $task->attachments()->where('kind', 'entregavel')->get();

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

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        abort_unless($attachment->task_id === $task->id, 403);

        // Excluir um entregável que já tem retorno do cliente apagaria o
        // feedback junto (FK com cascadeOnDelete) — perderia o histórico da
        // rodada. Se precisa de uma versão nova, é só anexar o arquivo
        // corrigido; o antigo fica registrado na rodada em que foi enviado.
        if (DeliverableFeedback::where('attachment_id', $attachment->id)->exists()) {
            return back()->with('warning', 'Esse arquivo já tem retorno do cliente registrado e não pode ser removido — anexe o material corrigido como um novo arquivo.');
        }

        Storage::disk($attachment->disk)->delete($attachment->disk_path);
        $attachment->delete();

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Anexo removido.');
    }
}
