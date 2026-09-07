@props([
    'activeNav' => null,
])

@php
    $user = auth()->user();
    $brandTitle = match (true) {
        $user?->isAdmin() => 'Admin',
        $user?->isMoic() => 'MOIC',
        default => $user?->department?->name ?? 'CDH',
    };
@endphp

<aside class="sidebar" data-sidebar id="sidebar" aria-label="Main navigation">
    <div class="sidebar__header">
        <div class="sidebar__brand">
            <img
                src="{{ asset('images/Coat_of_arms_of_Zambia.svg') }}"
                alt="Chililabombwe District Hospital"
                class="sidebar__logo"
            >
            <div class="sidebar__brand-text">
                <div class="sidebar__brand-title">{{ $brandTitle }}</div>
                <div class="sidebar__brand-subtitle">District Hospital</div>
            </div>
        </div>
    </div>

    <div class="sidebar__cta">
        <button type="button" class="sidebar__fab" title="New Transfer">
            <span class="material-symbols-outlined">add</span>
            <span class="sidebar__fab-label">New Transfer</span>
        </button>
    </div>

    <nav class="sidebar__nav" aria-label="Primary">
        @if ($user?->isAdmin())
            <a href="{{ route('admin.dashboard') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.dashboard')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.dashboard')) material-symbols-outlined--filled @endif">dashboard</span>
                <span class="sidebar__nav-label">Dashboard</span>
            </a>
        @endif

        @can('view-images')
            @unless ($user?->isAdmin())
                <a href="{{ route('department.dashboard') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('department.dashboard')])>
                    <span class="material-symbols-outlined @if(request()->routeIs('department.dashboard')) material-symbols-outlined--filled @endif">dashboard</span>
                    <span class="sidebar__nav-label">Dashboard</span>
                </a>
            @endunless
            <a href="{{ route('department.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('department.index')])>
                <span class="material-symbols-outlined @if(request()->routeIs('department.index')) material-symbols-outlined--filled @endif">inbox</span>
                <span class="sidebar__nav-label">Inbox</span>
            </a>
        @endcan

        <a href="{{ route('department.index') }}" class="sidebar__nav-link">
            <span class="material-symbols-outlined">send</span>
            <span class="sidebar__nav-label">Sent</span>
        </a>

        @can('view-images')
            <a href="{{ route('patients.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('patients.*') || request()->routeIs('examinations.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('patients.*') || request()->routeIs('examinations.*')) material-symbols-outlined--filled @endif">person_search</span>
                <span class="sidebar__nav-label">Patients</span>
            </a>
        @endcan

        @if ($user?->isAdmin())
            <a href="{{ route('admin.examination-types.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.examination-types.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.examination-types.*')) material-symbols-outlined--filled @endif">category</span>
                <span class="sidebar__nav-label">Examination Types</span>
            </a>
            <a href="{{ route('admin.accounts.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.accounts.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.accounts.*')) material-symbols-outlined--filled @endif">manage_accounts</span>
                <span class="sidebar__nav-label">Accounts</span>
            </a>
            <a href="{{ route('admin.departments.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.departments.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.departments.*')) material-symbols-outlined--filled @endif">account_tree</span>
                <span class="sidebar__nav-label">Departments</span>
            </a>
            <a href="{{ route('admin.audit-logs.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.audit-logs.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.audit-logs.*')) material-symbols-outlined--filled @endif">fact_check</span>
                <span class="sidebar__nav-label">Audit Log</span>
            </a>
            <a href="{{ route('admin.permissions.index') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.permissions.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.permissions.*')) material-symbols-outlined--filled @endif">key</span>
                <span class="sidebar__nav-label">Permissions</span>
            </a>
            <a href="{{ route('admin.settings.edit') }}" @class(['sidebar__nav-link', 'sidebar__nav-link--active' => request()->routeIs('admin.settings.*')])>
                <span class="material-symbols-outlined @if(request()->routeIs('admin.settings.*')) material-symbols-outlined--filled @endif">settings</span>
                <span class="sidebar__nav-label">Settings</span>
            </a>
        @endif
    </nav>

    <div class="sidebar__footer">
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar__nav-link sidebar__nav-link--button">
                <span class="material-symbols-outlined">logout</span>
                <span class="sidebar__nav-label">Logout</span>
            </button>
        </form>
    </div>
</aside>
