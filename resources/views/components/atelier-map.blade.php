@props([
    'markers' => [],
    'mode' => 'list',
    'label' => 'Carte des ateliers',
    'endpoint' => null,
    'userLat' => null,
    'userLng' => null,
    'alternative' => 'La liste des ateliers présente les mêmes informations que la carte.',
])

<div {{ $attributes->class(['atelier-map', 'atelier-map--'.$mode]) }}>
    <div
        class="atelier-map__canvas"
        data-atelier-map
        data-mode="{{ $mode }}"
        @if ($endpoint) data-endpoint="{{ $endpoint }}" @endif
        @if ($userLat !== null && $userLng !== null) data-user-lat="{{ $userLat }}" data-user-lng="{{ $userLng }}" @endif
        role="region"
        aria-label="{{ $label }}"
        aria-describedby="{{ $mode }}-map-alternative"
    >
        <div class="atelier-map__fallback">
            <i data-lucide="map" aria-hidden="true"></i>
            <span>Chargement de la carte…</span>
        </div>
    </div>
    <p id="{{ $mode }}-map-alternative" class="sr-only">{{ $alternative }}</p>
    <p class="atelier-map__status" data-map-status role="status" aria-live="polite"></p>
    <script type="application/json" data-map-markers>@json($markers)</script>
</div>
