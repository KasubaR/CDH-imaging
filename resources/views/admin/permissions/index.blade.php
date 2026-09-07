@extends('layouts.app')

@section('title', 'Permissions — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Permissions')

@section('content')
<div class="canvas__inner admin-permissions-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Permissions</h2>
        <p class="text-body-md page-header__lede">Who holds which permission, at a glance. Edit an account to change its permissions.</p>
    </div>

    <form method="get" action="{{ route('admin.permissions.index') }}" class="filter-chips" role="group" aria-label="Filters">
        <input
            type="search"
            name="search"
            value="{{ $filters['search'] }}"
            class="patients-search__input"
            placeholder="Search name or username..."
            aria-label="Search accounts"
        >

        <x-dropdown
            name="department_id"
            id="permissions-department"
            variant="chip"
            icon="account_tree"
            :options="$departments->map(fn ($department) => ['value' => (string) $department->id, 'label' => $department->name])->all()"
            value="{{ $filters['department_id'] ?: '' }}"
            placeholder="All Departments"
            aria-label="Department filter"
        />

        <button type="submit" class="btn btn--secondary">Filter</button>
        <a href="{{ route('admin.permissions.index') }}" class="btn btn--ghost">Clear</a>
    </form>

    <div class="bento-card admin-permissions-table">
        @if ($accounts->isEmpty())
            <div class="patient-examinations-card__empty">
                <p class="text-body-md">No accounts found.</p>
            </div>
        @else
            <div class="card-list-mobile card-list">
                @foreach ($accounts as $account)
                    <article class="card-list__item">
                        <div class="card-list__row">
                            <div>
                                <div class="card-list__title">{{ $account->name }}</div>
                                <div class="card-list__meta font-data-mono">{{ $account->username }} · {{ $account->department?->name ?? 'No department' }}</div>
                            </div>
                        </div>
                        <div class="card-list__meta">
                            @if ($account->isAdmin())
                                All permissions (administrator)
                            @else
                                @php $slugs = $account->permissions->pluck('slug'); @endphp
                                @forelse ($permissions as $permission)
                                    @if ($slugs->contains($permission->value))
                                        <span class="admin-permissions-chip">{{ $permission->label() }}</span>
                                    @endif
                                @empty
                                @endforelse
                                @if ($slugs->isEmpty())
                                    No permissions assigned
                                @endif
                            @endif
                        </div>
                        <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn--secondary btn--full">Edit account</a>
                    </article>
                @endforeach
            </div>

            <div class="table-desktop admin-permissions-table__table-wrap">
                <table class="data-table admin-permissions-table__table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Department</th>
                            @foreach ($permissions as $permission)
                                <th>{{ $permission->label() }}</th>
                            @endforeach
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr>
                                <td class="text-on-surface">
                                    {{ $account->name }}
                                    <div class="font-data-mono text-on-surface-variant">{{ $account->username }}</div>
                                </td>
                                <td>{{ $account->department?->name ?? '—' }}</td>
                                @if ($account->isAdmin())
                                    <td colspan="{{ count($permissions) }}" class="admin-permissions-table__admin-cell">All permissions (administrator)</td>
                                @else
                                    @php $slugs = $account->permissions->pluck('slug'); @endphp
                                    @foreach ($permissions as $permission)
                                        <td class="admin-permissions-table__cell">
                                            @if ($slugs->contains($permission->value))
                                                <span class="material-symbols-outlined" aria-label="Granted">check</span>
                                            @else
                                                <span class="admin-permissions-table__dash" aria-hidden="true">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                                <td>
                                    <a href="{{ route('admin.accounts.edit', $account) }}" class="btn btn--link">Edit</a>
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
