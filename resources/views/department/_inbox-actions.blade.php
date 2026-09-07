@php
    $status = $recipient->status;
    $transfer = $recipient->transfer;
    $canComplete = $status->order() >= \App\Enums\TransferRecipientStatus::Viewed->order() && ! $status->isTerminal();
    $rejectionReasons = \App\Enums\RejectionReason::options();
@endphp

<div class="inbox-row-actions">
    @if (in_array($status, [\App\Enums\TransferRecipientStatus::Pending, \App\Enums\TransferRecipientStatus::Delivered], true))
        <form method="POST" action="{{ route('transfers.acknowledge', $transfer) }}">
            @csrf
            <button type="submit" class="btn btn--secondary">Acknowledge</button>
        </form>
        <form method="POST" action="{{ route('transfers.reject', $transfer) }}" class="inbox-reject-form">
            @csrf
            <x-dropdown
                name="reason"
                :options="$rejectionReasons"
                placeholder="Reject reason"
                variant="chip"
                required
                aria-label="Rejection reason"
            />
            <button type="submit" class="btn btn--ghost">Reject</button>
        </form>
    @elseif (! $status->isTerminal())
        <a href="{{ route('examinations.show', $transfer->examination) }}" class="inbox-action-btn">
            Open <span class="material-symbols-outlined">chevron_right</span>
        </a>

        @if ($canComplete)
            <form method="POST" action="{{ route('transfers.complete', $transfer) }}">
                @csrf
                <button type="submit" class="btn btn--secondary">Mark complete</button>
            </form>
        @endif

        <form method="POST" action="{{ route('transfers.reject', $transfer) }}" class="inbox-reject-form">
            @csrf
            <x-dropdown
                name="reason"
                :options="$rejectionReasons"
                placeholder="Reject reason"
                variant="chip"
                required
                aria-label="Rejection reason"
            />
            <button type="submit" class="btn btn--ghost">Reject</button>
        </form>
    @endif
</div>
