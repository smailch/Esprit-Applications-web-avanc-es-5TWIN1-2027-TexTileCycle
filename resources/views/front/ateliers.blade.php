@extends('layouts.front')

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Le réseau local</p>
            <h1>Ateliers près de vous</h1>
            <p class="muted">38 ateliers partenaires en Tunisie.</p>
        </div>
        <div class="search-box">
            <i data-lucide="search"></i>
            <input type="search" placeholder="Rechercher un atelier...">
        </div>
    </div>

    <div class="ai-banner">
        <i data-lucide="sparkles"></i>
        <div>
            <strong>Meilleur match pour vous</strong>
            <p><b>Couture Plus</b> · 95% de compatibilité · Spécialiste denim à 1,2 km</p>
        </div>
        <a href="{{ route('front.rdv') }}" class="btn btn-primary small">Prendre RDV</a>
    </div>

    <div class="workshop-layout">
        <x-map-preview :tall="true" />
        <div class="workshop-list">
            @foreach($workshops as $workshop)
                <article class="workshop-card">
                    <div class="workshop-avatar"><i data-lucide="wrench"></i></div>
                    <div class="workshop-meta">
                        <h3>{{ $workshop['name'] }}</h3>
                        <p><i data-lucide="map-pin"></i> {{ $workshop['place'] }} · {{ $workshop['distance'] }}</p>
                        <span>{{ $workshop['specialty'] }}</span>
                    </div>
                    <div class="rating"><i data-lucide="star"></i> {{ $workshop['rating'] }}</div>
                    <a href="{{ route('front.rdv') }}" class="icon-btn" aria-label="Prendre RDV"><i data-lucide="chevron-right"></i></a>
                </article>
            @endforeach
        </div>
    </div>
</main>
@endsection
