@extends('layouts.app')

@section('title', 'Audit Log — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Audit Log')

@section('content')
<div class="canvas__inner admin-audit-log-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Audit Log</h2>
        <p class="text-body-md page-header__lede">Every recorded upload, view, download, transfer and deletion event — read-only.</p>
    </div>

    <form method="get" action="{{ route('admin.audit-logs.index') }}" class="filter-chips" role="group" aria-label="Filters">
        <input
            type="search"
            name="search"
            value="{{ $filters['search'] }}"
            class="patients-search__input"
            placeholder="Search by actor name or username..."
            aria-label="Search actor"
        >

        <x-dropdown
            name="department_id"
            id="audit-department"
            variant="chip"
            icon="account_tree"
            :options="$departments->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])->all()"
            value="{{ $filters['department_id'] ?: '' }}"
            placeholder="All Departments"
            aria-label="Department filter"
        />

        <x-dropdown
            name="action"
            id="audit-action"
            variant="chip"
            icon="history"
            :options="$actionOptions->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
            value="{{ $filters['action'] }}"
            placeholder="All Actions"
            aria-label="Action filter"
        />

        <x-dropdown
            name="date_range"
            id="audit-date-range"
            variant="chip"
            icon="calendar_today"
            :options="collect($dateRanges)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all()"
            value="{{ $filters['date_range'] }}"
            placeholder="All Time"
            aria-label="Date range filter"
        />

        <button type="submit" class="btn btn--secondary">Filter</button>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn--ghost">Clear</a>
    </form>

    <div class="bento-card admin-audit-log-table">
        @if ($logs->isEmpty())
            <div class="patient-examinations-card__empty">
                <p class="text-body-md">No audit events found.</p>
            </div>
        @else
            <div class="card-list-mobile card-list">
                @foreach ($logs as $log)
                    <article class="card-list__item">
                        <div class="card-list__row">
                            <div>
                                <div class="card-list__title">{{ $log->account?->name ?? 'System' }} {{ $log->action->label() }}</div>
                                <div class="card-list__meta font-data-mono">{{ $log->department?->name ?? 'No department' }} · {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                            </div>
                        </div>
                        <div class="card-list__meta">{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
                    </article>
                @endforeach
            </div>

            <div class="table-desktop audit-card__table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Actor</th>
                            <th>Department</th>
                            <th>Action</th>
                            <th>Subject</th>
                            <th>IP address</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="font-data-mono text-on-surface-variant">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="text-on-surface">{{ $log->account?->name ?? 'System' }}</td>
                                <td>{{ $log->department?->name ?? '—' }}</td>
                                <td>{{ $log->action->label() }}</td>
                                <td class="font-data-mono text-on-surface-variant">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                                <td class="font-data-mono text-on-surface-variant">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="data-table__footer">
                    <span class="text-body-md text-on-surface-variant">
                        Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }}
                    </span>
                    <div class="pagination">
                        @if ($logs->onFirstPage())
                            <button type="button" class="pagination__btn" disabled>Prev</button>
                        @else
                            <a href="{{ $logs->previousPageUrl() }}" class="pagination__btn">Prev</a>
                        @endif

                        @if ($logs->hasMorePages())
                            <a href="{{ $logs->nextPageUrl() }}" class="pagination__btn">Next</a>
                        @else
                            <button type="button" class="pagination__btn" disabled>Next</button>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
