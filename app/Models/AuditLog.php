<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registro inmutable de operaciones sensibles: quién, cuándo, qué y sobre qué recurso. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'details', 'ip'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
