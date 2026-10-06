@extends('layouts.front')

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Mon espace citoyen</p>
            <h1>Mes vêtements</h1>
            <p class="muted">Suivez vos pièces en cours. Les réparations et dons validés passent dans l'historique.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('front.historique') }}" class="btn btn-secondary"><i data-lucide="history"></i> Historique</a>
            <button type="button" class="btn btn-primary" data-open-modal="clothing-modal"><i data-lucide="plus"></i> Déclarer un vêtement</button>
        </div>
    </div>

    @if($vetements->isNotEmpty())
    @php($vetementAReparer = $vetements->first(fn ($v) => $v->intended_action === 'reparation'))
    <div class="ai-banner">
        <i data-lucide="sparkles"></i>
        <div>
            <strong>Suggestion intelligente</strong>
            @if($vetementAReparer)
                <p>Trouvez un atelier pour réparer « {{ $vetementAReparer->displayName() }} ». Le rendez-vous sera prérempli.</p>
            @else
                <p>Consultez les ateliers partenaires pour faire réparer vos pièces.</p>
            @endif
        </div>
        <a href="{{ $vetementAReparer?->nextStepUrl() ?? route('front.ateliers') }}" class="link-button">Trouver un atelier <i data-lucide="arrow-right"></i></a>
    </div>
    @endif

    <div class="clothes-grid">
        @forelse($vetements as $vetement)
            @php($done = $vetement->timelineProgress())
            <article class="clothing-card">
                <div class="clothing-image">
                    <img src="{{ $vetement->photoUrl() }}" alt="{{ $vetement->displayName() }}">
                    <button type="button" class="more-btn" aria-label="Actions"><i data-lucide="more-horizontal"></i></button>
                </div>
                <div class="clothing-info">
                    <div class="card-top">
                        <div>
                            <h3>{{ $vetement->displayName() }}</h3>
                            <p>{{ $vetement->type }} · Taille {{ $vetement->size }}</p>
                        </div>
                        <x-status-badge :tone="$vetement->statusTone()">{{ $vetement->statusLabel() }}</x-status-badge>
                    </div>
                    <div class="condition"><span>État</span><strong>{{ $vetement->condition_label ?? '—' }}</strong></div>
                    <div class="condition" style="margin-top:6px"><span>Matière</span><strong>{{ $vetement->material ?? '—' }}</strong></div>
                    <div class="condition" style="margin-top:8px">
                        <span>Parcours</span>
                        <x-status-badge :tone="$vetement->intendedActionTone()">{{ $vetement->intendedActionLabel() }}</x-status-badge>
                    </div>
                    <div class="mini-timeline">
                        @for($i = 1; $i <= 4; $i++)
                            <span @class(['done' => $i <= $done, 'current' => $i === $done + 1 && $done < 4])></span>
                        @endfor
                    </div>
                    <div class="timeline-labels">
                        <span>{{ $vetement->cycleEvents->first()?->title ?? 'Déclaré' }}</span>
                        <span>{{ $vetement->cycleEvents->last()?->title ?? $vetement->statusLabel() }}</span>
                    </div>
                    <div class="card-actions" style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                        @unless($vetement->estDansHistorique())
                        <form method="post" action="{{ route('front.vetements.action', $vetement->getKey()) }}" style="display:flex;gap:6px;flex-wrap:wrap">
                            @csrf
                            @method('PATCH')
                            <button type="submit" name="intended_action" value="reparation" class="btn btn-secondary small @if($vetement->intended_action === 'reparation') active-choice @endif">
                                <i data-lucide="wrench"></i> Réparation
                            </button>
                            <button type="submit" name="intended_action" value="don" class="btn btn-secondary small @if($vetement->intended_action === 'don') active-choice @endif">
                                <i data-lucide="gift"></i> Don
                            </button>
                        </form>
                        @if($vetement->nextStepUrl())
                            <a href="{{ $vetement->nextStepUrl() }}" class="btn btn-primary small">{{ $vetement->nextStepLabel() }}</a>
                        @endif
                        @else
                            <a href="{{ route('front.historique') }}" class="btn btn-secondary small"><i data-lucide="history"></i> Voir l'historique</a>
                        @endunless
                    </div>
                </div>
            </article>
        @empty
            <div class="panel" style="grid-column:1/-1;padding:32px;text-align:center;color:var(--muted)">
                <p>Aucun vêtement en cours.</p>
                <p style="margin-top:8px">Les pièces réparées ou données se retrouvent dans <a href="{{ route('front.historique') }}" style="color:var(--green);font-weight:600">l'historique du cycle de vie</a>.</p>
            </div>
        @endforelse
    </div>
</main>

<x-clothing-modal />
@endsection

@if($errors->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('clothing-modal');
                if (modal) {
                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }
            });
        </script>
    @endpush
@endif
