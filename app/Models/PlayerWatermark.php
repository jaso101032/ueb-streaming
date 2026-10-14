<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerWatermark extends Model
{
    public $incrementing = false;

    protected $fillable = ['id', 'path'];

    public static function current(): array
    {
        // One shared setting, independent of the website's institutional logos.
        $path = static::find(1)?->path;

        return [
            'url' => $path ? asset('storage/'.$path) : asset('images/brand/uebtv-logo-final.png'),
            'custom' => $path !== null,
        ];
    }
}
