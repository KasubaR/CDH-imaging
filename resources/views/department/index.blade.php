@extends('layouts.app')

@section('title', 'Department Inbox — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Chililabombwe District Hospital')

@section('content')
<div class="canvas__inner canvas__inner--full-height inbox-page">
    <div class="inbox-page__header-row page-header page-header--split">
        <div>
            <h2 class="text-headline-lg text-on-surface">Department Inbox</h2>
            <p class="text-body-md page-header__lede">Manage incoming radiological transfers and diagnostic requests.</p>
        </div>
    </div>

    <section class="inbox-dashboard" aria-labelledby="xray-transfer-heading">
        <h3 id="xray-transfer-heading" class="text-headline-sm text-on-surface inbox-dashboard__title">X-RAY TRANSFER</h3>
        <div class="kpi-grid kpi-grid--five">
            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h4 class="text-label-md text-on-surface-variant">New</h4>
                    <div class="kpi-card__icon kpi-card__icon--primary">
                        <span class="material-symbols-outlined">mark_email_unread</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['new'] }}</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h4 class="text-label-md text-on-surface-variant">Received today</h4>
                    <div class="kpi-card__icon kpi-card__icon--secondary">
                        <span class="material-symbols-outlined">inbox</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['received_today'] }}</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h4 class="text-label-md text-on-surface-variant">Sent today</h4>
                    <div class="kpi-card__icon kpi-card__icon--neutral">
                        <span class="material-symbols-outlined">outbox</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['sent_today'] }}</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h4 class="text-label-md text-on-surface-variant">Pending</h4>
                    <div class="kpi-card__icon kpi-card__icon--error">
                        <span class="material-symbols-outlined">pending_actions</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['pending'] }}</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h4 class="text-label-md text-on-surface-variant">Completed</h4>
                    <div class="kpi-card__icon kpi-card__icon--primary">
                        <span class="material-symbols-outlined">task_alt</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['completed'] }}</span>
                </div>
            </div>
        </div>

        @if (! array_filter($filters))
            <div class="bento-card inbox-recent-card">
                <div class="card-header">
                    <h3 class="text-headline-sm text-on-surface">Recent Transfers</h3>
                </div>
                @if ($recentTransfers->isEmpty())
                    <div class="inbox-list-card__empty">
                        <p class="text-body-md text-on-surface-variant">No recent transfers.</p>
                    </div>
                @else
                    <div class="card-list-mobile card-list">
                        @foreach ($recentTransfers as $recipient)
                            @php $examination = $recipient->transfer->examination; @endphp
                            <article class="card-list__item">
                                <div class="card-list__row">
                                    <div>
                                        <div class="inbox-patient-name text-on-surface">{{ $examination->patient?->patient_name ?? 'Unknown patient' }}</div>
                                        <div class="card-list__meta">{{ $examination->examinationType?->name }} · {{ $examination->body_part }}</div>
                                    </div>
                                    <x-status-badge :variant="$recipient->status->badgeVariant()">{{ $recipient->status->inboxLabel() }}</x-status-badge>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <div class="table-desktop inbox-table-card__scroll">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Examination</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentTransfers as $recipient)
                                    @php $examination = $recipient->transfer->examination; @endphp
                                    <tr>
                                        <td class="text-on-surface">{{ $examination->patient?->patient_name ?? 'Unknown patient' }}</td>
                                        <td>{{ $examination->examinationType?->name }} ({{ $examination->body_part }})</td>
                                        <td><x-status-badge :variant="$recipient->status->badgeVariant()">{{ $recipient->status->inboxLabel() }}</x-status-badge></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </section>

    <form method="get" action="{{ route('department.index') }}" class="filter-chips" role="group" aria-label="Filters">
        <input
            type="search"
            name="patient"
            value="{{ $filters['patient'] }}"
            class="patients-search__input"
            placeholder="Search patient..."
            aria-label="Patient"
        >

        <x-dropdown
            name="department_id"
            id="inbox-department"
            variant="chip"
            icon="account_tree"
            :options="$departments->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])->all()"
            value="{{ $filters['department_id'] }}"
            placeholder="All Departments"
            aria-label="Department filter"
        />

        <x-dropdown
            name="sender_department_id"
            id="inbox-sender"
            variant="chip"
            icon="outgoing_mail"
            :options="$departments->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])->all()"
            value="{{ $filters['sender_department_id'] }}"
            placeholder="All Senders"
            aria-label="Sender filter"
        />

        <x-dropdown
            name="examination_type_id"
            id="inbox-examination-type"
            variant="chip"
            icon="radiology"
            :options="$examinationTypes->map(fn ($type) => ['value' => (string) $type->id, 'label' => $type->name])->all()"
            value="{{ $filters['examination_type_id'] }}"
            placeholder="All Examinations"
            aria-label="Examination filter"
        />

        <x-dropdown
            name="status"
            id="inbox-status"
            variant="chip"
            icon="filter_list"
            :options="$statusOptions->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
            value="{{ $filters['status'] }}"
            placeholder="All Statuses"
            aria-label="Status filter"
        />

        <x-dropdown
            name="date_range"
            id="inbox-date-range"
            variant="chip"
            icon="calendar_today"
            :options="collect($dateRanges)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
            value="{{ $filters['date_range'] }}"
            placeholder="Any Date"
            aria-label="Date range"
        />

        <button type="submit" class="btn btn--primary">Apply filters</button>
        @if (array_filter($filters))
            <a href="{{ route('department.index') }}" class="btn btn--ghost">Clear</a>
        @endif
    </form>

    <div class="bento-card inbox-list-card">
        <div class="card-header">
            <h3 class="text-headline-sm text-on-surface">Incoming X-Rays</h3>
        </div>

        @if ($recipients->isEmpty())
            <div class="inbox-list-card__empty">
                <p class="text-body-md text-on-surface-variant">No transfers match these filters.</p>
            </div>
        @else
            <div class="card-list-mobile card-list">
                @foreach ($recipients as $recipient)
                    @php $examination = $recipient->transfer->examination; @endphp
                    <article class="card-list__item card-list__item--interactive @if($recipient->status->isTerminal()) card-list__item--muted @endif">
                        <div class="card-list__row">
                            <div>
                                <div class="inbox-patient-name text-on-surface">{{ $examination->patient?->patient_name ?? 'Unknown patient' }}</div>
                                <div class="card-list__meta">{{ $examination->examinationType?->name }} · {{ $examination->body_part }}</div>
                            </div>
                            <x-status-badge :variant="$recipient->status->badgeVariant()">{{ $recipient->status->inboxLabel() }}</x-status-badge>
                        </div>
                        <div class="card-list__meta">{{ $recipient->transfer->fromDepartment?->name }} · {{ optional($recipient->transfer->sent_at)->format('d/m/Y H:i') }}</div>
                        @include('department._inbox-actions', ['recipient' => $recipient])
                    </article>
                @endforeach
            </div>

            <div class="table-desktop inbox-table-card__scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Examination</th>
                            <th>From</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="inbox-table-card__action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recipients as $recipient)
                            @php $examination = $recipient->transfer->examination; @endphp
                            <tr class="data-table__row--interactive @if($recipient->status->isTerminal()) data-table__row--muted @endif">
                                <td class="text-on-surface">{{ $examination->patient?->patient_name ?? 'Unknown patient' }}</td>
                                <td>
                                    <div class="inbox-modality">
                                        <span class="material-symbols-outlined text-outline">radiology</span>
                                        {{ $examination->examinationType?->name }} ({{ $examination->body_part }})
                                    </div>
                                </td>
                                <td>{{ $recipient->transfer->fromDepartment?->name }}</td>
                                <td class="font-data-mono">{{ optional($recipient->transfer->sent_at)->format('d/m/Y') }}</td>
                                <td><x-status-badge :variant="$recipient->status->badgeVariant()">{{ $recipient->status->inboxLabel() }}</x-status-badge></td>
                                <td>
                                    @include('department._inbox-actions', ['recipient' => $recipient])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="data-table__footer">
                <span class="text-body-md text-on-surface-variant">
                    Showing {{ $recipients->firstItem() }} to {{ $recipients->lastItem() }} of {{ $recipients->total() }} entries
                </span>
                <div class="pagination">
                    @if ($recipients->onFirstPage())
                        <button type="button" class="pagination__btn" disabled>Prev</button>
                    @else
                        <a href="{{ $recipients->previousPageUrl() }}" class="pagination__btn">Prev</a>
                    @endif

                    @if ($recipients->hasMorePages())
                        <a href="{{ $recipients->nextPageUrl() }}" class="pagination__btn">Next</a>
                    @else
                        <button type="button" class="pagination__btn" disabled>Next</button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
