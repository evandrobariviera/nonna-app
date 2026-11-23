<?php

namespace App\Console\Commands;

use App\Models\TaskAttachment;
use App\Support\ImageThumbnail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Gera a miniatura leve dos Entregáveis de imagem enviados antes de existir
 * miniatura (23/11). Upload novo já nasce com ela. Pode rodar de novo à vontade:
 * só pega quem ainda não tem.
 */
class GenerateAttachmentThumbnails extends Command
{
    protected $signature = 'anexos:miniaturas {--limit=500 : quantos arquivos por rodada} {--since=120 : só anexos dos últimos N dias}';

    protected $description = 'Gera miniatura leve (WebP 400px) das imagens de Entregáveis que ainda não têm';

    public function handle(): int
    {
        $query = TaskAttachment::withoutGlobalScopes()
            ->where('kind', 'entregavel')
            ->whereNull('thumb_path')
            ->where('mime_type', 'like', 'image/%')
            ->where('size', '<=', 30 * 1024 * 1024)
            ->where('created_at', '>=', now()->subDays((int) $this->option('since')))
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'));

        $ok = $fail = 0;
        foreach ($query->get() as $a) {
            $contents = Storage::disk($a->disk)->get($a->disk_path);
            $thumb = $contents ? ImageThumbnail::make($contents, $a->disk, "tasks/{$a->task_id}/thumbs") : null;

            if ($thumb) {
                $a->forceFill(['thumb_path' => $thumb])->saveQuietly();
                $ok++;
            } else {
                $fail++;
            }
        }

        $this->info("Miniaturas geradas: {$ok}" . ($fail ? " · sem miniatura (fica o original): {$fail}" : ''));

        return self::SUCCESS;
    }
}
