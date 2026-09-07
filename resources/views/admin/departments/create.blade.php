@extends('layouts.app')

@section('title', 'Add department — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Add department')

@section('content')
<div class="canvas__inner admin-departments-page">
    <div class="bento-card form-card">
        <form method="post" action="{{ route('admin.departments.store') }}" class="form-stack">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">Code</label>
                <input type="text" id="code" name="code" class="form-input font-data-mono" value="{{ old('code') }}" required maxlength="20">
            </div>

            <label class="form-checkbox">
                <input type="checkbox" name="can_send" value="1" @checked(old('can_send', true))>
                Can send transfers
            </label>

            <label class="form-checkbox">
                <input type="checkbox" name="can_receive" value="1" @checked(old('can_receive', true))>
                Can receive transfers
            </label>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Create department</button>
                <a href="{{ route('admin.departments.index') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
