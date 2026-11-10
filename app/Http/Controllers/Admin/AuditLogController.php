<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ReactPage;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['accion' => ['nullable', 'string', 'max:80'], 'q' => ['nullable', 'string', 'max:120']]);
        $query = AuditLog::query()->latest('created_at')->latest('id');
        if (! empty($filters['accion'])) $query->where('action', 'like', $filters['accion'].'%');
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->where('user_name', 'like', $term)->orWhere('subject_label', 'like', $term));
        }
        $logs = $query->paginate(25)->withQueryString();
        $props = [
            'logs' => $logs->getCollection()->map(fn (AuditLog $log) => [
                'id' => $log->id, 'when' => $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                'who' => $log->user_name ?? 'Sistema', 'action' => $log->action,
                'subject' => trim(($log->subject_type ? $log->subject_type.' #'.$log->subject_id : '').' '.($log->subject_label ?? '')),
                'details' => $log->details, 'ip' => $log->ip,
            ])->values(),
            'pagination' => ReactPage::pagination($logs),
            'filters' => ['accion' => $filters['accion'] ?? '', 'q' => $filters['q'] ?? ''],
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ];

        return view('admin.audit', compact('props'));
    }
}
