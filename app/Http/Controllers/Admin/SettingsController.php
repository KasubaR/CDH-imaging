<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\DashboardStatsService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings, DashboardStatsService $dashboardStats): View
    {
        $this->authorize('manage-settings');

        $values = $settings->all();
        $values['image_max_mb'] = intdiv($values['image_max_bytes'], 1024 * 1024);

        return view('admin.settings.edit', [
            'values' => $values,
            'storageUsed' => $dashboardStats->adminOverview()['storage_used'],
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $validated = $request->validated();

        $settings->set('image_retention_months', $validated['image_retention_months']);
        $settings->set('image_max_bytes', $validated['image_max_mb'] * 1024 * 1024);
        $settings->set('upload_session_ttl_hours', $validated['upload_session_ttl_hours']);
        $settings->set('backup_retention_days', $validated['backup_retention_days']);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Settings updated successfully.');
    }
}
