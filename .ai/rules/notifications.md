---
paths:
  - 'app/Services/NotificationService.php,app/Models/Notification.php,app/Http/Controllers/Notification/**,app/View/Components/Layout/Topbar.php,resources/views/layout/topbar.blade.php'
---

# Notifications

## In-app only, custom per-user table, fan-out on send
Use the Phase 3 `notifications` table (`user_id`, `type`, `title`, `body`, `data`, `read_at`) — not Laravel's UUID database notification channel, mail, SMS, or WhatsApp. `User::notifications()` is a custom `HasMany` to `App\Models\Notification`; leave the `Notifiable` trait unused for now.

`TransferLifecycleService::send()` / `forward()` call `notifyTransferReceived()` inside the send transaction. Fan-out targets every **active** user in each destination department, excluding `sent_by`. Reject notifies the sending department (`notifyTransferRejected`); recall notifies the destination department (`notifyTransferRecalled`). Copy is denormalized so the topbar still reads correctly if related rows change.

Opening a notification (`notifications.show`) marks it read and redirects to `department.index`. The topbar class component loads the latest 15 rows + unread count — do not query notifications from Blade.
