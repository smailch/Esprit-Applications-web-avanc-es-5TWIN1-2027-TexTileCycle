@extends('layouts.trame')

@section('title', 'Passeport de réparation '.$passeport->numeroCourt())

@push('styles')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/passeport.css') }}">
@endpush

@php
    $revoque = $passeport->estRevoque();
    $matiere = $passeport->info('matiere');
@endphp

{{-- Page publique ouverte par le QR code : aucune donnée sur le client, seulement la pièce et ses réparations. --}}
@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Passeport {{ $passeport->numeroCourt() }}</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <p class="pp-kicker">Passeport de réparation · {{ $passeport->numeroCourt() }}</p>
            <h1>{{ $revoque ? 'Passeport révoqué' : 'Pièce réparée et certifiée' }}</h1>
            <p class="tr-bandeau__intro">
                {{ $passeport->vetementAffiche() }}@if ($matiere !== '') · {{ $matiere }}@endif
            </p>
        </div>
        <div class="tr-bandeau__cote">
            <p @class(['pp-sceau', 'is-revoque' => $revoque])>
                <i data-lucide="{{ $revoque ? 'shield-x' : 'shield-check' }}" aria-hidden="true"></i>
                {{ $revoque ? 'Non valide' : 'Certifié par un atelier partenaire' }}
            </p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content pp">
    @if ($revoque)
        <section class="pp-revoque tr-fil" role="alert" aria-labelledby="pp-revoque-titre">
            <h2 id="pp-revoque-titre"><i data-lucide="shield-x" aria-hidden="true"></i> Ce passeport n'est plus valide</h2>
            <p>TexTileCycle a révoqué ce passeport le {{ $passeport->revoque_le->locale('fr')->isoFormat('D MMMM YYYY') }}.
               Les informations de réparation ne sont plus garanties. En cas de doute, contactez la plateforme.</p>
        </section>
    @else
        <div class="pp-layout">
            <section class="pp-historique tr-fil" aria-labelledby="pp-historique-titre">
                <h2 id="pp-historique-titre">Historique des réparations</h2>
                <p class="muted">{{ $historique->count() }} réparation{{ $historique->count() > 1 ? 's' : '' }} certifiée{{ $historique->count() > 1 ? 's' : '' }} pour cette pièce.</p>

                <ol class="pp-frise">
                    @foreach ($historique as $etape)
                        @php $actuel = (string) $etape->getKey() === (string) $passeport->getKey(); @endphp
                        <li @class(['pp-frise__etape', 'is-actuel' => $actuel])>
                            <span class="pp-frise__point" aria-hidden="true"><i data-lucide="scissors"></i></span>
                            <div class="pp-frise__corps">
                                <p class="pp-frise__date">{{ $etape->dateReparationAffichee() }}</p>
                                <h3>{{ $etape->reparationAffichee() }}</h3>
                                @if ($etape->info('service_description') !== '' || $etape->dureeServiceAffichee() !== '')
                                    <p class="pp-frise__service">
                                        {{ $etape->info('service_description') }}
                                        @if ($etape->dureeServiceAffichee() !== '')
                                            <span class="pp-frise__duree"><i data-lucide="clock" aria-hidden="true"></i> {{ $etape->dureeServiceAffichee() }}</span>
                                        @endif
                                    </p>
                                @endif

                                @if ($etape->info('mot_atelier') !== '')
                                    <blockquote class="pp-frise__mot">
                                        <p>{{ $etape->info('mot_atelier') }}</p>
                                        <footer>— l'atelier</footer>
                                    </blockquote>
                                @endif

                                @php $atelierEtape = $ateliers->get((string) $etape->atelier_id); @endphp
                                <p class="pp-frise__atelier">
                                    <i data-lucide="store" aria-hidden="true"></i>
                                    @if ($atelierEtape)
                                        <a href="{{ route('front.ateliers.show', ['id' => (string) $etape->atelier_id]) }}">{{ $etape->info('atelier') }}</a>
                                    @else
                                        <span>{{ $etape->info('atelier') }}</span>
                                    @endif
                                    @if ($etape->info('ville') !== '') · {{ $etape->info('ville') }} @endif
                                    @if ($atelierEtape && (int) $atelierEtape->nb_avis > 0)
                                        <span class="pp-frise__note">
                                            <i data-lucide="star" aria-hidden="true"></i>
                                            {{ $atelierEtape->noteFormatee() }}<span class="tr-sr"> sur 5</span> · {{ (int) $atelierEtape->nb_avis }} avis
                                        </span>
                                    @endif
                                </p>

                                @if ($etape->garantieJours() > 0)
                                    <p @class(['pp-frise__garantie', 'is-active' => $etape->garantieActive()])>
                                        <i data-lucide="shield-check" aria-hidden="true"></i>
                                        Garantie {{ $etape->garantieJours() }} jours ·
                                        {{ $etape->garantieActive() ? 'valable jusqu\'au' : 'expirée depuis le' }}
                                        {{ $etape->garantieFin()->locale('fr')->isoFormat('D MMMM YYYY') }}
                                    </p>
                                @endif
                                @if ($actuel)
                                    <span class="pp-frise__badge">Ce passeport</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <aside class="pp-cote" aria-label="À propos de ce passeport">
                <section class="pp-garanties tr-fil" aria-labelledby="pp-garanties-titre">
                    <h2 id="pp-garanties-titre">Ce que garantit ce passeport</h2>
                    <ul>
                        <li><i data-lucide="badge-check" aria-hidden="true"></i> La réparation a été faite et déclarée terminée par un atelier partenaire de TexTileCycle.</li>
                        <li><i data-lucide="lock" aria-hidden="true"></i> Les informations sont figées à la date de la réparation.</li>
                        <li><i data-lucide="eye-off" aria-hidden="true"></i> Aucune donnée personnelle du propriétaire n'est affichée.</li>
                    </ul>
                </section>

                <section class="pp-partage tr-fil" aria-labelledby="pp-partage-titre">
                    <h2 id="pp-partage-titre">Partager</h2>
                    <img class="pp-partage__qr" src="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" alt="QR code de ce passeport" width="140" height="140">
                    <p class="pp-partage__url">{{ route('front.passeport', ['code' => $passeport->code]) }}</p>
                    <p class="muted">Utile pour revendre ou donner la pièce : l'acheteur voit qu'elle a été réparée par un professionnel.</p>
                </section>
            </aside>
        </div>
    @endif
</main>
@endsection
