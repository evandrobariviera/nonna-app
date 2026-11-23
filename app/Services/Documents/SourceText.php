<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;

/**
 * Transforma um arquivo em algo que a IA lê (23/11, fontes do rascunho do agente):
 * PDF e imagem seguem como arquivo (a API da OpenAI lê direto); Word, PowerPoint, planilha,
 * HTML e texto viram texto aqui, sem biblioteca nova (são ZIP com XML dentro).
 */
class SourceText
{
    public const MAX_BYTES = 15 * 1024 * 1024;
    public const MAX_CHARS = 60000;

    /** @return array{kind: 'text'|'file'|'skip', text?: string, file?: array{name: string, mime: string, data: string}, reason?: string} */
    public static function fromStorage(string $disk, string $path, ?string $mime, string $filename): array
    {
        try {
            $data = Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            return ['kind' => 'skip', 'reason' => 'não foi possível abrir o arquivo'];
        }

        return $data === null ? ['kind' => 'skip', 'reason' => 'arquivo não encontrado'] : self::fromBytes($data, $mime, $filename);
    }

    public static function fromBytes(string $data, ?string $mime, string $filename): array
    {
        if (strlen($data) > self::MAX_BYTES) {
            return ['kind' => 'skip', 'reason' => 'arquivo maior que 15 MB'];
        }
        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = $mime ?: 'application/octet-stream';

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            return ['kind' => 'file', 'file' => ['name' => $filename, 'mime' => 'application/pdf', 'data' => $data]];
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            $imgMime = $ext === 'jpg' ? 'image/jpeg' : "image/{$ext}";

            return ['kind' => 'file', 'file' => ['name' => $filename, 'mime' => $imgMime, 'data' => $data]];
        }

        $text = match (true) {
            $ext === 'docx'                                  => self::zipXml($data, ['word/document.xml']),
            $ext === 'pptx'                                  => self::zipXml($data, [], '~^ppt/slides/slide\d+\.xml$~'),
            $ext === 'xlsx'                                  => self::zipXml($data, ['xl/sharedStrings.xml'], '~^xl/worksheets/sheet\d+\.xml$~'),
            in_array($ext, ['html', 'htm'], true)            => self::html($data),
            in_array($ext, ['txt', 'md', 'csv', 'json', 'vtt', 'srt'], true) || str_starts_with($mime, 'text/') => $data,
            default                                          => null,
        };
        if ($text === null) {
            return ['kind' => 'skip', 'reason' => "tipo de arquivo que a IA não lê (.{$ext})"];
        }

        $text = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", mb_convert_encoding($text, 'UTF-8', 'UTF-8'))));
        if ($text === '') {
            return ['kind' => 'skip', 'reason' => 'arquivo sem texto'];
        }

        return ['kind' => 'text', 'text' => mb_substr($text, 0, self::MAX_CHARS)];
    }

    /** Texto de arquivos do Office (ZIP com XML): parágrafos viram quebra de linha. */
    private static function zipXml(string $data, array $entries, ?string $pattern = null): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'src');
        file_put_contents($tmp, $data);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);

            return null;
        }
        $names = $entries;
        if ($pattern) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match($pattern, $n)) {
                    $names[] = $n;
                }
            }
            natsort($names);
        }
        $out = [];
        foreach (array_unique($names) as $name) {
            $xml = $zip->getFromName($name);
            if ($xml === false) {
                continue;
            }
            $xml = preg_replace('~</(w:p|a:p|si|row)>~', "\n", $xml);
            $xml = preg_replace('~<(w:tab|w:br)\b[^>]*/>~', ' ', $xml);
            $out[] = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $zip->close();
        @unlink($tmp);

        return implode("\n\n", $out);
    }

    public static function html(string $html): string
    {
        $html = preg_replace('~<(script|style)\b.*?</\1>~is', '', $html);
        $html = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>~i', "\n", $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
