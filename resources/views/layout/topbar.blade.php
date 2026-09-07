@php
    $user = auth()->user();
    $initial = strtoupper(substr($user?->name ?? 'U', 0, 1));
@endphp

<header class="topbar">
    <div class="topbar__bar">
        <div class="topbar__left">
            <button type="button" class="btn btn--ghost btn--icon topbar__menu-toggle" data-sidebar-toggle aria-expanded="false" aria-controls="sidebar">
                <span class="material-symbols-outlined">menu</span>
                <span class="sr-only">Open navigation menu</span>
            </button>

            <h1 class="topbar__title topbar__title--mobile">
                {{ $desktopTitle ?? $title ?? 'Chililabombwe District Hospital' }}
            </h1>
            <h1 class="topbar__title topbar__title--desktop">
                {{ $desktopTitle ?? $title ?? 'Chililabombwe District Hospital' }}
            </h1>
        </div>

        <div class="topbar__actions">
            <div class="topbar__notifications" data-notification-panel>
                <button
                    type="button"
                    class="btn btn--ghost btn--icon topbar__notification-toggle"
                    data-notification-toggle
                    aria-expanded="false"
                    aria-controls="topbar-notification-menu"
                    title="Notifications"
                >
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="sr-only">Notifications</span>
                    @if ($unreadCount > 0)
                        <span class="topbar__notification-badge" aria-label="{{ $unreadCount }} unread">
                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        </span>
                    @endif
                </button>

                <div
                    id="topbar-notification-menu"
                    class="topbar__notification-menu"
                    data-notification-menu
                    hidden
                    role="region"
                    aria-label="Notifications"
                >
                    <div class="topbar__notification-header">
                        <span class="topbar__notification-heading">Notifications</span>
                        @if ($unreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="topbar__notification-mark-all">Mark all as read</button>
                            </form>
                        @endif
                    </div>

                    @forelse ($notifications as $notification)
                        <a
                            href="{{ route('notifications.show', $notification) }}"
                            @class([
                                'topbar__notification-item',
                                'topbar__notification-item--unread' => $notification->read_at === null,
                            ])
                        >
                            <span class="topbar__notification-title">{{ $notification->title }}</span>
                            <span class="topbar__notification-body">{{ $notification->body }}</span>
                        </a>
                    @empty
                        <p class="topbar__notification-empty">No notifications</p>
                    @endforelse
                </div>
            </div>

            <div class="topbar__user-chip" title="{{ $user?->name ?? 'User' }}">
                <span class="topbar__avatar topbar__avatar--initials" aria-hidden="true">{{ $initial }}</span>
                <span class="topbar__user-name">
                    <span class="topbar__user-display">{{ $user?->name ?? 'User' }}</span>
                    @if ($user?->department)
                        <span class="topbar__user-meta">{{ $user->department->name }}</span>
                    @endif
                </span>
            </div>
        </div>
    </div>
</header>
