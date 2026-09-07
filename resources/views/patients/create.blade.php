@extends('layouts.app')

@section('title', 'Register patient — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Register patient')

@section('content')
<div class="canvas__inner patients-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Register patient</h2>
        <p class="text-body-md page-header__lede">Enter patient details manually. This is not a full medical record.</p>
    </div>

    <div class="bento-card form-card">
        <form method="post" action="{{ route('patients.store') }}" class="form-stack">
            @csrf

            <div class="form-group">
                <label class="form-label" for="patient_name">Patient name</label>
                <input type="text" id="patient_name" name="patient_name" class="form-input" value="{{ old('patient_name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="nrc">NRC (optional)</label>
                <input type="text" id="nrc" name="nrc" class="form-input" value="{{ old('nrc') }}">
            </div>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Register patient</button>
                <a href="{{ route('patients.index') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
