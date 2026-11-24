<?php

namespace Database\Seeders;

use GdImage;
use RuntimeException;

/**
 * Genera una firma manuscrita simulada (PNG) para datos de demostración y pruebas.
 */
final class DemoSignature
{
    private const WIDTH = 600;

    private const HEIGHT = 200;

    public static function dataUrl(): string
    {
        $image = self::canvas();
        $ink = self::inkColor($image);
        imagesetthickness($image, 4);

        $prevX = 40;
        $prevY = intdiv(self::HEIGHT, 2);
        $phase = mt_rand(0, 100) / 10;

        for ($x = 40; $x < self::WIDTH - 40; $x += 4) {
            $y = (int) round(self::HEIGHT / 2 + sin($x / 22 + $phase) * 45 + cos($x / 9) * 12);
            imageline($image, $prevX, $prevY, $x, $y, $ink);
            [$prevX, $prevY] = [$x, $y];
        }

        return self::encode($image);
    }

    public static function blank(): string
    {
        return self::encode(self::canvas());
    }

    private static function canvas(): GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);

        if ($transparent === false) {
            throw new RuntimeException('No se pudo crear el lienzo de la firma.');
        }

        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);

        return $image;
    }

    private static function inkColor(GdImage $image): int
    {
        $color = imagecolorallocate($image, 20, 30, 60);

        if ($color === false) {
            throw new RuntimeException('No se pudo asignar el color de la firma.');
        }

        return $color;
    }

    private static function encode(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
