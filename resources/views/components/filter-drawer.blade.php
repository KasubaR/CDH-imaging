@props([
    'title' => 'Filters',
])

<button type="button" class="btn btn--secondary filter-drawer-trigger" data-filter-drawer-toggle aria-expanded="false">
    <span class="material-symbols-outlined">filter_list</span>
    Filter
</button>

<div class="filter-drawer-overlay" data-filter-drawer-overlay></div>

<aside class="filter-drawer" data-filter-drawer aria-label="{{ $title }}">
    <div class="filter-drawer__header">
        <h3 class="filter-drawer__title">{{ $title }}</h3>
        <button type="button" class="btn btn--ghost btn--icon" data-filter-drawer-close aria-label="Close filters">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
    <div class="filter-drawer__body">
        {{ $slot }}
    </div>
</aside>
