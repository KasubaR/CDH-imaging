@props([
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'placeholder' => 'Select…',
    'required' => false,
    'variant' => 'form',
    'icon' => null,
    'disabled' => false,
])

@php
    $fieldId = $id ?? $name ?? 'dropdown-'.uniqid();
    $listboxId = $fieldId.'-listbox';
    $ariaLabel = $attributes->get('aria-label');
    $normalized = [];

    foreach ($options as $key => $option) {
        if (is_array($option)) {
            $normalized[] = [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['label'] ?? ''),
            ];

            continue;
        }

        $normalized[] = [
            'value' => (string) $key,
            'label' => (string) $option,
        ];
    }

    $selectedValue = $value !== null ? (string) $value : '';
    $selectedLabel = $placeholder;
    $hasSelection = false;

    foreach ($normalized as $option) {
        if ($option['value'] === $selectedValue) {
            $selectedLabel = $option['label'];
            $hasSelection = true;
            break;
        }
    }

    $showPlaceholderStyle = ! $hasSelection || $selectedValue === '';
@endphp

<div
    {{ $attributes->class([
        'dropdown',
        'dropdown--'.$variant,
        'dropdown--disabled' => $disabled,
    ])->merge([
        'data-dropdown' => true,
        'data-placeholder' => $placeholder,
    ])->except('aria-label') }}
>
    @if ($name)
        <select
            name="{{ $name }}"
            id="{{ $fieldId }}-native"
            class="dropdown__native sr-only"
            tabindex="-1"
            aria-hidden="true"
            @disabled($disabled)
            @if ($required) required @endif
            data-dropdown-native
        >
            @if ($placeholder !== null && ! collect($normalized)->contains(fn (array $option): bool => $option['value'] === ''))
                <option value="" @selected($selectedValue === '')>{{ $placeholder }}</option>
            @endif
            @foreach ($normalized as $option)
                <option value="{{ $option['value'] }}" @selected($option['value'] === $selectedValue)>{{ $option['label'] }}</option>
            @endforeach
        </select>
    @endif

    <button
        type="button"
        id="{{ $fieldId }}"
        class="dropdown__trigger"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $listboxId }}"
        @if ($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
        @disabled($disabled)
        data-dropdown-trigger
    >
        @if ($icon)
            <span class="material-symbols-outlined dropdown__icon" aria-hidden="true">{{ $icon }}</span>
        @endif
        <span class="dropdown__value" data-dropdown-value @class(['dropdown__value--placeholder' => $showPlaceholderStyle])>
            {{ $selectedLabel }}
        </span>
        <span class="material-symbols-outlined dropdown__chevron" aria-hidden="true">expand_more</span>
    </button>

    <ul
        id="{{ $listboxId }}"
        class="dropdown__menu"
        role="listbox"
        tabindex="-1"
        hidden
        data-dropdown-menu
    >
        @foreach ($normalized as $option)
            <li
                class="dropdown__option"
                role="option"
                data-value="{{ $option['value'] }}"
                aria-selected="{{ $option['value'] === $selectedValue ? 'true' : 'false' }}"
                @class(['dropdown__option--selected' => $option['value'] === $selectedValue])
                data-dropdown-option
            >
                <span class="dropdown__option-label">{{ $option['label'] }}</span>
                <span class="material-symbols-outlined dropdown__option-check" aria-hidden="true">check</span>
            </li>
        @endforeach
    </ul>
</div>
