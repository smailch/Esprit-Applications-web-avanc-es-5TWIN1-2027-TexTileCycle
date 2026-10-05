@extends('layouts.back')

@section('page-title', $pageTitle)

@include('back.ateliers.partials.form-assets')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rdv-back.css') }}">
@endpush

@php
    $statutDetail = match ($atelier->statut) {
        \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF => 'visible sur le site',
        \App\Modules\Ateliers\Models\Atelier::STATUT_SUSPENDU => 'masqué du site',
        default => 'en cours de validation',
    };
    $nbAvis = (int) $atelier->nb_avis;
    $cartes = [
        ['label' => 'Statut', 'valeur' => $atelier->statutLabel(), 'detail' => $statutDetail, 'icon' => 'shield-check', 'tone' => $atelier->statutTone()],
        ['label' => 'Services', 'valeur' => $nbServices, 'detail' => 'au catalogue', 'icon' => 'scissors', 'tone' => 'blue'],
        ['label' => 'Note moyenne', 'valeur' => $nbAvis > 0 ? $atelier->noteFormatee() : '–', 'detail' => $nbAvis > 0 ? 'sur 5' : 'pas encore de note', 'icon' => 'star', 'tone' => 'orange'],
        ['label' => "Nombre d'avis", 'valeur' => $nbAvis, 'detail' => $nbAvis > 1 ? 'avis clients' : 'avis client', 'icon' => 'message-square', 'tone' => 'purple'],
    ];
@endphp

@section('content')
<div class="ab-page">
    @include('back.ateliers.espace.partials.entete', ['onglet' => 'profil'])
    @include('back.ateliers.partials.flash')
    @include('back.rendez-vous.partials.resume-atelier')

    <ul class="stats-grid four ab-stats ab-stats--list" aria-label="Résumé de mon atelier">
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

    <form method="post" action="{{ route('back.ateliers.profil.update') }}" class="ab-form" data-atelier-form>
        @csrf
        @method('PUT')

        <div class="ab-form__grid">
            <div class="ab-form__col">
                <section class="panel ab-section" aria-labelledby="sec-identite">
                    <div class="panel-head">
                        <div>
                            <h2 id="sec-identite">Identité</h2>
                            <p>Ce que les clients voient sur votre fiche</p>
                        </div>
                    </div>
                    <div class="ab-section__body">
                        @include('back.ateliers.partials.compte-lecture', [
                            'compte' => $compte,
                            'aide' => "Le compte rattaché à votre atelier est géré par l'administrateur.",
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
                            <p>Statut et note sont gérés par la plateforme</p>
                        </div>
                        <x-status-badge :tone="$atelier->statutTone()">{{ $atelier->statutLabel() }}</x-status-badge>
                    </div>
                    <div class="ab-section__body">
                        <p class="ab-hint ab-hint--block">
                            <i data-lucide="info" aria-hidden="true"></i>
                            Le statut est attribué par l'administrateur après vérification de votre fiche ; la note moyenne est calculée à partir des avis clients. Ni l'un ni l'autre ne se modifie depuis cet espace.
                        </p>
                    </div>
                </section>
            </div>
        </div>

        @include('back.ateliers.partials.form-actions', [
            'annulerUrl' => route('back.ateliers.profil'),
            'libelle' => 'Enregistrer mon profil',
        ])
    </form>
</div>
@endsection
