{{-- Résumé de l'espace atelier. Paramètre : $resumeRdv (RendezVousService::resumeAtelier). Requiert rdv-back.css. --}}
@php $enAttente = $resumeRdv['en_attente']; @endphp

<section class="panel rdv-resume" aria-labelledby="rdv-resume-titre">
    <div class="rdv-resume__head">
        <div>
            <h2 id="rdv-resume-titre">Prochains rendez-vous</h2>
            <p>
                @if ($enAttente > 0)
                    <span class="rdv-resume__count">{{ $enAttente }}</span>
                    {{ $enAttente > 1 ? 'demandes en attente de votre réponse' : 'demande en attente de votre réponse' }}
                @else
                    Aucune demande en attente
                @endif
            </p>
        </div>
        <a href="{{ route('back.rdv', $enAttente > 0 ? ['statut' => \App\Modules\RendezVous\Models\RendezVous::STATUT_EN_ATTENTE] : []) }}" class="btn btn-secondary small">
            <i data-lucide="calendar-days" aria-hidden="true"></i>
            {{ $enAttente > 0 ? 'Traiter les demandes' : 'Voir mes rendez-vous' }}
        </a>
    </div>

    @if ($resumeRdv['prochains']->isEmpty())
        <p class="rdv-resume__empty">Aucun rendez-vous à venir pour le moment.</p>
    @else
        <ul class="rdv-resume__list">
            @foreach ($resumeRdv['prochains'] as $rdv)
                <li>
                    <div class="rdv-when">
                        <b>{{ $rdv->dateFormatee() }} · {{ $rdv->heure }}</b>
                        <span>{{ $rdv->service?->nom ?? 'Service supprimé' }} · {{ $rdv->client?->name ?? 'Client introuvable' }}</span>
                    </div>
                    <x-status-badge :tone="$rdv->statutTone()">{{ $rdv->statutLabel() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</section>
