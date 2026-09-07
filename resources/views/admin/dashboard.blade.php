@extends('layouts.app')

@section('title', 'Administrator Dashboard — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'System Dashboard')

@section('content')
<div class="canvas__inner dashboard-page">
    <div class="page-header page-header--split">
        <div>
            <h2 class="text-headline-lg text-on-surface">Overview</h2>
            <p class="text-body-md page-header__lede">Hospital imaging transfer activity and storage.</p>
        </div>
    </div>

    <div class="bento-grid">
        <div class="bento-grid__kpi-row kpi-grid kpi-grid--five">
            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h3 class="text-label-md text-on-surface-variant">Departments</h3>
                    <div class="kpi-card__icon kpi-card__icon--primary">
                        <span class="material-symbols-outlined">account_tree</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['departments'] }}</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h3 class="text-label-md text-on-surface-variant">Images today</h3>
                    <div class="kpi-card__icon kpi-card__icon--secondary">
                        <span class="material-symbols-outlined">image</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['images_today'] }}</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h3 class="text-label-md text-on-surface-variant">Transfers today</h3>
                    <div class="kpi-card__icon kpi-card__icon--neutral">
                        <span class="material-symbols-outlined">sync_alt</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['transfers_today'] }}</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h3 class="text-label-md text-on-surface-variant">Failed transfers</h3>
                    <div class="kpi-card__icon kpi-card__icon--error">
                        <span class="material-symbols-outlined">error</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['failed_transfers'] }}</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-card__header">
                    <h3 class="text-label-md text-on-surface-variant">Storage used</h3>
                    <div class="kpi-card__icon kpi-card__icon--primary">
                        <span class="material-symbols-outlined">hard_drive</span>
                    </div>
                </div>
                <div class="kpi-card__value-row">
                    <span class="text-headline-lg text-on-surface">{{ $stats['storage_used'] }}</span>
                </div>
            </div>
        </div>

        <div class="bento-card activity-card">
            <div class="card-header">
                <h3 class="text-headline-sm text-on-surface">Activity</h3>
            </div>

            @if ($activity->isEmpty())
                <div class="inbox-list-card__empty">
                    <p class="text-body-md text-on-surface-variant">No transfers sent today.</p>
                </div>
            @else
                <ul class="activity-list">
                    @foreach ($activity as $row)
                        <li class="activity-list__item">
                            <span class="activity-list__dept text-on-surface">{{ $row->department->name }}</span>
                            <span class="activity-list__count font-data-mono text-on-surface-variant">
                                {{ $row->count }} {{ $row->count === 1 ? 'transfer' : 'transfers' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
