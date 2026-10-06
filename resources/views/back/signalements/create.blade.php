@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@section('content')
<div class="module">
    <div class="module-toolbar">
        <a href="{{ route('back.signalements.index') }}" class="link-button"><i data-lucide="arrow-left"></i> Retour aux signalements</a>
    </div>

    <form method="post" action="{{ route('back.signalements.store') }}" class="panel form-card" novalidate>
        @csrf
        <div class="panel-head">
            <div>
                <h2>Saisir un signalement</h2>
                <p>Pour un signalement reçu hors plateforme (téléphone, e-mail…). Les citoyens signalent directement depuis le site.</p>
            </div>
        </div>

        @include('back.signalements._form', ['edition' => false])

        <div class="form-actions">
            <a href="{{ route('back.signalements.index') }}" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Enregistrer</button>
        </div>
    </form>
</div>
@endsection
