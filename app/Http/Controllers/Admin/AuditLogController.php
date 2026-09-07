<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const DATE_RANGES = [
        'today' => 'Today',
        '7d' => 'Last 7 Days',
        'month' => 'This Month',
    ];

    public function index(Request $request): View
    {
        $this->authorize('view-audit-logs');

        $search = $request->string('search')->trim()->toString();
        $departmentId = $request->integer('department_id');
        $action = $request->string('action')->toString();
        $dateRange = $request->string('date_range')->toString();

        $logs = AuditLog::query()
            ->with(['department', 'account'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas(
                'account',
                fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%"),
            ))
            ->when($departmentId, fn (Builder $query) => $query->where('department_id', $departmentId))
            ->when(
                AuditAction::tryFrom($action) !== null,
                fn (Builder $query) => $query->where('action', $action),
            )
            ->when($dateRange !== '', function (Builder $query) use ($dateRange): void {
                $this->applyDateRange($query, $dateRange);
            })
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'departments' => Department::query()->orderBy('name')->get(),
            'actionOptions' => collect(AuditAction::cases())->mapWithKeys(
                fn (AuditAction $case) => [$case->value => $case->label()],
            ),
            'dateRanges' => self::DATE_RANGES,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'action' => $action,
                'date_range' => $dateRange,
            ],
        ]);
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function applyDateRange(Builder $query, string $range): void
    {
        $now = now();

        match ($range) {
            'today' => $query->whereDate('created_at', $now->toDateString()),
            '7d' => $query->where('created_at', '>=', $now->copy()->subDays(7)),
            'month' => $query->where('created_at', '>=', $now->copy()->startOfMonth()),
            default => null,
        };
    }
}
