<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class Audit
{
    /**
     * Registra una operación sensible. Un fallo del registro se reporta, pero
     * no interrumpe la operación que ya se completó.
     */
    public static function record(string $action, ?Model $subject = null, array $details = [], ?string $actorName = null): void
    {
        try {
            $user = auth()->user();
            AuditLog::create([
                'user_id' => $user?->id,
                'user_name' => $user ? $user->name.' <'.$user->email.'>' : ($actorName ?? 'Sistema'),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? mb_substr((string) ($subject->titulo ?? $subject->title ?? $subject->name ?? $subject->email ?? ''), 0, 250) ?: null : null,
                'details' => $details ?: null,
                'ip' => app()->runningInConsole() ? null : request()->ip(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
