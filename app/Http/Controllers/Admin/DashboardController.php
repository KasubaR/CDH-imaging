<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $dashboardStats)
    {
        //
    }

    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => $this->dashboardStats->adminOverview(),
            'activity' => $this->dashboardStats->adminActivityToday(),
        ]);
    }
}
