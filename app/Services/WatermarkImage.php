<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class WatermarkImage
{
    public const MAX_KB = 2048;
    private const MAX_SOURCE_PIXELS = 16_000_000;
    private const MAX_OUTPUT_SIDE = 768;

    public function normalize(UploadedFile $file): string
    {
        abort_unless(extension_loaded('gd'), 503, 'El servidor necesita la extensión PHP GD para procesar la marca de agua.');

        $bytes = file_get_contents($file->getRealPath());
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[2] !== IMAGETYPE_PNG) {
            $this->reject('El archivo debe ser una imagen PNG válida; cambiar la extensión no convierte una imagen.');
        }
        [$width, $height] = $size;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_SOURCE_PIXELS) {
            $this->reject('La imagen tiene una resolución demasiado grande para procesarla de forma segura. Reduce sus dimensiones y vuelve a intentarlo.');
        }

        // Inspect PNG chunks, not arbitrary compressed pixel bytes. Animated
        // PNGs are not appropriate for a permanent, unobtrusive watermark.
        for ($offset = 8, $length = strlen($bytes); $offset + 12 <= $length;) {
            $chunkLength = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            if ($type === 'acTL') {
                $this->reject('No se permiten imágenes animadas. Exporta el logo como PNG estático con fondo transparente.');
            }
            if ($type === 'IEND') {
                break;
            }
            $offset += $chunkLength + 12;
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            $this->reject('No se pudo leer el PNG. Vuelve a exportarlo con fondo transparente.');
        }
        imagepalettetotruecolor($image);
        $transparent = $visible = $clearBorder = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $alpha = (imagecolorat($image, $x, $y) >> 24) & 0x7F;
                if ($alpha >= 120) {
                    $transparent++;
                    if ($x === 0 || $x === $width - 1 || $y === 0 || $y === $height - 1) {
                        $clearBorder++;
                    }
                } elseif ($alpha < 64) {
                    $visible++;
                }
            }
        }
        if ($transparent < $width * $height * .05 || $clearBorder < (2 * $width + 2 * $height - 4) * .5) {
            $this->reject('El PNG debe tener fondo realmente transparente, también en sus bordes. No se admiten fondos blancos, negros ni una cuadrícula dibujada.');
        }
        if ($visible < $width * $height * .01) {
            $this->reject('La imagen está vacía o es demasiado transparente. El logo debe ser visible.');
        }

        // Fit the full logo into a square-to-6:1 transparent canvas. Portrait
        // and very wide artwork gets padding instead of being cropped or
        // stretched. Small inputs are enlarged just enough to meet the player
        // canvas minimum; large inputs are capped for playback efficiency.
        $ratio = $width / $height;
        if ($ratio < 1) {
            $canvasWidth = $canvasHeight = $height;
        } elseif ($ratio > 6) {
            $canvasWidth = $width;
            $canvasHeight = (int) ceil($width / 6);
        } else {
            $canvasWidth = $width;
            $canvasHeight = $height;
        }

        $maxScale = min(self::MAX_OUTPUT_SIDE / $canvasWidth, self::MAX_OUTPUT_SIDE / $canvasHeight);
        $minScale = max(128 / $canvasWidth, 64 / $canvasHeight);
        $scale = min($maxScale, max(1, $minScale));
        $outputWidth = max(128, min(self::MAX_OUTPUT_SIDE, (int) round($canvasWidth * $scale)));
        $outputHeight = max(64, min(self::MAX_OUTPUT_SIDE, (int) round($canvasHeight * $scale)));
        // Integer rounding at extreme aspect ratios can miss the 6:1 limit by
        // one pixel, so correct the canvas while preserving its full content.
        if ($outputWidth > $outputHeight * 6) {
            $outputHeight = (int) ceil($outputWidth / 6);
        }
        if ($outputHeight > $outputWidth) {
            $outputWidth = $outputHeight;
        }

        $contentScale = min($scale, $outputWidth / $width, $outputHeight / $height);
        $contentWidth = max(1, (int) round($width * $contentScale));
        $contentHeight = max(1, (int) round($height * $contentScale));
        $contentWidth = min($contentWidth, $outputWidth);
        $contentHeight = min($contentHeight, $outputHeight);
        $source = imagescale($image, $contentWidth, $contentHeight);
        if (! $source) {
            $this->reject('No se pudo ajustar el PNG. Exporta una versión estática y vuelve a intentarlo.');
        }
        $canvas = imagecreatetruecolor($outputWidth, $outputHeight);
        if (! $canvas) {
            imagedestroy($image);
            imagedestroy($source);
            $this->reject('No se pudo preparar el lienzo de la marca de agua. Inténtalo con otra imagen.');
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $clear);
        imagecopy($canvas, $source, (int) floor(($outputWidth - $contentWidth) / 2), (int) floor(($outputHeight - $contentHeight) / 2), 0, 0, $contentWidth, $contentHeight);

        // Re-encode only decoded pixels; do not publish metadata, trailing data
        // or the uploaded filename.
        ob_start();
        imagepng($canvas, null, 8);
        $normalized = ob_get_clean();
        imagedestroy($image);
        imagedestroy($source);
        imagedestroy($canvas);

        return $normalized;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['watermark' => $message]);
    }
}
