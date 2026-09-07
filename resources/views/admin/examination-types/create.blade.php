@extends('layouts.app')

@section('title', 'Add examination type — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Add examination type')

@section('content')
<div class="canvas__inner admin-examination-types-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Add examination type</h2>
    </div>

    <div class="bento-card form-card">
        <form method="post" action="{{ route('admin.examination-types.store') }}" class="form-stack">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">Code</label>
                <input type="text" id="code" name="code" class="form-input font-data-mono" value="{{ old('code') }}" required maxlength="20">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description (optional)</label>
                <textarea id="description" name="description" class="form-input" rows="3">{{ old('description') }}</textarea>
            </div>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Create type</button>
                <a href="{{ route('admin.examination-types.index') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
