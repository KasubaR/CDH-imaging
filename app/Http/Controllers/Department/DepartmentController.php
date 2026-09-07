<?php

namespace App\Http\Controllers\Department;

use App\Enums\TransferRecipientStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ExaminationType;
use App\Models\TransferRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    /**
     * Inbox status filter -> underlying TransferRecipientStatus value(s). "New" covers both
     * Pending and Delivered — see TransferRecipientStatus::inboxLabel().
     *
     * @var array<string, list<string>>
     */
    private const STATUS_FILTERS = [
        'new' => ['pending', 'delivered'],
        'received' => ['acknowledged'],
        'viewed' => ['viewed'],
        'downloaded' => ['downloaded'],
        'completed' => ['completed'],
        'rejected' => ['rejected'],
        'recalled' => ['recalled'],
    ];

    /**
     * @var array<string, string>
     */
    private const DATE_RANGES = [
        'today' => 'Today',
        '7d' => 'Last 7 Days',
        'month' => 'This Month',
    ];

    /**
     * The department inbox — every TransferRecipient row addressed to the current user's own
     * department. There is no cross-department browsing here: one user belongs to one department,
     * and this page is always scoped to it (see .ai/rules for the Phase 17 scoping decision).
     */
    public function index(Request $request): View
    {
        $departmentId = $request->user()?->department_id;

        $query = TransferRecipient::query()
            ->where('department_id', $departmentId ?? 0)
            ->with([
                'transfer.examination.patient',
                'transfer.examination.examinationType',
                'transfer.fromDepartment',
                'transfer.sentBy',
            ]);

        $this->applyFilters($query, $request);

        /** @var LengthAwarePaginator<int, TransferRecipient> $recipients */
        $recipients = $query->latest('id')->paginate(15)->withQueryString();

        return view('department.index', [
            'recipients' => $recipients,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'examinationTypes' => ExaminationType::query()->where('is_active', true)->orderBy('name')->get(),
            'statusOptions' => collect(self::STATUS_FILTERS)->mapWithKeys(
                fn (array $values, string $key) => [$key => TransferRecipientStatus::from($values[0])->inboxLabel()],
            ),
            'dateRanges' => self::DATE_RANGES,
            'filters' => [
                'patient' => $request->string('patient')->trim()->toString(),
                'department_id' => $request->string('department_id')->toString(),
                'sender_department_id' => $request->string('sender_department_id')->toString(),
                'examination_type_id' => $request->string('examination_type_id')->toString(),
                'status' => $request->string('status')->toString(),
                'date_range' => $request->string('date_range')->toString(),
            ],
        ]);
    }

    /**
     * @param  Builder<TransferRecipient>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $patient = $request->string('patient')->trim()->toString();

        if ($patient !== '') {
            $query->whereHas(
                'transfer.examination.patient',
                fn (Builder $inner) => $inner->where('patient_name', 'like', "%{$patient}%"),
            );
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->whereHas(
                'transfer.examination',
                fn (Builder $inner) => $inner->where('referring_department_id', $departmentId),
            );
        }

        if ($senderDepartmentId = $request->integer('sender_department_id')) {
            $query->whereHas(
                'transfer',
                fn (Builder $inner) => $inner->where('from_department_id', $senderDepartmentId),
            );
        }

        if ($examinationTypeId = $request->integer('examination_type_id')) {
            $query->whereHas(
                'transfer.examination',
                fn (Builder $inner) => $inner->where('examination_type_id', $examinationTypeId),
            );
        }

        $status = $request->string('status')->toString();

        if (array_key_exists($status, self::STATUS_FILTERS)) {
            $query->whereIn('status', self::STATUS_FILTERS[$status]);
        }

        $dateRange = $request->string('date_range')->toString();

        if (array_key_exists($dateRange, self::DATE_RANGES)) {
            $this->applyDateRange($query, $dateRange);
        }
    }

    /**
     * @param  Builder<TransferRecipient>  $query
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
