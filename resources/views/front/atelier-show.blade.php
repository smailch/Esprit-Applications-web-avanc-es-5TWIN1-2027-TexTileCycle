@extends('layouts.trame')

@section('title', $atelier->nom.' · Atelier')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/ateliers.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ateliers-trame.css') }}">
@endpush

@php
    $id = (string) $atelier->getKey();
    $listQuery = $listQuery ?? [];
    $rdvParams = array_filter([
        'atelier_id' => $id,
        'vetement_id' => $listQuery['vetement_id'] ?? null,
    ]);
    $rdvUrl = route('front.rdv.create', $rdvParams);
    $telLien = $atelier->telephoneLien();
    $semaine = $atelier->horairesSemaine();
    $prixMin = $atelier->prixMinimal();
    $aCoordonnees = is_numeric($atelier->latitude) && is_numeric($atelier->longitude);
@endphp

{{-- « Ateliers » du fil d'Ariane ramène à la liste avec les filtres de la recherche ($retourUrl). --}}
@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ $retourUrl }}">Ateliers</a></li>
            <li><span aria-current="page">{{ $atelier->nom }}</span></li>
        </ol>
    </nav>

    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal atelier-bandeau">
            <div class="atelier-avatar atelier-avatar--lg atelier-avatar--{{ $atelier->avatarTone() }}" aria-hidden="true">{{ $atelier->initiales() }}</div>

            <div class="atelier-bandeau__texte">
                <p class="atelier-bandeau__statut">Atelier partenaire</p>
                <h1>{{ $atelier->nom }}</h1>

                {{-- Infos et spécialité sur une seule ligne : le bandeau reste compact. --}}
                <div class="atelier-bandeau__infos">
                    <ul class="atelier-hero__meta">
                        <li><i data-lucide="map-pin" aria-hidden="true"></i> {{ $atelier->ville }}</li>
                        <li class="rating">
                            <i data-lucide="star" aria-hidden="true"></i>
                            <span><span class="sr-only">Note </span>{{ $atelier->noteFormatee() }}<span class="sr-only"> sur 5</span></span>
                            <a href="#avis" class="atelier-hero__reviews"><span aria-hidden="true">·</span> {{ (int) $atelier->nb_avis }} avis</a>
                        </li>
                        <li>
                            <span @class(['open-badge', 'is-open' => $ouvert])>
                                <span class="open-badge__dot" aria-hidden="true"></span>{{ $ouvert ? 'Ouvert maintenant' : 'Fermé' }}
                            </span>
                        </li>
                    </ul>

                    @if ($atelier->specialiteAffichee() !== '')
                        <p class="atelier-hero__specialite"><i data-lucide="scissors" aria-hidden="true"></i> {{ $atelier->specialiteAffichee() }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="tr-bandeau__cote atelier-hero__actions">
            <a href="{{ $rdvUrl }}" class="btn btn-primary" aria-label="Prendre RDV chez {{ $atelier->nom }}">
                <i data-lucide="calendar-plus" aria-hidden="true"></i> Prendre RDV
            </a>
            @if ($telLien)
                <a href="{{ $telLien }}" class="btn btn-secondary" aria-label="Appeler {{ $atelier->nom }} au {{ $atelier->telephone }}">
                    <i data-lucide="phone" aria-hidden="true"></i> Appeler
                </a>
            @endif
            {{-- Mène à la section Avis ; le libellé dit ce que le client peut y faire. --}}
            <a href="#avis" class="btn btn-secondary">
                <i data-lucide="star" aria-hidden="true"></i>
                @if ($monAvis ?? null)
                    Mon avis
                @elseif ($peutDonnerAvis ?? false)
                    Donner mon avis
                @else
                    Avis
                @endif
            </a>
            <x-signaler cible-type="Atelier" :cible-id="(string) $atelier->getKey()" />
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content atelier-show">
    @if ($vetementPourRdv ?? null)
        <div class="ai-banner">
            <i data-lucide="shirt"></i>
            <div>
                <strong>Réparation de « {{ $vetementPourRdv->displayName() }} »</strong>
                <p>Ce vêtement sera déjà sélectionné dans le formulaire de rendez-vous avec cet atelier.</p>
            </div>
        </div>
    @endif

    <div class="atelier-show__layout">
        <div class="atelier-show__content">
            @if (filled($atelier->description))
                <section class="atelier-section tr-fil" aria-labelledby="titre-apropos">
                    <h2 id="titre-apropos">À propos</h2>
                    <p class="atelier-section__text">{{ $atelier->description }}</p>
                </section>
            @endif

            <section class="atelier-section tr-fil" aria-labelledby="titre-services">
                <div class="atelier-section__head">
                    <h2 id="titre-services">Services et tarifs</h2>
                    @if ($prixMin !== null)
                        <p class="muted">À partir de {{ \App\Modules\Ateliers\Models\Atelier::formatPrix($prixMin) }}</p>
                    @endif
                </div>

                @if ($atelier->services->isEmpty())
                    <p class="atelier-section__empty">Cet atelier n'a pas encore publié ses services. Contactez-le directement pour un devis.</p>
                @else
                    <ul class="service-list">
                        @foreach ($atelier->services as $service)
                            <li class="service-row">
                                <div class="service-row__icon" aria-hidden="true"><i data-lucide="scissors"></i></div>
                                <div class="service-row__body">
                                    <h3>{{ $service->nom }}</h3>
                                    @if (filled($service->description))
                                        <p>{{ $service->description }}</p>
                                    @endif
                                    @if ($service->dureeFormatee())
                                        <span class="service-row__duration">
                                            <i data-lucide="clock" aria-hidden="true"></i>
                                            <span class="sr-only">Durée estimée : </span>{{ $service->dureeFormatee() }}
                                        </span>
                                    @endif
                                </div>
                                <div class="service-row__side">
                                    @if ($service->prixFormate())
                                        <p class="service-row__price"><span class="sr-only">Prix estimé : </span>{{ $service->prixFormate() }}</p>
                                    @endif
                                    <a href="{{ route('front.rdv.create', $rdvParams + ['service_id' => (string) $service->getKey()]) }}" class="btn btn-secondary small" aria-label="Prendre RDV pour « {{ $service->nom }} » chez {{ $atelier->nom }}">
                                        Réserver
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @include('front.partials.atelier-avis')
        </div>

        <aside class="atelier-show__aside" aria-label="Informations pratiques">
            <section class="panel atelier-info tr-fil" aria-labelledby="titre-coordonnees">
                <h2 id="titre-coordonnees">Coordonnées</h2>
                <dl class="atelier-info__list">
                    <div>
                        <dt><i data-lucide="map-pin" aria-hidden="true"></i> Adresse</dt>
                        <dd>{{ $atelier->adresse }}<br>{{ $atelier->ville }}</dd>
                    </div>
                    @if ($telLien)
                        <div>
                            <dt><i data-lucide="phone" aria-hidden="true"></i> Téléphone</dt>
                            <dd><a href="{{ $telLien }}">{{ $atelier->telephone }}</a></dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="panel atelier-info tr-fil" aria-labelledby="titre-horaires">
                <h2 id="titre-horaires">Horaires</h2>
                <table class="horaires-table">
                    <caption class="sr-only">Horaires d'ouverture de la semaine, heure de Tunis</caption>
                    <thead class="sr-only">
                        <tr><th scope="col">Jour</th><th scope="col">Horaires</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($semaine as $nomJour => $plages)
                            <tr @class(['is-today' => $nomJour === $jour])>
                                <th scope="row">
                                    {{ ucfirst($nomJour) }}
                                    @if ($nomJour === $jour)
                                        <span class="horaires-table__today">Aujourd'hui</span>
                                    @endif
                                </th>
                                <td>
                                    @if (empty($plages))
                                        <span class="horaires-table__closed">Fermé</span>
                                    @else
                                        @foreach ($plages as [$debut, $fin])
                                            <span class="horaires-table__slot">{{ $debut }} – {{ $fin }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            @if ($aCoordonnees)
                <section class="panel atelier-info tr-fil" aria-labelledby="titre-localisation">
                    <h2 id="titre-localisation">Localisation</h2>
                    <x-atelier-map
                        :markers="$markers"
                        mode="single"
                        :label="'Carte : emplacement de '.$atelier->nom"
                        :alternative="'Adresse : '.$atelier->adresse.', '.$atelier->ville.'.'"
                    />
                </section>
            @endif
        </aside>
    </div>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('js/avis.js') }}" defer></script>
@endpush

@if ($aCoordonnees)
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/ateliers-map.js') }}"></script>
    @endpush
@endif
