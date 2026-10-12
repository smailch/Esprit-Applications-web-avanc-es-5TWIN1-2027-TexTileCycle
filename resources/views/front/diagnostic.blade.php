@extends('layouts.front')

@section('title', 'Diagnostic par photo · Ateliers')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="{{ asset('css/ateliers.css') }}">
@endpush

@php
    $erreurPhoto = $errors->first('photo');
    $erreurTexte = $errors->first('texte');
@endphp

@section('content')
<main class="container page-content diag">
    <nav class="atelier-breadcrumb" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.ateliers') }}">Ateliers</a></li>
            <li><span aria-current="page">Diagnostic par photo</span></li>
        </ol>
    </nav>

    {{-- Écran de saisie. Masqué quand le résultat est rendu par le serveur (envoi sans JavaScript). --}}
    <div class="diag-hero" data-ecran-saisie @if ($resultat) hidden @endif>
        <div class="diag-intro">
            <h1>Diagnostiquer mon vêtement</h1>
            <p class="diag-intro__texte">Une photo de la partie abîmée suffit : on vous propose la réparation et les ateliers qui la font près de chez vous.</p>

            <ol class="diag-etapes">
                <li><span class="diag-etapes__num" aria-hidden="true">1</span> Photographiez la partie abîmée, de près et bien éclairée.</li>
                <li><span class="diag-etapes__num" aria-hidden="true">2</span> L'IA reconnaît le défaut et cherche la réparation.</li>
                <li><span class="diag-etapes__num" aria-hidden="true">3</span> Choisissez un atelier et prenez rendez-vous.</li>
            </ol>
        </div>

        {{-- Contrôles de saisie en PHP (DiagnosticPhotoRequest) : aucun attribut de validation HTML. --}}
        <form method="post" action="{{ route('front.ateliers.diagnostic.analyser') }}" enctype="multipart/form-data"
              class="diag-form diagnostic-form" data-diagnostic-form>
            @csrf

            <div data-alerte-zone></div>

            @if (session('diagnostic_erreur'))
                <div class="diag-alerte" role="alert">
                    <i data-lucide="alert-triangle" aria-hidden="true"></i>
                    <div>
                        <p>{{ session('diagnostic_erreur') }}</p>
                        <a href="{{ route('front.ateliers') }}">Chercher un atelier par service ou par ville</a>
                    </div>
                </div>
            @endif

            <label for="diagnostic-photo" @class(['diag-depot', 'has-error' => $erreurPhoto]) data-depot>
                <input id="diagnostic-photo" type="file" name="photo" class="diag-depot__input" data-depot-input
                       aria-describedby="diagnostic-photo-aide @if ($erreurPhoto) diagnostic-photo-erreur @endif"
                       @if ($erreurPhoto) aria-invalid="true" @endif>
                <span class="diag-depot__vide">
                    <i data-lucide="camera" aria-hidden="true"></i>
                    <span class="diag-depot__titre">Déposez la photo ici</span>
                    <span class="diag-depot__lien">ou choisissez un fichier</span>
                </span>
                <span class="diag-depot__plein">
                    <img alt="Aperçu de la photo choisie" data-depot-apercu>
                    <span class="diag-depot__fichier">
                        <span data-depot-nom></span>
                        <span class="diag-depot__changer">Changer de photo</span>
                    </span>
                </span>
            </label>
            <p id="diagnostic-photo-aide" class="diag-aide">JPG, PNG ou WebP, 5 Mo au plus.</p>
            @if ($erreurPhoto)
                <p id="diagnostic-photo-erreur" class="diag-erreur"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $erreurPhoto }}</p>
            @endif

            <div @class(['diag-champ', 'has-error' => $erreurTexte])>
                <label for="diagnostic-texte">Précision <span class="diag-facultatif">facultative</span></label>
                <textarea id="diagnostic-texte" name="texte" rows="2" placeholder="Ex. trou près de la poche, fermeture bloquée"
                          @if ($erreurTexte) aria-describedby="diagnostic-texte-erreur" aria-invalid="true" @endif>{{ old('texte', $texte) }}</textarea>
                @if ($erreurTexte)
                    <p id="diagnostic-texte-erreur" class="diag-erreur"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $erreurTexte }}</p>
                @endif
            </div>

            {{-- Position facultative, remplie par js/diagnostic.js : sert à classer les ateliers par distance. --}}
            <div class="diag-position" data-position hidden>
                <button type="button" class="diag-position__bouton" data-position-bouton aria-pressed="false">
                    <i data-lucide="locate" aria-hidden="true"></i>
                    <span data-position-libelle>Utiliser ma position pour trouver les ateliers proches</span>
                </button>
                <p class="diag-position__etat" data-position-etat role="status" aria-live="polite"></p>
                {{-- Carte de contrôle : le citoyen vérifie la position détectée avant de lancer l'analyse. --}}
                <div class="diag-position__apercu" data-position-apercu hidden>
                    <div class="diag-position__carte" data-position-carte role="img" aria-label="Carte de votre position"></div>
                    <p class="diag-position__coordonnees" data-position-coordonnees></p>
                </div>
                <input type="hidden" name="lat" value="" data-position-lat>
                <input type="hidden" name="lng" value="" data-position-lng>
            </div>

            <button type="submit" class="btn btn-primary diag-envoyer">
                <i data-lucide="scan-search" aria-hidden="true"></i>
                <span data-envoi-libelle>Lancer le diagnostic</span>
            </button>
            <p class="diag-confidentialite"><i data-lucide="lock" aria-hidden="true"></i> La photo sert uniquement à l'analyse, elle n'est pas enregistrée.</p>
        </form>
    </div>

    {{-- Écran « résultat » : rendu ici sans JavaScript, ou inséré par js/diagnostic.js sans recharger la page. --}}
    <div class="diag-resultat-zone" data-resultat-zone>
        @if ($resultat)
            @include('front.partials.diagnostic-resultat', ['resultat' => $resultat])
        @endif
    </div>

    <p class="sr-only" role="status" aria-live="polite" data-annonce></p>
</main>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/diagnostic.js') }}" defer></script>
@endpush
