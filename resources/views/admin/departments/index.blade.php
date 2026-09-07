@extends('layouts.app')

@section('title', 'Departments — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Departments')

@section('content')
<div class="canvas__inner admin-departments-page">
    <div class="admin-departments-page__header page-header page-header--split">
        <p class="text-body-md page-header__lede">Manage departments and which ones can transfer images to each other.</p>

        <div class="admin-departments-page__header-actions">
            <a href="{{ route('admin.departments.matrix') }}" class="btn btn--secondary">
                <span class="material-symbols-outlined">swap_horiz</span>
                Transfer matrix
            </a>
            <a href="{{ route('admin.departments.create') }}" class="btn btn--primary">
                <span class="material-symbols-outlined">add</span>
                Add department
            </a>
        </div>
    </div>

    <div class="bento-card admin-departments-table">
        <div class="card-list-mobile card-list">
            @foreach ($departments as $department)
                <article @class(['card-list__item', 'admin-departments-table__status--inactive' => ! $department->is_active])>
                    <div class="card-list__row">
                        <div>
                            <div class="card-list__title">{{ $department->name }}</div>
                            <div class="card-list__meta font-data-mono">{{ $department->code }} · {{ $department->users_count }} accounts · {{ $department->examinations_count }} exams</div>
                        </div>
                        @if ($department->is_active)
                            <x-status-badge variant="completed">Active</x-status-badge>
                        @else
                            <x-status-badge variant="sent">Inactive</x-status-badge>
                        @endif
                    </div>
                    <div class="card-list__actions">
                        <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn--secondary">Edit</a>
                        @if ($department->is_active)
                            <form method="post" action="{{ route('admin.departments.deactivate', $department) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn--ghost">Deactivate</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.departments.activate', $department) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn--ghost">Activate</button>
                            </form>
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
                        <th>Code</th>
                        <th>Accounts</th>
                        <th>Examinations</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($departments as $department)
                        <tr @class(['admin-departments-table__status--inactive' => ! $department->is_active])>
                            <td class="text-on-surface">{{ $department->name }}</td>
                            <td class="font-data-mono">{{ $department->code }}</td>
                            <td>{{ $department->users_count }}</td>
                            <td>{{ $department->examinations_count }}</td>
                            <td>
                                @if ($department->is_active)
                                    <x-status-badge variant="completed">Active</x-status-badge>
                                @else
                                    <x-status-badge variant="sent">Inactive</x-status-badge>
                                @endif
                            </td>
                            <td>
                                <div class="admin-departments-table__actions">
                                    <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn--link">Edit</a>
                                    @if ($department->is_active)
                                        <form method="post" action="{{ route('admin.departments.deactivate', $department) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn--link">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('admin.departments.activate', $department) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn--link">Activate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
