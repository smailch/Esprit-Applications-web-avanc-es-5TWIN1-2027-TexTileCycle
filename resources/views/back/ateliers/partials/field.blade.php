@php
    $id = $id ?? 'f-'.$name;
    $type = $type ?? 'text';
    $hint = $hint ?? null;
    $required = $required ?? false;
    $hasError = $errors->has($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($hasError ? $id.'-error' : ''));
@endphp

<div @class(['ab-field', 'has-error' => $hasError, $class ?? null])>
    <label for="{{ $id }}" class="ab-label">
        {{ $label }}
        @if ($required)<span class="ab-required" aria-hidden="true">*</span>@endif
    </label>

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 4 }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @foreach ($attrs ?? [] as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
        >{{ $value }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @foreach ($attrs ?? [] as $attr => $attrValue) {{ $attr }}="{{ $attrValue }}" @endforeach
        >
    @endif

    @if ($hint)
        <p id="{{ $id }}-hint" class="ab-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="ab-error"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $message }}</p>
    @enderror
</div>
