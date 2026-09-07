@extends('layouts.app')

@section('title', 'New examination — ' . $patient->patient_name)
@section('topbar_desktop_title', 'New examination')

@section('content')
@php
    $examinationTypeOptions = $examinationTypes
        ->map(fn ($type) => ['value' => (string) $type->id, 'label' => $type->name])
        ->all();

    $departmentOptions = $departments
        ->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])
        ->all();
@endphp

<div class="canvas__inner patients-page">
    <div class="page-header">
        <p class="text-body-md page-header__lede">Record an examination for {{ $patient->patient_name }}.</p>
    </div>

    <div class="bento-card form-card">
        <form method="post" action="{{ route('patients.examinations.store', $patient) }}" class="form-stack">
            @csrf

            <div class="form-group">
                <label class="form-label" for="examination_type_id">Examination type</label>
                <x-dropdown
                    name="examination_type_id"
                    id="examination_type_id"
                    :options="$examinationTypeOptions"
                    :value="old('examination_type_id')"
                    placeholder="Select type..."
                    required
                />
            </div>

            <div class="form-group">
                <label class="form-label" for="body_part">Body part</label>
                <input type="text" id="body_part" name="body_part" class="form-input" value="{{ old('body_part') }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description (optional)</label>
                <textarea id="description" name="description" class="form-input" rows="3">{{ old('description') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="date_taken">Date taken</label>
                <input type="date" id="date_taken" name="date_taken" class="form-input" value="{{ old('date_taken', now()->format('Y-m-d')) }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="time_taken">Time taken</label>
                <input type="time" id="time_taken" name="time_taken" class="form-input" value="{{ old('time_taken', now()->format('H:i')) }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="referring_department_id">Referring department</label>
                <x-dropdown
                    name="referring_department_id"
                    id="referring_department_id"
                    :options="$departmentOptions"
                    :value="old('referring_department_id', $defaultDepartmentId)"
                    placeholder="Select department..."
                    required
                />
            </div>

            <div class="form-group">
                <label class="form-label" for="referring_clinician">Referring clinician (optional)</label>
                <input type="text" id="referring_clinician" name="referring_clinician" class="form-input" value="{{ old('referring_clinician') }}">
            </div>

            <div class="form-group">
                <label class="form-label" for="radiographer">Radiographer (optional)</label>
                <input type="text" id="radiographer" name="radiographer" class="form-input" value="{{ old('radiographer') }}">
            </div>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Save examination</button>
                <a href="{{ route('patients.show', $patient) }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
