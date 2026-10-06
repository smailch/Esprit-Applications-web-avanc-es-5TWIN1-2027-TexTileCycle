@extends('layouts.front')

@section('title', 'Historique du cycle de vie — TexTileCycle')

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Mon espace citoyen</p>
            <h1>Cycle de vie</h1>
            <p class="muted">Historique complet de vos vêtements après une réparation validée ou un don accepté.</p>
        </div>
        <a href="{{ route('front.vetements') }}" class="btn btn-secondary"><i data-lucide="shirt"></i> Mes vêtements</a>
    </div>

    <div class="history-filters" role="navigation" aria-label="Filtrer l'historique">
        @foreach($filtres as $valeur => $libelle)
            <a href="{{ $valeur === '' ? route('front.historique') : route('front.historique', ['statut' => $valeur]) }}"
               class="history-filter {{ ($filtre ?: '') === $valeur ? 'is-active' : '' }}">
                {{ $libelle }}
            </a>
        @endforeach
    </div>

    <div class="history-list">
        @forelse($vetements as $vetement)
            <article class="history-card">
                <div class="history-card-head">
                    <div class="history-thumb">
                        <img src="{{ $vetement->photoUrl() }}" alt="{{ $vetement->displayName() }}">
                    </div>
                    <div class="history-meta">
                        <div class="card-top">
                            <div>
                                <h3>{{ $vetement->displayName() }}</h3>
                                <p>{{ $vetement->material ?? $vetement->type }} · Taille {{ $vetement->size }}</p>
                            </div>
                            <x-status-badge :tone="$vetement->statusTone()">{{ $vetement->statusLabel() }}</x-status-badge>
                        </div>
                        <p class="muted small">{{ $vetement->cycleEvents->count() }} étape{{ $vetement->cycleEvents->count() > 1 ? 's' : '' }} dans le parcours</p>
                    </div>
                </div>

                <ol class="cycle-timeline">
                    @forelse($vetement->cycleEvents as $event)
                        <li class="cycle-step {{ $event->step_key === 'termine' ? 'is-done' : '' }}">
                            <span class="cycle-dot" aria-hidden="true">
                                <i data-lucide="{{ $event->icon() }}"></i>
                            </span>
                            <div class="cycle-step-body">
                                <div class="cycle-step-top">
                                    <b>{{ $event->title }}</b>
                                    @if($event->occurredLabel())
                                        <time datetime="{{ $event->occurred_at?->toIso8601String() }}">{{ $event->occurredLabel() }}</time>
                                    @endif
                                </div>
                                @if($event->description)
                                    <p>{{ $event->description }}</p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="cycle-step">
                            <span class="cycle-dot" aria-hidden="true"><i data-lucide="circle"></i></span>
                            <div class="cycle-step-body">
                                <b>{{ $vetement->statusLabel() }}</b>
                                <p>Aucun détail d'étape n'a encore été enregistré pour cette pièce.</p>
                            </div>
                        </li>
                    @endforelse
                </ol>
            </article>
        @empty
            <div class="panel history-empty">
                <i data-lucide="history"></i>
                <h2>Aucun cycle clôturé pour le moment</h2>
                <p>Dès qu'un atelier valide une réparation ou qu'une association accepte un don, la pièce apparaît ici avec tout son historique.</p>
                <a href="{{ route('front.vetements') }}" class="btn btn-primary"><i data-lucide="plus"></i> Déclarer un vêtement</a>
            </div>
        @endforelse
    </div>
</main>
@endsection
