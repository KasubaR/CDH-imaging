@extends('layouts.app')

@section('title', 'Accounts — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Accounts')

@section('content')
@php
    $roleFilterOptions = [
        ['value' => 'admin', 'label' => 'System administrator'],
        ['value' => 'moic', 'label' => 'Medical Officer in Charge'],
        ['value' => 'staff', 'label' => 'Department account'],
    ];

    $statusFilterOptions = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive'],
    ];
@endphp

<div class="canvas__inner admin-accounts-page">
    <div class="admin-accounts-page__header page-header page-header--split">
        <p class="text-body-md page-header__lede">Provision and manage staff and administrator accounts.</p>

        <a href="{{ route('admin.accounts.create') }}" class="btn btn--primary">
            <span class="material-symbols-outlined">person_add</span>
            Add account
        </a>
    </div>

    <x-filter-drawer>
    <form method="get" action="{{ route('admin.accounts.index') }}" class="filter-chips" role="group" aria-label="Filters">
        <input
            type="search"
            name="search"
            value="{{ $filters['search'] }}"
            class="patients-search__input"
            placeholder="Search name, username or email..."
            aria-label="Search accounts"
        >

        <x-dropdown
            name="department_id"
            id="accounts-department"
            variant="chip"
            icon="account_tree"
            :options="$departments->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])->all()"
            value="{{ $filters['department_id'] ?: '' }}"
            placeholder="All Departments"
            aria-label="Department filter"
        />

        <x-dropdown
            name="role"
            id="accounts-role"
            variant="chip"
            icon="shield_person"
            :options="$roleFilterOptions"
            value="{{ $filters['role'] }}"
            placeholder="All Roles"
            aria-label="Role filter"
        />

        <x-dropdown
            name="status"
            id="accounts-status"
            variant="chip"
            icon="filter_list"
            :options="$statusFilterOptions"
            value="{{ $filters['status'] }}"
            placeholder="All Statuses"
            aria-label="Status filter"
        />

        <button type="submit" class="btn btn--secondary">Filter</button>
        <a href="{{ route('admin.accounts.index') }}" class="btn btn--ghost">Clear</a>
    </form>
    </x-filter-drawer>

    <div class="bento-card admin-accounts-table">
        @if ($accounts->isEmpty())
            <div class="patient-examinations-card__empty">
                <p class="text-body-md">No accounts found.</p>
            </div>
        @else
            <div class="card-list-mobile card-list">
                @foreach ($accounts as $account)
                    <article @class(['card-list__item', 'admin-accounts-table__status--inactive' => ! $account->is_active])>
                        <div class="card-list__row">
                            <div>
                                <div class="card-list__title">{{ $account->name }}</div>
                                <div class="card-list__meta font-data-mono">{{ $account->username }} · {{ $account->department?->name ?? 'No department' }}</div>
                            </div>
                            @if ($account->is_active)
                                <x-status-badge variant="completed">Active</x-status-badge>
                            @else
                                <x-status-badge variant="sent">Inactive</x-status-badge>
                            @endif
                        </div>
                        <div class="card-list__meta">{{ \App\Enums\UserRole::from($account->role)->label() }}</div>
                        <div class="card-list__actions">
                            <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn--secondary">Edit</a>
                            @if ($account->id !== auth()->id())
                                @if ($account->is_active)
                                    <form method="post" action="{{ route('admin.accounts.deactivate', $account) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn--ghost">Deactivate</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('admin.accounts.activate', $account) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn--ghost">Activate</button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="table-desktop audit-card__table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr @class(['admin-accounts-table__status--inactive' => ! $account->is_active])>
                                <td class="text-on-surface">{{ $account->name }}</td>
                                <td class="font-data-mono">{{ $account->username }}</td>
                                <td>{{ $account->department?->name ?? '—' }}</td>
                                <td>{{ \App\Enums\UserRole::from($account->role)->label() }}</td>
                                <td>
                                    @if ($account->is_active)
                                        <x-status-badge variant="completed">Active</x-status-badge>
                                    @else
                                        <x-status-badge variant="sent">Inactive</x-status-badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-accounts-table__actions">
                                        <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn--link">Edit</a>
                                        @if ($account->id !== auth()->id())
                                            @if ($account->is_active)
                                                <form method="post" action="{{ route('admin.accounts.deactivate', $account) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn--link">Deactivate</button>
                                                </form>
                                            @else
                                                <form method="post" action="{{ route('admin.accounts.activate', $account) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn--link">Activate</button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($accounts->hasPages())
                <div class="data-table__footer">
                    <span class="text-body-md text-on-surface-variant">
                        Showing {{ $accounts->firstItem() }} to {{ $accounts->lastItem() }} of {{ $accounts->total() }}
                    </span>
                    <div class="pagination">
                        @if ($accounts->onFirstPage())
                            <button type="button" class="pagination__btn" disabled>Prev</button>
                        @else
                            <a href="{{ $accounts->previousPageUrl() }}" class="pagination__btn">Prev</a>
                        @endif

                        @if ($accounts->hasMorePages())
                            <a href="{{ $accounts->nextPageUrl() }}" class="pagination__btn">Next</a>
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
