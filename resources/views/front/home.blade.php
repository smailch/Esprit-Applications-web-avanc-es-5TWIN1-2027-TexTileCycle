@extends('layouts.front')

@section('content')
<main>
    <section class="hero container">
        <div class="hero-copy">
            <div class="pill"><i data-lucide="sparkles"></i> L'économie circulaire, simplement</div>
            <h1>Donnez une seconde vie à <em>vos vêtements.</em></h1>
            <p>Réparez, donnez et suivez l'impact de chaque pièce. Ensemble, construisons une mode plus durable en Tunisie.</p>
            <div class="hero-actions">
                <a href="{{ route('front.vetements') }}" class="btn btn-primary">Déclarer un vêtement <i data-lucide="arrow-right"></i></a>
                <a href="{{ route('front.ateliers') }}" class="btn btn-secondary"><i data-lucide="map-pin"></i> Trouver un atelier</a>
            </div>
            <div class="trust-row">
                <div class="avatar-stack"><span>Y</span><span>S</span><span>M</span><span>+</span></div>
                <span>Rejoignez <strong>1 240 citoyens</strong> engagés</span>
            </div>
        </div>
        <div class="hero-art">
            <div class="orbit orbit-one"></div>
            <div class="orbit orbit-two"></div>
            <div class="leaf-card"><i data-lucide="leaf"></i><span>Impact positif</span><strong>+ 2,3 t CO₂</strong></div>
            <div class="fabric-card fabric-one"><i data-lucide="shirt"></i><div><b>Veste en jean</b><small>Réparée avec succès</small></div><i data-lucide="check"></i></div>
            <div class="fabric-card fabric-two"><i data-lucide="recycle"></i><div><b>Cycle terminé</b><small>Une pièce sauvée</small></div></div>
        </div>
    </section>

    <section class="stats-strip">
        <div class="container stats-grid">
            <div><strong>1 240</strong><span>vêtements sauvés</span></div>
            <div><strong>856</strong><span>réparations réalisées</span></div>
            <div><strong>384</strong><span>dons redistribués</span></div>
            <div><strong>2,3 t</strong><span>de CO₂ évitées</span></div>
        </div>
    </section>

    <section class="section container">
        <div class="section-heading">
            <div>
                <p class="kicker">Le parcours TexTileCycle</p>
                <h2>Chaque geste compte.</h2>
            </div>
            <p>Une expérience simple pour transformer vos habitudes et mesurer votre impact.</p>
        </div>
        <div class="steps-grid">
            @foreach([
                ['01', 'Déclarer', 'Dites-nous ce que vous avez dans votre armoire.', 'shirt'],
                ['02', 'Réparer ou donner', 'Choisissez la meilleure seconde vie.', 'wrench'],
                ['03', 'Suivre le parcours', 'Restez informé à chaque étape.', 'recycle'],
                ['04', 'Mesurer l\'impact', 'Visualisez votre contribution.', 'leaf'],
            ] as [$num, $title, $desc, $icon])
                <div class="step">
                    <span class="step-num">{{ $num }}</span>
                    <div class="step-icon"><i data-lucide="{{ $icon }}"></i></div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="impact-section">
        <div class="container impact-layout">
            <div>
                <p class="kicker">Un réseau local</p>
                <h2>Près de chez vous,<br><em>un impact réel.</em></h2>
                <p class="muted">Découvrez les ateliers et associations qui font bouger la mode en Tunisie.</p>
                <a href="{{ route('front.ateliers') }}" class="btn btn-primary">Explorer la carte <i data-lucide="arrow-right"></i></a>
            </div>
            <x-map-preview />
        </div>
    </section>

    <section class="section container">
        <div class="section-heading centered">
            <p class="kicker">Pourquoi nous rejoindre</p>
            <h2>La mode autrement.</h2>
            <p>Des outils concrets pour prolonger la vie de vos vêtements.</p>
        </div>
        <div class="benefits-grid">
            <div class="benefit-card">
                <div class="benefit-icon"><i data-lucide="recycle"></i></div>
                <h3>Économie circulaire</h3>
                <p>Réduisez le gaspillage en donnant une nouvelle histoire à chaque pièce.</p>
                <a href="#">En savoir plus <i data-lucide="arrow-right"></i></a>
            </div>
            <div class="benefit-card featured">
                <div class="benefit-icon"><i data-lucide="users"></i></div>
                <h3>Ateliers locaux</h3>
                <p>Un réseau de savoir-faire près de chez vous, pour une mode qui crée du lien.</p>
                <a href="{{ route('front.ateliers') }}">Découvrir le réseau <i data-lucide="arrow-right"></i></a>
            </div>
            <div class="benefit-card">
                <div class="benefit-icon"><i data-lucide="bar-chart-3"></i></div>
                <h3>Impact mesurable</h3>
                <p>Chaque action est comptabilisée pour rendre votre engagement visible.</p>
                <a href="#">Voir les chiffres <i data-lucide="arrow-right"></i></a>
            </div>
        </div>
    </section>
</main>
@endsection
