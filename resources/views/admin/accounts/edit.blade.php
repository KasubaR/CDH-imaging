@extends('layouts.app')

@section('title', 'Edit ' . $account->name . ' — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Edit account')

@section('content')
@php
    $departmentOptions = $departments
        ->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])
        ->all();

    $roleOptions = [
        ['value' => 'staff', 'label' => 'Department account'],
        ['value' => 'admin', 'label' => 'System administrator'],
    ];

    $isSelf = $account->id === auth()->id();
    $initials = collect(preg_split('/\s+/', trim($account->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="canvas__inner admin-accounts-page">
    <div class="page-header page-header--split admin-accounts-page__header">
        <div>
            <a href="{{ route('admin.accounts.index') }}" class="admin-account-edit__back">
                <span class="material-symbols-outlined">arrow_back</span>
                Accounts
            </a>
            <h2 class="text-headline-lg text-on-surface">Edit account</h2>
            <p class="text-body-md page-header__lede">
                Update profile, access, and permissions for
                <span class="font-data-mono">{{ $account->username }}</span>.
            </p>
        </div>
        <x-status-badge :variant="$account->is_active ? 'completed' : 'rejected'">
            {{ $account->is_active ? 'Active' : 'Inactive' }}
        </x-status-badge>
    </div>

    <div class="admin-accounts-page__edit-layout">
        <div class="bento-card admin-account-edit__main">
            <div class="admin-account-edit__identity">
                <div class="admin-account-edit__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : '?' }}</div>
                <div class="admin-account-edit__identity-text">
                    <p class="admin-account-edit__identity-name text-on-surface">{{ $account->name }}</p>
                    <p class="admin-account-edit__identity-meta font-data-mono text-on-surface-variant">
                        {{ $account->username }} · {{ $account->email }}
                    </p>
                </div>
                <span class="admin-account-edit__role-pill">
                    {{ $account->isAdmin() ? 'System administrator' : 'Department account' }}
                </span>
            </div>

            <form method="post" action="{{ route('admin.accounts.update', $account) }}" class="form-stack admin-account-edit__form">
                @csrf
                @method('PUT')

                <section class="admin-account-edit__section" aria-labelledby="account-profile-heading">
                    <div class="admin-account-edit__section-head">
                        <h3 id="account-profile-heading" class="admin-account-edit__section-title">Profile</h3>
                        <p class="admin-account-edit__section-lede">Name and sign-in details.</p>
                    </div>

                    <div class="admin-account-edit__fields">
                        <div class="form-group">
                            <label class="form-label" for="name">Full name</label>
                            <input type="text" id="name" name="name" class="form-input" value="{{ old('name', $account->name) }}" required autofocus>
                        </div>

                        <div class="admin-account-edit__field-row">
                            <div class="form-group">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-input font-data-mono" value="{{ old('username', $account->username) }}" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-input" value="{{ old('email', $account->email) }}" required>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-account-edit__section" aria-labelledby="account-access-heading">
                    <div class="admin-account-edit__section-head">
                        <h3 id="account-access-heading" class="admin-account-edit__section-title">Access</h3>
                        <p class="admin-account-edit__section-lede">Role and home department.</p>
                    </div>

                    <div class="admin-account-edit__fields">
                        <div class="form-group">
                            <label class="form-label" for="role">Account type</label>
                            <x-dropdown
                                name="role"
                                id="role"
                                :options="$roleOptions"
                                :value="old('role', $account->role)"
                                placeholder="Select account type..."
                                required
                            />
                            @if ($isSelf)
                                <p class="form-hint">You cannot remove your own admin access.</p>
                            @endif
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="department_id">Department</label>
                            <x-dropdown
                                name="department_id"
                                id="department_id"
                                :options="$departmentOptions"
                                :value="old('department_id', $account->department_id)"
                                placeholder="Select department..."
                            />
                            <p class="form-hint">Required for department accounts. Optional for administrators.</p>
                        </div>
                    </div>
                </section>

                <section class="admin-account-edit__section" aria-labelledby="account-permissions-heading">
                    <div class="admin-account-edit__section-head">
                        <h3 id="account-permissions-heading" class="admin-account-edit__section-title">Permissions</h3>
                        <p class="admin-account-edit__section-lede">
                            Applies to department accounts only. Administrators already hold every permission.
                        </p>
                    </div>

                    <div class="admin-account-edit__permission-grid" role="group" aria-labelledby="account-permissions-heading">
                        @foreach ($permissions as $permission)
                            <label class="admin-account-edit__permission">
                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission->value }}"
                                    @checked(collect(old('permissions', $assignedPermissionSlugs))->contains($permission->value))
                                >
                                <span class="admin-account-edit__permission-label">{{ $permission->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <div class="form-stack__submit admin-account-edit__actions">
                    <button type="submit" class="btn btn--primary">Save changes</button>
                    <a href="{{ route('admin.accounts.index') }}" class="btn btn--ghost">Cancel</a>
                </div>
            </form>
        </div>

        <aside class="bento-card admin-account-edit__password" aria-labelledby="reset-password-heading">
            <div class="admin-account-edit__password-head">
                <span class="material-symbols-outlined admin-account-edit__password-icon" aria-hidden="true">lock_reset</span>
                <div>
                    <h3 id="reset-password-heading" class="text-headline-sm text-on-surface">Reset password</h3>
                    <p class="admin-account-edit__section-lede">Sets a new password immediately. The current one stops working.</p>
                </div>
            </div>

            <form method="post" action="{{ route('admin.accounts.reset-password', $account) }}" class="form-stack">
                @csrf
                @method('PATCH')

                <div class="form-group">
                    <label class="form-label" for="reset_password">New password</label>
                    <input type="password" id="reset_password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="reset_password_confirmation">Confirm password</label>
                    <input type="password" id="reset_password_confirmation" name="password_confirmation" class="form-input" required minlength="8" autocomplete="new-password">
                </div>

                <div class="form-stack__submit">
                    <button type="submit" class="btn btn--secondary">Reset password</button>
                </div>
            </form>
        </aside>
    </div>
</div>
@endsection
