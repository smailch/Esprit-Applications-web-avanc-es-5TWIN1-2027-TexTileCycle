@extends('layouts.back')

@section('page-title', $pageTitle)

@include('back.ateliers.partials.form-assets')

@section('content')
<div class="ab-page">
    @include('back.ateliers.partials.flash')

    <section class="panel ab-empty ab-empty--setup" aria-labelledby="ab-setup-title">
        <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="store"></i></div>
        <h2 id="ab-setup-title">Créez la fiche de votre atelier</h2>
        <p>Votre compte a été validé. Renseignez votre atelier pour apparaître ensuite sur le site, après une dernière validation de la fiche par l'administrateur.</p>
        <ul class="ab-setup-steps">
            <li><i data-lucide="user-check" aria-hidden="true"></i> Compte atelier validé</li>
            <li class="is-current"><i data-lucide="store" aria-hidden="true"></i> Création de votre fiche</li>
            <li class="is-pending"><i data-lucide="scissors" aria-hidden="true"></i> Ajout de vos services</li>
        </ul>
    </section>

    <form method="post" action="{{ route('back.ateliers.profil.store') }}" class="ab-form" data-atelier-form>
        @csrf

        <div class="ab-form__grid">
            <div class="ab-form__col">
                <section class="panel ab-section" aria-labelledby="sec-identite">
                    <div class="panel-head">
                        <div>
                            <h2 id="sec-identite">Identité</h2>
                            <p>Ce que les clients verront sur votre fiche</p>
                        </div>
                    </div>
                    <div class="ab-section__body">
                        @include('back.ateliers.partials.compte-lecture', [
                            'compte' => $compte,
                            'aide' => 'Ce compte sera le responsable de l\'atelier. Le statut de la fiche passera en attente de validation.',
                        ])
                        @include('back.ateliers.partials.identite-champs')
                    </div>
                </section>

                @include('back.ateliers.partials.horaires-editeur')
            </div>

            <div class="ab-form__col">
                @include('back.ateliers.partials.localisation')

                <section class="panel ab-section" aria-labelledby="sec-visibilite">
                    <div class="panel-head">
                        <div>
                            <h2 id="sec-visibilite">Visibilité</h2>
                            <p>Votre fiche restera en attente jusqu'à validation</p>
                        </div>
                        <x-status-badge tone="orange">En attente</x-status-badge>
                    </div>
                    <div class="ab-section__body">
                        <p class="ab-hint ab-hint--block">
                            <i data-lucide="info" aria-hidden="true"></i>
                            Après création, l'administrateur valide votre atelier. Tant qu'il n'est pas actif, il n'apparaît pas sur la carte ni dans l'annuaire citoyen.
                        </p>
                    </div>
                </section>
            </div>
        </div>

        @include('back.ateliers.partials.form-actions', [
            'annulerUrl' => null,
            'libelle' => 'Créer mon atelier',
        ])
    </form>
</div>
@endsection
