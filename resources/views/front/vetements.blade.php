@extends('layouts.front')

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Mon espace citoyen</p>
            <h1>Mes vêtements</h1>
            <p class="muted">Suivez toutes vos pièces et leur parcours.</p>
        </div>
        <button type="button" class="btn btn-primary" data-open-modal="clothing-modal"><i data-lucide="plus"></i> Déclarer un vêtement</button>
    </div>

    <div class="ai-banner">
        <i data-lucide="sparkles"></i>
        <div>
            <strong>Suggestion intelligente</strong>
            <p>Votre pantalon denim semble idéal pour une réparation chez <b>Couture Plus</b>.</p>
        </div>
        <a href="{{ route('front.ateliers') }}" class="link-button">Voir l'atelier <i data-lucide="arrow-right"></i></a>
    </div>

    <div class="clothes-grid">
        @foreach($clothes as $item)
            <article class="clothing-card">
                <div class="clothing-image">
                    <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}">
                    <button type="button" class="more-btn" aria-label="Actions"><i data-lucide="more-horizontal"></i></button>
                </div>
                <div class="clothing-info">
                    <div class="card-top">
                        <div>
                            <h3>{{ $item['name'] }}</h3>
                            <p>{{ $item['type'] }} · Taille {{ $item['size'] }}</p>
                        </div>
                        <x-status-badge :tone="$item['tone']">{{ $item['status'] }}</x-status-badge>
                    </div>
                    <div class="condition"><span>État</span><strong>{{ $item['condition'] }}</strong></div>
                    <div class="mini-timeline">
                        @for($i = 1; $i <= 4; $i++)
                            <span @class(['done' => $i <= $item['done'], 'current' => $i === $item['done'] + 1 && $item['done'] < 4])></span>
                        @endfor
                    </div>
                    <div class="timeline-labels">
                        <span>Déclaré</span>
                        <span>{{ $item['status'] === 'Donné' ? 'Terminé' : 'En cours' }}</span>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</main>

<x-clothing-modal />
@endsection
