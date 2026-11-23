<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Miniatura leve de imagem (lado maior 400px, WebP ~20-40 KB) pra grade de
 * entregáveis — a tela da tarefa não precisa baixar a arte em resolução cheia
 * só pra mostrar um quadradinho. Qualquer falha devolve null e a tela cai no
 * arquivo original; nunca impede o upload.
 */
class ImageThumbnail
{
    private const MAX_SIDE   = 400;
    // Imagem gigante (ex: arte de adesivo 10k×10k) estoura memória no GD — pula.
    private const MAX_PIXELS = 50_000_000;

    public static function make(string $contents, string $disk, string $dir): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $info = @getimagesizefromstring($contents);
            if (!$info || $info[0] * $info[1] > self::MAX_PIXELS) {
                return null;
            }

            $src = @imagecreatefromstring($contents);
            if (!$src) {
                return null;
            }

            [$w, $h] = [imagesx($src), imagesy($src)];
            $scale = min(1, self::MAX_SIDE / max($w, $h));
            $tw = max(1, (int) round($w * $scale));
            $th = max(1, (int) round($h * $scale));

            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

            ob_start();
            $webp = function_exists('imagewebp') && imagewebp($dst, null, 78);
            if (!$webp) {
                ob_clean();
                imagejpeg($dst, null, 80);
            }
            $bytes = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);

            $path = trim($dir, '/') . '/' . Str::random(24) . ($webp ? '.webp' : '.jpg');
            $ok = Storage::disk($disk)->put($path, $bytes, ['ContentType' => $webp ? 'image/webp' : 'image/jpeg']);

            return $ok ? $path : null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
