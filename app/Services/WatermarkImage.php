<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class WatermarkImage
{
    public const MAX_KB = 2048;

    public function normalize(UploadedFile $file): string
    {
        abort_unless(extension_loaded('gd'), 503, 'El servidor necesita la extensión PHP GD para procesar la marca de agua.');

        $bytes = file_get_contents($file->getRealPath());
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[2] !== IMAGETYPE_PNG) {
            $this->reject('El archivo debe ser una imagen PNG válida; cambiar la extensión no convierte una imagen.');
        }
        [$width, $height] = $size;
        if ($width < 128 || $height < 64 || $width > 2048 || $height > 2048 || $width < $height || $width > $height * 6) {
            $this->reject('Usa un PNG de 128 a 2048 px de ancho y 64 a 2048 px de alto, cuadrado u horizontal (proporción entre 1:1 y 6:1).');
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

        // Re-encode only decoded pixels; do not publish metadata, trailing data
        // or the uploaded filename. Bound stored resolution for mobile clients.
        if ($width > 768) {
            $image = imagescale($image, 768, (int) round($height * 768 / $width));
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 8);

        return ob_get_clean();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['watermark' => $message]);
    }
}
