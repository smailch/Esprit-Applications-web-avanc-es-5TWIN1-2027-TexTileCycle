@extends('layouts.front')

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Solidarité textile</p>
            <h1>{{ $donation ? 'Proposer un don' : 'Associations partenaires' }}</h1>
            <p class="muted">
                {{ $donation
                    ? 'Trouvez l\'association qui donnera le plus de sens à votre vêtement.'
                    : 'Des besoins réels, des dons qui comptent.' }}
            </p>
        </div>
    </div>

    <div class="association-grid">
        @foreach([
            ['Solidarité Mode', 'Recherche pulls taille L', '98%', 'Pulls, manteaux', 'Espace Elan'],
            ['Friperie Solidaire', 'Besoin de vêtements enfants', '94%', 'Enfants 4–12 ans', 'Amitié Sans Frontières'],
            ['Les Petites Mains', 'Collecte textile créatif', '88%', 'Tissus, boutons', 'Atelier social'],
        ] as [$name, $need, $score, $types, $org])
            <article class="association-card">
                <div class="association-head">
                    <div class="association-logo"><i data-lucide="heart-handshake"></i></div>
                    <x-status-badge tone="purple">{{ $score }} match</x-status-badge>
                </div>
                <h3>{{ $name }}</h3>
                <p>{{ $need }}</p>
                <div class="tag-row"><span>{{ $types }}</span><span>{{ $org }}</span></div>
                <button type="button" class="btn btn-secondary full">
                    {{ $donation ? 'Proposer mon vêtement' : 'Voir les besoins' }}
                    <i data-lucide="arrow-right"></i>
                </button>
            </article>
        @endforeach
    </div>
</main>
@endsection
