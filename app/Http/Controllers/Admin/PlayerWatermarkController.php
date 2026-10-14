<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlayerWatermark;
use App\Services\WatermarkImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlayerWatermarkController extends Controller
{
    public function edit()
    {
        return view('admin.player-watermark', ['watermark' => PlayerWatermark::current()]);
    }

    public function update(Request $request, WatermarkImage $image)
    {
        $request->validate(['watermark' => [
            'bail', 'required', 'file', 'max:'.WatermarkImage::MAX_KB,
            'mimetypes:image/png', 'extensions:png',
        ]], [
            'watermark.required' => 'Selecciona un PNG con fondo transparente.',
            'watermark.max' => 'La marca de agua no debe superar los 2 MB.',
            'watermark.mimetypes' => 'Solo se admite PNG estático con transparencia. No se admiten JPG, SVG, GIF ni WebP.',
            'watermark.extensions' => 'El archivo debe tener extensión .png.',
        ]);

        $png = $image->normalize($request->file('watermark'));
        $path = 'player-watermarks/'.Str::uuid().'.png';
        $disk = Storage::disk('public');
        abort_unless($disk->put($path, $png), 500, 'No se pudo guardar la marca de agua.');
        try {
            PlayerWatermark::updateOrCreate(['id' => 1], ['path' => $path]);
        } catch (\Throwable $e) {
            $disk->delete($path);
            throw $e;
        }

        // Previous images stay available to already-open players. They are not
        // selected again unless explicitly uploaded by an administrator.
        return to_route('admin.watermark.edit')->with('success', 'Marca de agua actualizada. Se aplicará al abrir o recargar los reproductores.');
    }

    public function destroy()
    {
        PlayerWatermark::whereKey(1)->delete();

        return to_route('admin.watermark.edit')->with('success', 'Se restauró la marca de agua oficial de UEBTV.');
    }
}
