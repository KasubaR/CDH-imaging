@extends('layouts.app')

@section('title', $patient->patient_name . ' — Chililabombwe District Hospital')
@section('topbar_desktop_title', $patient->patient_name)

@section('content')
<div class="canvas__inner patients-page">
    <div class="patient-header">
        <div class="patient-header__inner">
            <div class="patient-header__profile">
                <div class="patient-header__avatar">
                    <span class="material-symbols-outlined">patient_list</span>
                </div>
                <div>
                    <div class="patient-header__name-row">
                        <h2 class="text-headline-sm text-on-surface">{{ $patient->patient_name }}</h2>
                        @if ($patient->nrc)
                            <span class="patient-id-badge font-data-mono">NRC: {{ $patient->nrc }}</span>
                        @endif
                    </div>
                    <div class="patient-profile__meta text-body-md">
                        <span>Registered {{ $patient->created_at->format('Y-m-d') }}</span>
                        <span>{{ $patient->examinations->count() }} examination(s)</span>
                    </div>
                </div>
            </div>
            <div class="patient-header__actions">
                <a href="{{ route('patients.index') }}" class="btn btn--secondary">
                    <span class="material-symbols-outlined">arrow_back</span>
                    All patients
                </a>
                @can('create', App\Models\Examination::class)
                    <a href="{{ route('patients.examinations.create', $patient) }}" class="btn btn--primary">
                        <span class="material-symbols-outlined">add_circle</span>
                        New examination
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="bento-card patient-examinations-card">
        <div class="card-header">
            <h3 class="text-headline-sm text-on-surface">Examination history</h3>
        </div>

        @if ($patient->examinations->isEmpty())
            <div class="patient-examinations-card__empty">
                <p class="text-body-md">No examinations recorded yet.</p>
            </div>
        @else
            <div class="card-list-mobile card-list">
                @foreach ($patient->examinations as $examination)
                    <article class="card-list__item card-list__item--interactive">
                        <div class="card-list__row">
                            <div>
                                <div class="card-list__title">{{ $examination->examinationType->name }}</div>
                                <div class="card-list__meta">{{ $examination->body_part }}</div>
                            </div>
                            <span class="font-data-mono card-list__meta">{{ $examination->date_taken->format('Y-m-d') }}</span>
                        </div>
                        <div class="card-list__meta">{{ $examination->referringDepartment->name }}</div>
                        <a href="{{ route('examinations.show', $examination) }}" class="btn btn--secondary btn--full">View examination</a>
                    </article>
                @endforeach
            </div>

            <div class="table-desktop audit-card__table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Body part</th>
                            <th>Referring dept</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($patient->examinations as $examination)
                            <tr class="data-table__row--interactive">
                                <td class="font-data-mono">{{ $examination->date_taken->format('Y-m-d') }} {{ $examination->time_taken }}</td>
                                <td>{{ $examination->examinationType->name }}</td>
                                <td>{{ $examination->body_part }}</td>
                                <td>{{ $examination->referringDepartment->name }}</td>
                                <td>
                                    <a href="{{ route('examinations.show', $examination) }}" class="btn btn--link">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
