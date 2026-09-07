@extends('layouts.app')

@section('title', 'Department Dashboard — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Department Dashboard')

@section('content')
<div class="canvas__inner dashboard-page">
    <div class="page-header">
        <p class="text-body-md page-header__lede">X-ray transfer activity for your department.</p>
    </div>

    <section class="department-dashboard" aria-labelledby="xray-transfer-heading">
        <h3 id="xray-transfer-heading" class="text-headline-sm text-on-surface department-dashboard__title">X-RAY TRANSFER</h3>
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
    </section>
</div>
@endsection
