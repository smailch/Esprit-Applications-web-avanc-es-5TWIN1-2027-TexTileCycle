@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/ateliers-back.js') }}" defer></script>
@endpush

@php
    $edition = $modale['mode'] === \App\Modules\Ateliers\Http\Controllers\Back\MonServiceController::MODALE_EDITION;
    $serviceModale = $modale['service'];
    $valeur = fn (string $champ) => $modale['ouverte'] ? old($champ, $serviceModale?->{$champ}) : '';
    $cartes = [
        ['label' => 'Services', 'valeur' => $resume['nombre'], 'detail' => 'au catalogue', 'icon' => 'scissors', 'tone' => 'green'],
        ['label' => 'Prix minimal', 'valeur' => $resume['prixMinimal'] ?? '–', 'detail' => 'affiché « à partir de »', 'icon' => 'banknote', 'tone' => 'blue'],
        ['label' => 'Durée moyenne', 'valeur' => $resume['dureeMoyenne'] ?? '–', 'detail' => 'par prestation', 'icon' => 'clock', 'tone' => 'purple'],
    ];
@endphp

@section('content')
<div class="ab-page">
    @include('back.ateliers.espace.partials.entete', ['onglet' => 'services'])
    @include('back.ateliers.partials.flash', ['masquerErreurs' => $modale['ouverte']])

    <div class="ab-summary">
        <ul class="ab-summary__cards" aria-label="Résumé de mon catalogue">
            @foreach ($cartes as $carte)
                <li class="stat-card ab-stat ab-stat--static">
                    <div @class(['stat-icon', $carte['tone']]) aria-hidden="true"><i data-lucide="{{ $carte['icon'] }}"></i></div>
                    <div>
                        <p class="eyebrow">{{ $carte['label'] }}</p>
                        <strong>{{ $carte['valeur'] }}</strong>
                        <small>{{ $carte['detail'] }}</small>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($services->isNotEmpty())
            <button type="button" class="btn btn-primary ab-summary__action" data-service-create>
                <i data-lucide="plus" aria-hidden="true"></i> Ajouter un service
            </button>
        @endif
    </div>

    @if ($services->isEmpty())
        <section class="panel ab-empty" aria-labelledby="ab-services-empty-title">
            <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="scissors"></i></div>
            <h2 id="ab-services-empty-title">Votre catalogue est vide</h2>
            <p>Ajoutez les prestations que vous proposez (retouche d'ourlet, changement de fermeture, upcycling…) avec un prix et une durée estimés : les clients pourront ensuite les réserver.</p>
            <button type="button" class="btn btn-primary" data-service-create>
                <i data-lucide="plus" aria-hidden="true"></i> Ajouter mon premier service
            </button>
        </section>
    @else
        <div class="panel table-panel ab-table-panel">
            <div class="panel-head">
                <div>
                    <h2>{{ $resume['nombre'] }} {{ $resume['nombre'] > 1 ? 'services' : 'service' }}</h2>
                    <p>Prix et durées sont des estimations affichées sur votre fiche publique.</p>
                </div>
            </div>
            <table class="ab-table">
                <caption class="sr-only">Mes services</caption>
                <thead>
                    <tr>
                        <th scope="col">Service</th>
                        <th scope="col">Description</th>
                        <th scope="col">Prix (TND)</th>
                        <th scope="col">Durée</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        @php $serviceId = (string) $service->getKey(); @endphp
                        <tr>
                            <td data-label="Service" class="ab-cell-primary"><b>{{ $service->nom }}</b></td>
                            <td data-label="Description">
                                @if (filled($service->description))
                                    <span class="ab-desc">{{ \Illuminate\Support\Str::limit($service->description, 110) }}</span>
                                @else
                                    <span class="ab-muted">Aucune description</span>
                                @endif
                            </td>
                            <td data-label="Prix (TND)">
                                <b>{{ is_numeric($service->prix_estime) ? \App\Modules\Ateliers\Models\Atelier::formatMontant((float) $service->prix_estime) : '–' }}</b>
                            </td>
                            <td data-label="Durée">{{ $service->dureeFormatee() ?? '–' }}</td>
                            <td data-label="Actions" class="ab-actions-cell">
                                <div class="ab-actions">
                                    <button type="button" class="icon-btn" title="Modifier"
                                            aria-label="Modifier le service {{ $service->nom }}"
                                            data-service-edit
                                            data-id="{{ $serviceId }}"
                                            data-action="{{ route('back.ateliers.services.update', ['id' => $serviceId]) }}"
                                            data-nom="{{ $service->nom }}"
                                            data-description="{{ $service->description }}"
                                            data-prix="{{ $service->prix_estime }}"
                                            data-duree="{{ $service->duree_estimee }}">
                                        <i data-lucide="pencil" aria-hidden="true"></i>
                                    </button>
                                    <form method="post" action="{{ route('back.ateliers.services.destroy', ['id' => $serviceId]) }}"
                                          data-confirm-delete data-nom="{{ $service->nom }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="icon-btn is-danger" title="Supprimer" aria-label="Supprimer le service {{ $service->nom }}">
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
    @endif
</div>

<div @class(['modal-backdrop', 'open' => $modale['ouverte']]) id="ab-service-modal"
     aria-hidden="{{ $modale['ouverte'] ? 'false' : 'true' }}"
     data-service-modal @if ($modale['ouverte']) data-open-on-load @endif>
    <div class="modal ab-modal ab-modal--form" role="dialog" aria-modal="true" aria-labelledby="ab-service-title">
        <div class="modal-head">
            <div>
                <p class="kicker">Mon catalogue</p>
                <h2 id="ab-service-title" data-service-title>{{ $edition ? 'Modifier le service' : 'Nouveau service' }}</h2>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer la fenêtre"><i data-lucide="x" aria-hidden="true"></i></button>
        </div>

        <form method="post" class="ab-modal__form"
              action="{{ $edition ? route('back.ateliers.services.update', ['id' => (string) $serviceModale->getKey()]) : route('back.ateliers.services.store') }}"
              data-service-form data-store-action="{{ route('back.ateliers.services.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" data-service-method @disabled(! $edition)>
            <input type="hidden" name="_modal" value="{{ $modale['mode'] }}" data-service-mode>
            <input type="hidden" name="_service" value="{{ $serviceModale?->getKey() }}" data-service-id>

            @include('back.ateliers.partials.field', ['id' => 'service-nom', 'name' => 'nom', 'label' => 'Nom du service', 'value' => $valeur('nom'), 'required' => true, 'attrs' => ['maxlength' => 120, 'placeholder' => 'Ex. Raccourcir un ourlet']])

            @include('back.ateliers.partials.field', ['id' => 'service-description', 'name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'value' => $valeur('description'), 'hint' => 'Facultatif, 1000 caractères maximum.', 'attrs' => ['maxlength' => 1000]])

            <div class="ab-field-row">
                @include('back.ateliers.partials.field', ['id' => 'service-prix', 'name' => 'prix_estime', 'label' => 'Prix estimé (TND)', 'type' => 'number', 'value' => $valeur('prix_estime'), 'required' => true, 'hint' => 'En dinars, 0 ou plus.', 'attrs' => ['min' => 0, 'max' => 100000, 'step' => '0.5', 'inputmode' => 'decimal']])
                @include('back.ateliers.partials.field', ['id' => 'service-duree', 'name' => 'duree_estimee', 'label' => 'Durée estimée (min)', 'type' => 'number', 'value' => $valeur('duree_estimee'), 'required' => true, 'hint' => 'Entre 5 et 480 minutes.', 'attrs' => ['min' => 5, 'max' => 480, 'step' => 1, 'inputmode' => 'numeric']])
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save" aria-hidden="true"></i>
                    <span data-service-submit>{{ $edition ? 'Enregistrer les modifications' : 'Ajouter le service' }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

@include('back.ateliers.partials.delete-modal', ['cible' => 'service'])
@endsection
