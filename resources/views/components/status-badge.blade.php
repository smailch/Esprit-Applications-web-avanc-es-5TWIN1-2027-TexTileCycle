@props(['tone' => 'green'])

<span {{ $attributes->merge(['class' => "status status-{$tone}"]) }}>
    {{ $slot }}
</span>
