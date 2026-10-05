<section class="panel ab-section" aria-labelledby="sec-localisation">
    <div class="panel-head">
        <div>
            <h2 id="sec-localisation">Localisation</h2>
            <p>Adresse affichée et position sur la carte du site</p>
        </div>
    </div>
    <div class="ab-section__body">
        @include('back.ateliers.partials.field', ['name' => 'adresse', 'label' => 'Adresse', 'value' => old('adresse', $atelier->adresse), 'required' => true, 'attrs' => ['maxlength' => 255, 'autocomplete' => 'street-address']])

        @include('back.ateliers.partials.field', ['name' => 'ville', 'label' => 'Ville', 'value' => old('ville', $atelier->ville), 'required' => true, 'attrs' => ['maxlength' => 100, 'autocomplete' => 'address-level2']])

        <div class="ab-map-picker" data-location-picker data-default-lat="36.8065" data-default-lng="10.1815">
            <div class="ab-map" data-location-map role="region" aria-label="Carte de positionnement de l'atelier" aria-describedby="ab-map-help">
                <p class="ab-map__fallback"><i data-lucide="map" aria-hidden="true"></i> Chargement de la carte…</p>
            </div>
            <p id="ab-map-help" class="ab-hint">Cliquez sur la carte pour placer l'atelier, puis faites glisser le marqueur pour ajuster. Les coordonnées peuvent aussi être saisies à la main.</p>
            <p class="ab-map__status" data-location-status role="status" aria-live="polite"></p>
        </div>

        <div class="ab-field-row">
            @include('back.ateliers.partials.field', ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'value' => old('latitude', $atelier->latitude), 'attrs' => ['step' => 'any', 'min' => -90, 'max' => 90, 'inputmode' => 'decimal', 'data-lat-input' => '']])
            @include('back.ateliers.partials.field', ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'value' => old('longitude', $atelier->longitude), 'attrs' => ['step' => 'any', 'min' => -180, 'max' => 180, 'inputmode' => 'decimal', 'data-lng-input' => '']])
        </div>
        <button type="button" class="link-button ab-clear-location" data-clear-location>
            <i data-lucide="eraser" aria-hidden="true"></i> Effacer la position
        </button>
    </div>
</section>
