@extends('layouts.app')

@section('title', 'Add account — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Add account')

@section('content')
@php
    $departmentOptions = $departments
        ->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])
        ->all();

    $roleOptions = [
        ['value' => 'staff', 'label' => 'Department account'],
        ['value' => 'admin', 'label' => 'System administrator'],
    ];
@endphp

<div class="canvas__inner admin-accounts-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Add account</h2>
    </div>

    <div class="bento-card form-card">
        <form method="post" action="{{ route('admin.accounts.store') }}" class="form-stack">
            @csrf

            <div class="form-group">
                <label class="form-label" for="name">Full name</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name') }}" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input font-data-mono" value="{{ old('username') }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" value="{{ old('email') }}" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Account type</label>
                <x-dropdown
                    name="role"
                    id="role"
                    :options="$roleOptions"
                    :value="old('role', 'staff')"
                    placeholder="Select account type..."
                    required
                />
            </div>

            <div class="form-group">
                <label class="form-label" for="department_id">Department</label>
                <x-dropdown
                    name="department_id"
                    id="department_id"
                    :options="$departmentOptions"
                    :value="old('department_id')"
                    placeholder="Select department..."
                />
                <p class="form-hint">Required for a department account. Ignored for a system administrator.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required minlength="8" autocomplete="new-password">
            </div>

            <fieldset class="form-group">
                <legend class="form-label">Permissions</legend>
                <p class="form-hint">Ignored for a system administrator — admins implicitly hold every permission.</p>
                @foreach ($permissions as $permission)
                    <label class="form-checkbox">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked(collect(old('permissions', []))->contains($permission->value))>
                        {{ $permission->label() }}
                    </label>
                @endforeach
            </fieldset>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Create account</button>
                <a href="{{ route('admin.accounts.index') }}" class="btn btn--ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
