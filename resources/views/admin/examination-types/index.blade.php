@extends('layouts.app')

@section('title', 'Examination Types — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Examination Types')

@section('content')
<div class="canvas__inner admin-examination-types-page">
    <div class="admin-examination-types-page__header page-header page-header--split">
        <p class="text-body-md page-header__lede">Manage body-region examination types used when recording studies.</p>

        <a href="{{ route('admin.examination-types.create') }}" class="btn btn--primary">
            <span class="material-symbols-outlined">add</span>
            Add type
        </a>
    </div>

    <div class="bento-card admin-examination-types-table">
        <div class="card-list-mobile card-list">
            @foreach ($examinationTypes as $type)
                <article @class(['card-list__item', 'admin-examination-types-table__status--inactive' => ! $type->is_active])>
                    <div class="card-list__row">
                        <div>
                            <div class="card-list__title">{{ $type->name }}</div>
                            <div class="card-list__meta font-data-mono">{{ $type->code }} · {{ $type->examinations_count }} exams</div>
                        </div>
                        @if ($type->is_active)
                            <x-status-badge variant="completed">Active</x-status-badge>
                        @else
                            <x-status-badge variant="sent">Inactive</x-status-badge>
                        @endif
                    </div>
                    <div class="card-list__actions">
                        <a href="{{ route('admin.examination-types.edit', $type) }}" class="btn btn--secondary">Edit</a>
                        @if ($type->is_active)
                            <form method="post" action="{{ route('admin.examination-types.deactivate', $type) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn--ghost">Deactivate</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.examination-types.activate', $type) }}">
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
                        <th>Examinations</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($examinationTypes as $type)
                        <tr @class(['admin-examination-types-table__status--inactive' => ! $type->is_active])>
                            <td class="text-on-surface">{{ $type->name }}</td>
                            <td class="font-data-mono">{{ $type->code }}</td>
                            <td>{{ $type->examinations_count }}</td>
                            <td>
                                @if ($type->is_active)
                                    <x-status-badge variant="completed">Active</x-status-badge>
                                @else
                                    <x-status-badge variant="sent">Inactive</x-status-badge>
                                @endif
                            </td>
                            <td>
                                <div class="admin-examination-types-table__actions">
                                    <a href="{{ route('admin.examination-types.edit', $type) }}" class="btn btn--link">Edit</a>
                                    @if ($type->is_active)
                                        <form method="post" action="{{ route('admin.examination-types.deactivate', $type) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn--link">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="post" action="{{ route('admin.examination-types.activate', $type) }}">
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
