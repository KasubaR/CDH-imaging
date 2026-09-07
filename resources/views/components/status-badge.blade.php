@props([
    'variant' => 'new',
])

<span {{ $attributes->class(['status-badge', 'status-badge--'.$variant]) }}>
    {{ $slot }}
</span>
