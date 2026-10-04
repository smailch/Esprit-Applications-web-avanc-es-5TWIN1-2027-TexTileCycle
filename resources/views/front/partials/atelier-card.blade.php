@php
    $id = (string) $atelier->getKey();
    $ficheUrl = route('front.ateliers.show', ['id' => $id] + $listQuery);
    $distance = \App\Modules\Ateliers\Services\AtelierService::formatDistance($atelier->distance_km);
    $prixMin = $atelier->prixMinimal();
    $services = $atelier->services->pluck('nom')->filter()->unique()->values();
    $ouvert = $atelier->estOuvert();
    $titreId = 'atelier-titre-'.$id;
@endphp

<article class="atelier-card" id="atelier-{{ $id }}" data-atelier-id="{{ $id }}" aria-labelledby="{{ $titreId }}">
    <div class="atelier-avatar atelier-avatar--{{ $atelier->avatarTone() }}" aria-hidden="true">{{ $atelier->initiales() }}</div>

    <div class="atelier-card__body">
        <div class="atelier-card__head">
            <h2 class="atelier-card__title" id="{{ $titreId }}">
                <a href="{{ $ficheUrl }}">{{ $atelier->nom }}</a>
            </h2>
            <span @class(['open-badge', 'is-open' => $ouvert])>
                <span class="open-badge__dot" aria-hidden="true"></span>{{ $ouvert ? 'Ouvert maintenant' : 'Fermé' }}
            </span>
        </div>

        <p class="atelier-card__place">
            <i data-lucide="map-pin" aria-hidden="true"></i>
            <span>{{ $atelier->ville }}</span>
            @if ($distance)
                <span class="atelier-card__distance"><span aria-hidden="true">·</span> à {{ $distance }}</span>
            @endif
        </p>

        @if ($atelier->specialiteAffichee() !== '')
            <p class="atelier-card__specialite">{{ $atelier->specialiteAffichee() }}</p>
        @endif

        @if ($services->isNotEmpty())
            <ul class="atelier-card__services" aria-label="Services proposés par {{ $atelier->nom }}">
                @foreach ($services->take(3) as $nomService)
                    <li>{{ $nomService }}</li>
                @endforeach
                @if ($services->count() > 3)
                    <li class="is-more"><span aria-hidden="true">+{{ $services->count() - 3 }}</span><span class="sr-only">et {{ $services->count() - 3 }} autres services</span></li>
                @endif
            </ul>
        @endif

        <div class="atelier-card__foot">
            <p class="rating atelier-card__rating">
                <i data-lucide="star" aria-hidden="true"></i>
                <span><span class="sr-only">Note </span>{{ $atelier->noteFormatee() }}<span class="sr-only"> sur 5</span></span>
                <span class="atelier-card__reviews"><span aria-hidden="true">·</span> {{ (int) $atelier->nb_avis }} avis</span>
            </p>

            @if ($prixMin !== null)
                <p class="atelier-card__price">à partir de <strong>{{ \App\Modules\Ateliers\Models\Atelier::formatPrix($prixMin) }}</strong></p>
            @endif

            <div class="atelier-card__actions">
                @if (is_numeric($atelier->latitude) && is_numeric($atelier->longitude))
                    <button type="button" class="icon-btn atelier-card__locate" data-show-on-map="{{ $id }}" aria-label="Voir {{ $atelier->nom }} sur la carte">
                        <i data-lucide="map" aria-hidden="true"></i>
                    </button>
                @endif
                <a href="{{ $ficheUrl }}" class="btn btn-secondary small" aria-label="Voir la fiche de {{ $atelier->nom }}">Voir</a>
                <a href="{{ route('front.rdv', ['atelier' => $id]) }}" class="btn btn-primary small" aria-label="Prendre RDV chez {{ $atelier->nom }}">
                    <i data-lucide="calendar-plus" aria-hidden="true"></i> Prendre RDV
                </a>
            </div>
        </div>
    </div>
</article>
