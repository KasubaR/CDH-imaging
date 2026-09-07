@props([
    'type' => 'info',
])

<div {{ $attributes->class(['alert', 'alert--'.$type]) }} role="alert">
    {{ $slot }}
</div>
