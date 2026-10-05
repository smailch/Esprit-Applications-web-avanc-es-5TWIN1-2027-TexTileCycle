@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
    <link rel="stylesheet" href="{{ asset('css/rdv-back.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/rdv-back.js') }}" defer></script>
@endpush

@php
    $statutActif = $filters['statut'] ?? null;
    $periodeActive = $filters['periode'] ?? null;
    $aDesFiltres = $filters !== [];
    $avecAtelier = fn (array $params) => array_filter($params + ['atelier' => $filters['atelier'] ?? null]);
    $cartes = [
        ['statut' => \App\Modules\RendezVous\Models\RendezVous::STATUT_EN_ATTENTE, 'label' => 'En attente', 'valeur' => $stats[\App\Modules\RendezVous\Models\RendezVous::STATUT_EN_ATTENTE], 'icon' => 'hourglass', 'tone' => 'orange', 'detail' => 'à confirmer ou refuser'],
        ['statut' => \App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME, 'label' => 'Confirmés', 'valeur' => $stats[\App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME], 'icon' => 'calendar-check', 'tone' => 'blue', 'detail' => 'créneaux réservés'],
        ['statut' => null, 'label' => "Aujourd'hui", 'valeur' => $stats['aujourdhui'], 'icon' => 'calendar-clock', 'tone' => 'green', 'detail' => 'rendez-vous du jour'],
        ['statut' => \App\Modules\RendezVous\Models\RendezVous::STATUT_TERMINE, 'label' => 'Terminés', 'valeur' => $stats[\App\Modules\RendezVous\Models\RendezVous::STATUT_TERMINE], 'icon' => 'circle-check', 'tone' => 'purple', 'detail' => 'prestations réalisées'],
    ];
    $periodes = [
        \App\Modules\RendezVous\Services\RendezVousService::PERIODE_A_VENIR => 'À venir',
        \App\Modules\RendezVous\Services\RendezVousService::PERIODE_PASSES => 'Passés',
    ];
@endphp

@section('content')
<div class="ab-page">
    @include('back.ateliers.partials.flash')

    @if ($atelier)
        <p class="rdv-scope"><i data-lucide="store" aria-hidden="true"></i> Rendez-vous de <strong>{{ $atelier->nom }}</strong></p>
    @endif

    <ul class="stats-grid four ab-stats ab-stats--list rdv-stats" aria-label="Résumé des rendez-vous">
        @foreach ($cartes as $carte)
            <li>
                @if ($carte['statut'])
                    <a href="{{ route('back.rdv', $avecAtelier(['statut' => $carte['statut']])) }}"
                       @class(['stat-card', 'ab-stat', 'is-current' => $statutActif === $carte['statut']])
                       @if ($statutActif === $carte['statut']) aria-current="true" @endif
                       aria-label="{{ $carte['label'] }} : {{ $carte['valeur'] }}. Afficher ces rendez-vous.">
                @else
                    <div class="stat-card ab-stat ab-stat--static">
                @endif
                    <div @class(['stat-icon', $carte['tone']]) aria-hidden="true"><i data-lucide="{{ $carte['icon'] }}"></i></div>
                    <div>
                        <p class="eyebrow">{{ $carte['label'] }}</p>
                        <strong>{{ $carte['valeur'] }}</strong>
                        <small>{{ $carte['detail'] }}</small>
                    </div>
                @if ($carte['statut'])
                    </a>
                @else
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    <div class="module-toolbar ab-toolbar">
        <form method="get" action="{{ route('back.rdv') }}" class="ab-filters" aria-label="Filtrer les rendez-vous">
            <label for="rdv-statut" class="sr-only">Filtrer par statut</label>
            <select id="rdv-statut" name="statut" class="ab-select">
                <option value="">Tous les statuts</option>
                @foreach (\App\Modules\RendezVous\Models\RendezVous::STATUTS as $statut)
                    <option value="{{ $statut }}" @selected($statutActif === $statut)>{{ \App\Modules\RendezVous\Models\RendezVous::libelleStatut($statut) }}</option>
                @endforeach
            </select>

            <label for="rdv-periode" class="sr-only">Filtrer par période</label>
            <select id="rdv-periode" name="periode" class="ab-select">
                <option value="">Toutes les dates</option>
                @foreach ($periodes as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected($periodeActive === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>

            @if ($estAdmin)
                <label for="rdv-atelier" class="sr-only">Filtrer par atelier</label>
                <select id="rdv-atelier" name="atelier" class="ab-select">
                    <option value="">Tous les ateliers</option>
                    @foreach ($ateliersFiltre as $choix)
                        <option value="{{ $choix->getKey() }}" @selected(($filters['atelier'] ?? null) === (string) $choix->getKey())>{{ $choix->nom }}@if ($choix->ville) ({{ $choix->ville }})@endif</option>
                    @endforeach
                </select>
            @endif

            <button type="submit" class="btn btn-secondary small"><i data-lucide="filter" aria-hidden="true"></i> Filtrer</button>
            @if ($aDesFiltres)
                <a href="{{ route('back.rdv') }}" class="link-button ab-reset"><i data-lucide="x" aria-hidden="true"></i> Effacer les filtres</a>
            @endif
        </form>
    </div>

    @if ($rdvs->isEmpty())
        <div class="panel ab-empty">
            <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="{{ $aDesFiltres ? 'search-x' : 'calendar-days' }}"></i></div>
            @if ($aDesFiltres)
                <h2>Aucun rendez-vous ne correspond à ces filtres</h2>
                <p>Essayez un autre statut ou une autre période.</p>
                <a href="{{ route('back.rdv') }}" class="btn btn-secondary"><i data-lucide="rotate-ccw" aria-hidden="true"></i> Effacer les filtres</a>
            @else
                <h2>Aucun rendez-vous pour le moment</h2>
                <p>Les demandes envoyées depuis la fiche publique {{ $atelier ? 'de votre atelier' : 'des ateliers' }} apparaîtront ici.</p>
            @endif
        </div>
    @else
        <div class="panel table-panel ab-table-panel">
            <div class="panel-head">
                <div>
                    <h2>{{ $rdvs->total() }} rendez-vous</h2>
                    <p>
                        {{ $statutActif ? 'Statut : '.\App\Modules\RendezVous\Models\RendezVous::libelleStatut($statutActif) : 'Tous les statuts' }}
                        · {{ $periodeActive ? $periodes[$periodeActive] : 'Toutes les dates' }}
                        · heure de Tunis
                    </p>
                </div>
            </div>
            <table class="ab-table rdv-table">
                <caption class="sr-only">Rendez-vous, page {{ $rdvs->currentPage() }} sur {{ $rdvs->lastPage() }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Date et heure</th>
                        @if ($estAdmin)
                            <th scope="col">Atelier</th>
                        @endif
                        <th scope="col">Client</th>
                        <th scope="col">Vêtement</th>
                        <th scope="col">Service</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rdvs as $rdv)
                        @php
                            $rdvId = (string) $rdv->getKey();
                            $quand = $rdv->dateFormatee().' à '.$rdv->heure;
                            $client = $rdv->client;
                            $service = $rdv->service;
                            $libelleClient = $client?->name ?? 'Client introuvable';
                        @endphp
                        <tr>
                            <td data-label="Date et heure" class="ab-cell-primary">
                                <div class="rdv-when">
                                    <b>{{ $rdv->dateFormatee() }}</b>
                                    <span>
                                        {{ $rdv->heure }} – {{ $rdv->heureFin() }}
                                        @if ($rdv->date === $aujourdhui)
                                            <span class="rdv-today">Aujourd'hui</span>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            @if ($estAdmin)
                                <td data-label="Atelier">{{ $rdv->atelier?->nom ?? 'Atelier supprimé' }}</td>
                            @endif
                            <td data-label="Client">
                                <div class="ab-contact">
                                    <b>{{ $libelleClient }}</b>
                                    @if ($client?->phone)
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $client->phone) }}">{{ $client->phone }}</a>
                                    @elseif ($client?->email)
                                        <span>{{ $client->email }}</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Vêtement">
                                <div class="rdv-detail">
                                    @if ($rdv->vetement)
                                        <span>{{ $rdv->vetement->displayName() }}</span>
                                    @else
                                        <span class="ab-muted">Non précisé</span>
                                    @endif
                                    @if (filled($rdv->notes))
                                        <span class="rdv-note" title="{{ $rdv->notes }}">« {{ \Illuminate\Support\Str::limit($rdv->notes, 90) }} »</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Service">
                                <div class="rdv-detail">
                                    <b>{{ $service?->nom ?? 'Service supprimé' }}</b>
                                    <span class="rdv-meta">
                                        {{ \App\Modules\Ateliers\Models\Service::formatDuree($rdv->dureeMinutes()) }}
                                        @if ($service?->prixFormate())
                                            · {{ $service->prixFormate() }}
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td data-label="Statut">
                                <div class="rdv-detail">
                                    <x-status-badge :tone="$rdv->statutTone()">{{ $rdv->statutLabel() }}</x-status-badge>
                                    @if (filled($rdv->motif))
                                        <span class="rdv-meta">Motif : {{ $rdv->motif }}</span>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Actions" class="ab-actions-cell">
                                <div class="ab-actions">
                                    @if ($rdv->peutPasserA(\App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME))
                                        <form method="post" action="{{ route('back.rdv.statut', ['id' => $rdvId]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="statut" value="{{ \App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME }}">
                                            <button type="submit" class="btn btn-primary small" aria-label="Confirmer le rendez-vous de {{ $libelleClient }} le {{ $quand }}">
                                                <i data-lucide="check" aria-hidden="true"></i> Confirmer
                                            </button>
                                        </form>
                                    @endif
                                    @if ($rdv->peutPasserA(\App\Modules\RendezVous\Models\RendezVous::STATUT_REFUSE))
                                        <form method="post" action="{{ route('back.rdv.statut', ['id' => $rdvId]) }}"
                                              data-motif-form data-motif-requis="1"
                                              data-titre="Refuser le rendez-vous de {{ $libelleClient }} le {{ $quand }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="statut" value="{{ \App\Modules\RendezVous\Models\RendezVous::STATUT_REFUSE }}">
                                            <input type="hidden" name="motif" value="">
                                            <button type="submit" class="icon-btn is-danger" title="Refuser" aria-label="Refuser le rendez-vous de {{ $libelleClient }} le {{ $quand }}">
                                                <i data-lucide="x" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if ($rdv->peutPasserA(\App\Modules\RendezVous\Models\RendezVous::STATUT_TERMINE))
                                        <form method="post" action="{{ route('back.rdv.statut', ['id' => $rdvId]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="statut" value="{{ \App\Modules\RendezVous\Models\RendezVous::STATUT_TERMINE }}">
                                            <button type="submit" class="btn btn-secondary small" aria-label="Marquer comme terminé le rendez-vous de {{ $libelleClient }} le {{ $quand }}">
                                                <i data-lucide="check-check" aria-hidden="true"></i> Terminé
                                            </button>
                                        </form>
                                    @endif
                                    @if ($rdv->peutPasserA(\App\Modules\RendezVous\Models\RendezVous::STATUT_ANNULE))
                                        <form method="post" action="{{ route('back.rdv.statut', ['id' => $rdvId]) }}"
                                              data-motif-form data-motif-requis="0"
                                              data-titre="Annuler le rendez-vous de {{ $libelleClient }} le {{ $quand }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="statut" value="{{ \App\Modules\RendezVous\Models\RendezVous::STATUT_ANNULE }}">
                                            <input type="hidden" name="motif" value="">
                                            <button type="submit" class="icon-btn is-warning" title="Annuler" aria-label="Annuler le rendez-vous de {{ $libelleClient }} le {{ $quand }}">
                                                <i data-lucide="calendar-x" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if (! array_key_exists((string) $rdv->statut, \App\Modules\RendezVous\Models\RendezVous::TRANSITIONS))
                                        <span class="ab-muted">Aucune action</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ab-pagination">
            {{ $rdvs->links('vendor.pagination.textilecycle') }}
        </div>
    @endif
</div>

<div class="modal-backdrop" id="rdv-motif-modal" aria-hidden="true" data-motif-modal>
    <div class="modal ab-modal" role="dialog" aria-modal="true" aria-labelledby="rdv-motif-title">
        <div class="modal-head">
            <div>
                <p class="kicker">Rendez-vous</p>
                <h2 id="rdv-motif-title" data-motif-title>Refuser le rendez-vous</h2>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer la fenêtre"><i data-lucide="x" aria-hidden="true"></i></button>
        </div>
        <div class="ab-modal__form">
            <div class="ab-field">
                <label for="rdv-motif" data-motif-label>Motif</label>
                <textarea id="rdv-motif" maxlength="{{ \App\Modules\RendezVous\Services\RendezVousService::MOTIF_MAX }}" rows="3" aria-describedby="rdv-motif-hint" data-motif-input></textarea>
                <p class="ab-hint" id="rdv-motif-hint" data-motif-hint>{{ \App\Modules\RendezVous\Services\RendezVousService::MOTIF_MAX }} caractères maximum, enregistrés avec le rendez-vous.</p>
                <p class="ab-error" data-motif-error hidden>Indiquez le motif du refus.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Retour</button>
                <button type="button" class="btn ab-btn-danger" data-motif-confirm><i data-lucide="check" aria-hidden="true"></i> Valider</button>
            </div>
        </div>
    </div>
</div>
@endsection
