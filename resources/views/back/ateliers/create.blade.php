@extends('layouts.back')

@section('page-title', $pageTitle)

@include('back.ateliers.partials.form-assets')

@section('content')
<div class="ab-page">
    <div class="ab-page-head">
        <a href="{{ route('back.ateliers') }}" class="ab-back-link"><i data-lucide="arrow-left" aria-hidden="true"></i> Retour aux ateliers</a>
        <p class="ab-page-head__intro">Rattachez un compte de rôle atelier, complétez sa fiche puis choisissez sa visibilité.</p>
    </div>

    @include('back.ateliers.partials.flash')
    @include('back.ateliers.form')
</div>
@endsection
