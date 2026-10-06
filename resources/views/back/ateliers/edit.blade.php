@extends('layouts.back')

@section('page-title', $pageTitle)

@include('back.ateliers.partials.form-assets')

@section('content')
<div class="ab-page">
    <div class="ab-page-head">
        <a href="{{ route('back.ateliers') }}" class="ab-back-link"><i data-lucide="arrow-left" aria-hidden="true"></i> Retour aux ateliers</a>
        <div class="ab-page-head__title">
            <span @class(['ab-avatar', 'ab-avatar--'.$atelier->avatarTone()]) aria-hidden="true">{{ $atelier->initiales() }}</span>
            <div>
                <p class="ab-page-head__name">{{ $atelier->nom }}</p>
                <p class="ab-page-head__intro">{{ $atelier->ville }}</p>
            </div>
            @if ($atelier->statut === \App\Modules\Ateliers\Models\Atelier::STATUT_ACTIF)
                <a href="{{ route('front.ateliers.show', ['id' => (string) $atelier->getKey()]) }}" class="btn btn-secondary small" target="_blank" rel="noopener">
                    <i data-lucide="external-link" aria-hidden="true"></i> Voir la fiche publique
                </a>
            @endif
        </div>
    </div>

    @include('back.ateliers.partials.flash')
    @include('back.ateliers.form')
</div>
@endsection
