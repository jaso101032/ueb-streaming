<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerWatermark extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'path', 'position'];

    public const POSITIONS = ['top-right', 'top-left', 'bottom-right', 'bottom-left'];

    public static function current(): array
    {
        // One shared setting, independent of the website's institutional logos.
        $watermark = static::find(1);
        $path = $watermark?->path ?: null;

        return [
            'url' => $path ? asset('storage/'.$path) : asset('images/brand/uebtv-logo-final.png'),
            'custom' => $path !== null,
            'position' => in_array($watermark?->position, self::POSITIONS, true) ? $watermark->position : 'top-right',
        ];
    }
}
