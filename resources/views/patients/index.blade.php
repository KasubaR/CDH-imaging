@extends('layouts.app')

@section('title', 'Patients — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Patients')

@section('content')
<div class="canvas__inner patients-page">
    <div class="patients-page__header page-header page-header--split">
        <p class="text-body-md page-header__lede">Register patients and view examination history.</p>

        @can('create', App\Models\Patient::class)
            <a href="{{ route('patients.create') }}" class="btn btn--primary">
                <span class="material-symbols-outlined">person_add</span>
                Register patient
            </a>
        @endcan
    </div>

    <form method="get" action="{{ route('patients.index') }}" class="patients-search">
        <input
            type="search"
            name="search"
            value="{{ $search }}"
            class="patients-search__input"
            placeholder="Search by name or NRC..."
            aria-label="Search patients"
        >
        <button type="submit" class="btn btn--secondary">Search</button>
        @if ($search !== '')
            <a href="{{ route('patients.index') }}" class="btn btn--ghost">Clear</a>
        @endif
    </form>

    <div class="bento-card patient-examinations-card">
        <div class="card-header">
            <h3 class="text-headline-sm text-on-surface">Registered patients</h3>
        </div>

        @if ($patients->isEmpty())
            <div class="patient-examinations-card__empty">
                <p class="text-body-md">No patients found.</p>
            </div>
        @else
            <div class="card-list-mobile card-list">
                @foreach ($patients as $patient)
                    <article class="card-list__item card-list__item--interactive">
                        <div class="card-list__row">
                            <div>
                                <div class="card-list__title">{{ $patient->patient_name }}</div>
                                <div class="card-list__meta font-data-mono">{{ $patient->nrc ?? 'No NRC' }}</div>
                            </div>
                            <span class="patient-count-badge">{{ $patient->examinations_count }} exams</span>
                        </div>
                        <div class="card-list__meta">Registered {{ $patient->created_at->format('Y-m-d') }}</div>
                        <a href="{{ route('patients.show', $patient) }}" class="btn btn--secondary btn--full">View patient</a>
                    </article>
                @endforeach
            </div>

            <div class="table-desktop audit-card__table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient name</th>
                            <th>NRC</th>
                            <th>Examinations</th>
                            <th>Registered</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($patients as $patient)
                            <tr class="data-table__row--interactive">
                                <td class="text-on-surface">{{ $patient->patient_name }}</td>
                                <td class="font-data-mono text-on-surface-variant">{{ $patient->nrc ?? '—' }}</td>
                                <td>{{ $patient->examinations_count }}</td>
                                <td class="font-data-mono text-on-surface-variant">{{ $patient->created_at->format('Y-m-d') }}</td>
                                <td>
                                    <a href="{{ route('patients.show', $patient) }}" class="btn btn--link">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($patients->hasPages())
                <div class="data-table__footer">
                    <span class="text-body-md text-on-surface-variant">
                        Showing {{ $patients->firstItem() }} to {{ $patients->lastItem() }} of {{ $patients->total() }}
                    </span>
                    <div class="pagination">
                        @if ($patients->onFirstPage())
                            <button type="button" class="pagination__btn" disabled>Prev</button>
                        @else
                            <a href="{{ $patients->previousPageUrl() }}" class="pagination__btn">Prev</a>
                        @endif

                        @if ($patients->hasMorePages())
                            <a href="{{ $patients->nextPageUrl() }}" class="pagination__btn">Next</a>
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
