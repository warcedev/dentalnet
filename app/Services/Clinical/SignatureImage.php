<?php

namespace App\Services\Clinical;

use Illuminate\Validation\ValidationException;

/**
 * Valida y decodifica la firma capturada en el canvas (data URL PNG).
 */
class SignatureImage
{
    public const MAX_BYTES = 300 * 1024;

    public const MAX_WIDTH = 2400;

    public const MAX_HEIGHT = 1200;

    /** Proporción mínima de píxeles con trazo para no aceptar una firma vacía. */
    public const MIN_INK_RATIO = 0.002;

    /** @return string Bytes PNG validados */
    public function decode(string $dataUrl, string $field = 'signature'): string
    {
        $prefix = 'data:image/png;base64,';

        if (! str_starts_with($dataUrl, $prefix)) {
            throw ValidationException::withMessages([$field => 'La firma no tiene un formato válido.']);
        }

        $bytes = base64_decode(substr($dataUrl, strlen($prefix)), true);

        if ($bytes === false || $bytes === '' || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            throw ValidationException::withMessages([$field => 'La firma no tiene un formato válido.']);
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'La imagen de la firma es demasiado grande.']);
        }

        $size = @getimagesizefromstring($bytes);

        if ($size === false || $size[0] > self::MAX_WIDTH || $size[1] > self::MAX_HEIGHT || $size[0] < 50 || $size[1] < 20) {
            throw ValidationException::withMessages([$field => 'La imagen de la firma no tiene un tamaño válido.']);
        }

        if ($this->inkRatio($bytes) < self::MIN_INK_RATIO) {
            throw ValidationException::withMessages([$field => 'La firma está vacía. Pida que firme en el recuadro.']);
        }

        return $bytes;
    }

    /** Proporción de píxeles (muestreados) que tienen trazo visible. */
    private function inkRatio(string $bytes): float
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return 0.0;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $step = 2;
        $total = 0;
        $ink = 0;

        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                $total++;
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $luma = ((($rgba >> 16) & 0xFF) + (($rgba >> 8) & 0xFF) + ($rgba & 0xFF)) / 3;

                if ($alpha < 100 && $luma < 160) {
                    $ink++;
                }
            }
        }

        imagedestroy($image);

        return $ink / $total;
    }
}
