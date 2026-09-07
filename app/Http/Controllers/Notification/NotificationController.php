<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function show(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorize('view', $notification);

        $notification->markAsRead();

        return redirect()->route('department.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back();
    }
}
