<?php

namespace App\Support;

class UploadOptions
{
    // Sem charset no Content-Type, o R2/S3 devolve só "text/plain" — o navegador chuta a
    // codificação ao abrir o link assinado direto e vira mojibake em qualquer acento,
    // mesmo com o arquivo em si 100% UTF-8 correto (achado real num anexo de Reunião).
    // Só afeta texto; binário (imagem/PDF/áudio) não é interpretado como texto pelo
    // navegador, então não precisa do charset.
    public static function forStore(string $mimeType, string $disk): array
    {
        $options = ['disk' => $disk];

        if (str_starts_with($mimeType, 'text/')) {
            $options['ContentType'] = $mimeType . '; charset=UTF-8';
        }

        return $options;
    }
}
