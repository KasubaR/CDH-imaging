<?php

namespace App\View\Components\Layout;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\Component;

class Topbar extends Component
{
    /** @var Collection<int, Notification> */
    public Collection $notifications;

    public int $unreadCount;

    public function __construct(
        public ?string $title = null,
        public ?string $desktopTitle = null,
    ) {
        $user = auth()->user();

        if ($user === null) {
            $this->notifications = new Collection;
            $this->unreadCount = 0;

            return;
        }

        $this->notifications = Notification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(15)
            ->get();

        $this->unreadCount = Notification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->count();
    }

    public function render(): View
    {
        return view('layout.topbar');
    }
}
