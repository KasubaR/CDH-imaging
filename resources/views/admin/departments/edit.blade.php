@extends('layouts.app')

@section('title', 'Edit ' . $department->name . ' — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Edit department')

@section('content')
<div class="canvas__inner admin-departments-page">
    <div class="bento-card form-card">
        <form method="post" action="{{ route('admin.departments.update', $department) }}" class="form-stack">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name', $department->name) }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">Code</label>
                <input type="text" id="code" name="code" class="form-input font-data-mono" value="{{ old('code', $department->code) }}" required maxlength="20">
            </div>

            <label class="form-checkbox">
                <input type="checkbox" name="can_send" value="1" @checked(old('can_send', $department->can_send))>
                Can send transfers
            </label>

            <label class="form-checkbox">
                <input type="checkbox" name="can_receive" value="1" @checked(old('can_receive', $department->can_receive))>
                Can receive transfers
            </label>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Save changes</button>
                <a href="{{ route('admin.departments.index') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
