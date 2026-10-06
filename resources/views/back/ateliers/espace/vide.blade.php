@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@section('content')
<div class="ab-page">
    @include('back.ateliers.partials.flash')

    <section class="panel ab-empty ab-empty--setup" aria-labelledby="ab-setup-title">
        <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="store"></i></div>
        <h2 id="ab-setup-title">Votre atelier n'est pas encore configuré. Contactez l'administrateur.</h2>
        <p>Votre compte a bien le rôle atelier, mais aucune fiche ne lui est encore rattachée. Dès que l'administrateur l'aura créée, vous pourrez gérer ici votre profil, vos horaires et vos services.</p>
        <ul class="ab-setup-steps">
            <li><i data-lucide="user-check" aria-hidden="true"></i> Compte atelier créé</li>
            <li class="is-pending"><i data-lucide="hourglass" aria-hidden="true"></i> Fiche atelier à créer par l'administrateur</li>
            <li class="is-pending"><i data-lucide="scissors" aria-hidden="true"></i> Ajout de vos services</li>
        </ul>
    </section>
</div>
@endsection
