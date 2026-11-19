<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $severity = (string) $request->query('severity', '');
        $eventType = (string) $request->query('event_type', '');
        $userId = $request->query('user_id');
        $ipAddress = trim((string) $request->query('ip_address', ''));
        $dateFromRaw = trim((string) $request->query('date_from', ''));
        $dateToRaw = trim((string) $request->query('date_to', ''));
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw) === 1 ? $dateFromRaw : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw) === 1 ? $dateToRaw : '';
        $search = trim((string) $request->query('q', ''));

        $severityCounts = SecurityLog::query()
            ->reorder()
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as total')
            ->pluck('total', 'severity');

        $logs = SecurityLog::query()
            ->with('user')
            ->when($severity !== '' && in_array($severity, ['info', 'warning', 'critical'], true), fn ($q) => $q->where('severity', $severity))
            ->when($eventType !== '', fn ($q) => $q->where('event_type', $eventType))
            ->when($userId !== null && $userId !== '', fn ($q) => $q->where('user_id', (int) $userId))
            ->when($ipAddress !== '', fn ($q) => $q->where('ip_address', 'like', "%{$ipAddress}%"))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $eventTypes = SecurityLog::query()
            ->reorder()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.audit.index', [
            'logs' => $logs,
            'severityCounts' => $severityCounts,
            'eventTypes' => $eventTypes,
            'users' => $users,
            'severity' => $severity,
            'eventType' => $eventType,
            'userId' => $userId,
            'ipAddress' => $ipAddress,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'search' => $search,
        ]);
    }
}
