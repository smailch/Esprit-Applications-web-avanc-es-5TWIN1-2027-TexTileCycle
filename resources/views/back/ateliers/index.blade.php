@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@php
    $cartes = [
        ['statut' => null, 'label' => 'Total', 'valeur' => $stats['total'], 'icon' => 'wrench', 'tone' => 'green', 'detail' => 'ateliers enregistrés'],
        ['statut' => \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF, 'label' => 'Actifs', 'valeur' => $stats[\App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF], 'icon' => 'circle-check', 'tone' => 'blue', 'detail' => 'visibles sur le site'],
        ['statut' => \App\Modules\Ateliers\Models\Atelier::STATUT_EN_ATTENTE, 'label' => 'En attente', 'valeur' => $stats[\App\Modules\Ateliers\Models\Atelier::STATUT_EN_ATTENTE], 'icon' => 'hourglass', 'tone' => 'orange', 'detail' => 'à valider'],
        ['statut' => \App\Modules\Ateliers\Models\Atelier::STATUT_SUSPENDU, 'label' => 'Suspendus', 'valeur' => $stats[\App\Modules\Ateliers\Models\Atelier::STATUT_SUSPENDU], 'icon' => 'circle-pause', 'tone' => 'purple', 'detail' => 'masqués du site'],
    ];
    $statutActif = $filters['statut'] ?? null;
    $aDesFiltres = $filters !== [];
@endphp

@section('content')
<div class="ab-page">
    @include('back.ateliers.partials.flash')

    <div class="stats-grid four ab-stats">
        @foreach ($cartes as $carte)
            <a href="{{ route('back.ateliers', array_filter(['statut' => $carte['statut'], 'q' => $filters['q'] ?? null])) }}"
               @class(['stat-card', 'ab-stat', 'is-current' => $statutActif === $carte['statut']])
               @if ($statutActif === $carte['statut']) aria-current="true" @endif
               aria-label="{{ $carte['label'] }} : {{ $carte['valeur'] }} {{ $carte['detail'] }}. Afficher ces ateliers.">
                <div @class(['stat-icon', $carte['tone']]) aria-hidden="true"><i data-lucide="{{ $carte['icon'] }}"></i></div>
                <div aria-hidden="true">
                    <p class="eyebrow">{{ $carte['label'] }}</p>
                    <strong>{{ $carte['valeur'] }}</strong>
                    <small>{{ $carte['detail'] }}</small>
                </div>
            </a>
        @endforeach
    </div>

    <div class="module-toolbar ab-toolbar">
        <form method="get" action="{{ route('back.ateliers') }}" class="ab-filters" role="search" aria-label="Filtrer les ateliers">
            <div class="search-box compact">
                <i data-lucide="search" aria-hidden="true"></i>
                <label for="ab-q" class="sr-only">Rechercher par nom, ville, spécialité ou adresse</label>
                <input id="ab-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nom, ville, spécialité…">
            </div>
            <label for="ab-statut" class="sr-only">Filtrer par statut</label>
            <select id="ab-statut" name="statut" class="ab-select">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $statut)
                    <option value="{{ $statut }}" @selected($statutActif === $statut)>{{ \App\Modules\Ateliers\Models\Atelier::libelleStatut($statut) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary small"><i data-lucide="filter" aria-hidden="true"></i> Filtrer</button>
            @if ($aDesFiltres)
                <a href="{{ route('back.ateliers') }}" class="link-button ab-reset"><i data-lucide="x" aria-hidden="true"></i> Effacer les filtres</a>
            @endif
        </form>
        <a href="{{ route('back.ateliers.create') }}" class="btn btn-primary"><i data-lucide="plus" aria-hidden="true"></i> Nouvel atelier</a>
    </div>

    @if ($ateliers->isEmpty())
        <div class="panel ab-empty">
            <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="{{ $aDesFiltres ? 'search-x' : 'store' }}"></i></div>
            @if ($aDesFiltres)
                <h2>Aucun atelier ne correspond à ces filtres</h2>
                <p>Essayez un autre mot-clé ou affichez tous les statuts.</p>
                <a href="{{ route('back.ateliers') }}" class="btn btn-secondary"><i data-lucide="rotate-ccw" aria-hidden="true"></i> Effacer les filtres</a>
            @else
                <h2>Aucun atelier pour le moment</h2>
                <p>Créez le premier atelier partenaire en le rattachant à un compte de rôle atelier.</p>
                <a href="{{ route('back.ateliers.create') }}" class="btn btn-primary"><i data-lucide="plus" aria-hidden="true"></i> Nouvel atelier</a>
            @endif
        </div>
    @else
        <div class="panel table-panel ab-table-panel">
            <div class="panel-head">
                <div>
                    <h2>{{ $ateliers->total() }} {{ $ateliers->total() > 1 ? 'ateliers' : 'atelier' }}</h2>
                    <p>{{ $statutActif ? 'Statut : '.\App\Modules\Ateliers\Models\Atelier::libelleStatut($statutActif) : 'Tous les statuts' }}@if (isset($filters['q'])) · recherche « {{ $filters['q'] }} »@endif</p>
                </div>
            </div>
            <table class="ab-table">
                <caption class="sr-only">Liste des ateliers, page {{ $ateliers->currentPage() }} sur {{ $ateliers->lastPage() }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Atelier</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Services</th>
                        <th scope="col">Note</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ateliers as $atelier)
                        @php
                            $id = (string) $atelier->getKey();
                            $nbServices = $atelier->services->count();
                        @endphp
                        <tr>
                            <td data-label="Atelier">
                                <div class="table-item">
                                    <div class="ab-avatar ab-avatar--{{ $atelier->avatarTone() }}" aria-hidden="true">{{ $atelier->initiales() }}</div>
                                    <div class="ab-identity">
                                        <b>{{ $atelier->nom }}</b>
                                        <span><i data-lucide="map-pin" aria-hidden="true"></i> {{ $atelier->ville }}</span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Contact">
                                <div class="ab-contact">
                                    @if ($atelier->telephoneLien())
                                        <a href="{{ $atelier->telephoneLien() }}">{{ $atelier->telephone }}</a>
                                    @endif
                                    <span>{{ $atelier->user?->email ?? 'Compte introuvable' }}</span>
                                </div>
                            </td>
                            <td data-label="Services">
                                <span class="ab-count">{{ $nbServices }} <span class="ab-count__label">{{ $nbServices > 1 ? 'services' : 'service' }}</span></span>
                            </td>
                            <td data-label="Note">
                                <span class="rating">
                                    <i data-lucide="star" aria-hidden="true"></i>
                                    {{ $atelier->noteFormatee() }}<span class="sr-only"> sur 5</span>
                                    <span class="ab-reviews">({{ (int) $atelier->nb_avis }} avis)</span>
                                </span>
                            </td>
                            <td data-label="Statut">
                                <x-status-badge :tone="$atelier->statutTone()">{{ $atelier->statutLabel() }}</x-status-badge>
                            </td>
                            <td data-label="Actions" class="ab-actions-cell">
                                <div class="ab-actions">
                                    @if ($atelier->statut === \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF)
                                        <a href="{{ route('front.ateliers.show', ['id' => $id]) }}" class="icon-btn" target="_blank" rel="noopener" aria-label="Voir la fiche publique de {{ $atelier->nom }} (nouvel onglet)" title="Fiche publique">
                                            <i data-lucide="external-link" aria-hidden="true"></i>
                                        </a>
                                    @endif

                                    <a href="{{ route('back.ateliers.edit', ['id' => $id]) }}" class="icon-btn" aria-label="Modifier {{ $atelier->nom }}" title="Modifier">
                                        <i data-lucide="pencil" aria-hidden="true"></i>
                                    </a>

                                    @php
                                        $transition = match ($atelier->statut) {
                                            \App\Modules\Ateliers\Models\Atelier::STATUT_EN_ATTENTE => ['statut' => \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF, 'verbe' => 'Valider', 'icon' => 'check', 'tone' => 'is-success'],
                                            \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF => ['statut' => \App\Modules\Ateliers\Models\Atelier::STATUT_SUSPENDU, 'verbe' => 'Suspendre', 'icon' => 'pause', 'tone' => 'is-warning'],
                                            default => ['statut' => \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF, 'verbe' => 'Réactiver', 'icon' => 'play', 'tone' => 'is-success'],
                                        };
                                    @endphp
                                    <form method="post" action="{{ route('back.ateliers.statut', ['id' => $id]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="statut" value="{{ $transition['statut'] }}">
                                        <button type="submit" class="icon-btn {{ $transition['tone'] }}" aria-label="{{ $transition['verbe'] }} {{ $atelier->nom }}" title="{{ $transition['verbe'] }}">
                                            <i data-lucide="{{ $transition['icon'] }}" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="post" action="{{ route('back.ateliers.destroy', ['id' => $id]) }}"
                                          data-confirm-delete
                                          data-nom="{{ $atelier->nom }}"
                                          data-services="{{ $nbServices }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn is-danger" aria-label="Supprimer {{ $atelier->nom }}" title="Supprimer">
                                            <i data-lucide="trash-2" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ab-pagination">
            {{ $ateliers->links('vendor.pagination.textilecycle') }}
        </div>
    @endif
</div>

@include('back.ateliers.partials.delete-modal', ['cible' => 'atelier'])
@endsection

@push('scripts')
    <script src="{{ asset('js/ateliers-back.js') }}"></script>
@endpush
