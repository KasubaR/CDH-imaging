<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $dashboardStats)
    {
        //
    }

    public function index(Request $request): View
    {
        $departmentId = $request->user()?->department_id;

        $stats = $departmentId !== null
            ? $this->dashboardStats->departmentTransferStats($departmentId)
            : [
                'new' => 0,
                'received_today' => 0,
                'sent_today' => 0,
                'pending' => 0,
                'completed' => 0,
            ];

        $recentTransfers = $departmentId !== null
            ? $this->dashboardStats->departmentRecentTransfers($departmentId)
            : collect();

        return view('department.dashboard', [
            'stats' => $stats,
            'recentTransfers' => $recentTransfers,
        ]);
    }
}
