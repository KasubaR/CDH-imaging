<?php

namespace App\Services;

use App\Enums\TransferRecipientStatus;
use App\Models\Department;
use App\Models\Image;
use App\Models\Transfer;
use App\Models\TransferRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /**
     * @return array{
     *     new: int,
     *     received_today: int,
     *     sent_today: int,
     *     pending: int,
     *     completed: int
     * }
     */
    public function departmentTransferStats(int $departmentId): array
    {
        return [
            'new' => $this->inboxFor($departmentId)
                ->whereIn('status', [
                    TransferRecipientStatus::Pending->value,
                    TransferRecipientStatus::Delivered->value,
                ])
                ->count(),
            'received_today' => $this->inboxFor($departmentId)
                ->whereDate('acknowledged_at', today())
                ->count(),
            'sent_today' => $this->transfersTodayQuery()
                ->where('from_department_id', $departmentId)
                ->count(),
            'pending' => $this->inboxFor($departmentId)
                ->where('status', TransferRecipientStatus::Pending)
                ->count(),
            'completed' => $this->inboxFor($departmentId)
                ->where('status', TransferRecipientStatus::Completed)
                ->count(),
        ];
    }

    /**
     * @return Collection<int, TransferRecipient>
     */
    public function departmentRecentTransfers(int $departmentId, int $limit = 10): Collection
    {
        return TransferRecipient::query()
            ->where('department_id', $departmentId)
            ->with([
                'transfer.examination.patient',
                'transfer.examination.examinationType',
            ])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{
     *     departments: int,
     *     images_today: int,
     *     transfers_today: int,
     *     failed_transfers: int,
     *     storage_used: string
     * }
     */
    public function adminOverview(): array
    {
        $storageBytes = (int) Image::query()->sum('file_size');

        return [
            'departments' => Department::query()->where('is_active', true)->count(),
            'images_today' => Image::query()->whereDate('created_at', today())->count(),
            'transfers_today' => $this->transfersTodayQuery()->count(),
            'failed_transfers' => TransferRecipient::query()
                ->where('status', TransferRecipientStatus::Rejected)
                ->whereDate('rejected_at', today())
                ->count(),
            'storage_used' => $this->formatStorageGb($storageBytes),
        ];
    }

    /**
     * Top sending departments by outbound transfer count today.
     *
     * @return Collection<int, object{department: Department, count: int}>
     */
    public function adminActivityToday(int $limit = 10): Collection
    {
        $rows = $this->transfersTodayQuery()
            ->select('from_department_id', DB::raw('COUNT(*) as transfer_count'))
            ->groupBy('from_department_id')
            ->orderByDesc('transfer_count')
            ->limit($limit)
            ->get();

        $departments = Department::query()
            ->whereIn('id', $rows->pluck('from_department_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(function (Transfer $row) use ($departments) {
                $department = $departments->get($row->from_department_id);

                if ($department === null) {
                    return null;
                }

                return (object) [
                    'department' => $department,
                    'count' => (int) $row->transfer_count,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Builder<TransferRecipient>
     */
    private function inboxFor(int $departmentId): Builder
    {
        return TransferRecipient::query()->where('department_id', $departmentId);
    }

    /**
     * @return Builder<Transfer>
     */
    private function transfersTodayQuery(): Builder
    {
        return Transfer::query()->where(function (Builder $query): void {
            $query->whereDate('sent_at', today())
                ->orWhere(function (Builder $inner): void {
                    $inner->whereNull('sent_at')->whereDate('created_at', today());
                });
        });
    }

    private function formatStorageGb(int $bytes): string
    {
        return number_format($bytes / (1024 ** 3), 2).' GB';
    }
}
