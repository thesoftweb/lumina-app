@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'value' => null,
])

@php
    $id = 'campo-'.str_replace('_', '-', $name);
    $describedBy = collect([$hint ? $id.'-dica' : null, $errors->has($name) ? $id.'-erro' : null])->filter()->implode(' ');
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-semibold text-gray-800 mb-1">
        {{ $label }}
        @if ($required)
            <span class="text-red-600" aria-hidden="true">*</span>
        @else
            <span class="font-normal text-gray-500">(opcional)</span>
        @endif
    </label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @if ($required) required aria-required="true" @endif
        @if ($errors->has($name)) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->merge(['class' => 'field']) }} />
    @if ($hint)
        <p id="{{ $id }}-dica" class="mt-1 text-sm text-gray-600">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-erro" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
    @enderror
</div>
