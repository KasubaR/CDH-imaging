@extends('layouts.app')

@section('title', 'Transfer matrix — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Transfer matrix')

@section('content')
<div class="canvas__inner admin-departments-page">
    <div class="page-header page-header--split">
        <p class="text-body-md page-header__lede">Check a cell to let the row department send transfers to the column department. This is what TransferAuthorizationService actually checks — the per-department "Can send/receive" flags are a separate, coarser toggle.</p>

        <a href="{{ route('admin.departments.index') }}" class="btn btn--secondary">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to departments
        </a>
    </div>

    <div class="bento-card admin-departments-matrix">
        <form method="post" action="{{ route('admin.departments.matrix.update') }}">
            @csrf
            @method('PUT')

            <div class="table-desktop admin-departments-matrix__table-wrap">
                <table class="data-table admin-departments-matrix__table">
                    <thead>
                        <tr>
                            <th>From \ To</th>
                            @foreach ($departments as $to)
                                <th>{{ $to->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($departments as $from)
                            <tr>
                                <th scope="row" class="text-on-surface">{{ $from->name }}</th>
                                @foreach ($departments as $to)
                                    <td class="admin-departments-matrix__cell">
                                        @if ($from->id === $to->id)
                                            <span class="admin-departments-matrix__self" aria-hidden="true">—</span>
                                        @else
                                            <input
                                                type="checkbox"
                                                name="matrix[{{ $from->id }}][{{ $to->id }}]"
                                                value="1"
                                                @checked($enabledPairs->has($from->id.':'.$to->id))
                                                aria-label="{{ $from->name }} can send to {{ $to->name }}"
                                            >
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Save matrix</button>
            </div>
        </form>
    </div>
</div>
@endsection
