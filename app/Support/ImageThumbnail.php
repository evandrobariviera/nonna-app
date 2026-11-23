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

    // O GD abre a imagem inteira descomprimida (~5 bytes por pixel com folga).
    // Achado real (23/11): uma arte grande pediu 127 MB e derrubou o PHP com limite
    // de 256 MB — erro fatal não cai no catch. Só tenta se sobrar memória de verdade.
    private static function fitsInMemory(int $pixels): bool
    {
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true; // sem limite
        }

        return $pixels * 5 + 8 * 1024 * 1024 < ($limit - memory_get_usage(true)) * 0.7;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    public static function make(string $contents, string $disk, string $dir): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $info = @getimagesizefromstring($contents);
            if (!$info || $info[0] * $info[1] > self::MAX_PIXELS || !self::fitsInMemory($info[0] * $info[1])) {
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
