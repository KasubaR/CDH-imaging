@extends('layouts.app')

@section('title', 'Edit ' . $examinationType->name . ' — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Edit examination type')

@section('content')
<div class="canvas__inner admin-examination-types-page">
    <div class="bento-card form-card">
        <form method="post" action="{{ route('admin.examination-types.update', $examinationType) }}" class="form-stack">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name', $examinationType->name) }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">Code</label>
                <input type="text" id="code" name="code" class="form-input font-data-mono" value="{{ old('code', $examinationType->code) }}" required maxlength="20">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description (optional)</label>
                <textarea id="description" name="description" class="form-input" rows="3">{{ old('description', $examinationType->description) }}</textarea>
            </div>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Save changes</button>
                <a href="{{ route('admin.examination-types.index') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
