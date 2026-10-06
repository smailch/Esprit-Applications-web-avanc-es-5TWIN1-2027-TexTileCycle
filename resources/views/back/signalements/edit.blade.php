@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@section('content')
<div class="module">
    <div class="module-toolbar">
        <a href="{{ route('back.signalements.show', $signalement->getKey()) }}" class="link-button"><i data-lucide="arrow-left"></i> Retour au détail</a>
    </div>

    <form method="post" action="{{ route('back.signalements.update', $signalement->getKey()) }}" class="panel form-card" novalidate>
        @csrf
        @method('PUT')
        <div class="panel-head">
            <div>
                <h2>Modifier le signalement</h2>
                <p>Émis par <b>{{ $signalement->auteur?->name ?? 'compte supprimé' }}</b> le {{ $signalement->created_at?->format('d/m/Y à H:i') }}</p>
            </div>
            <x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge>
        </div>

        @include('back.signalements._form', ['edition' => true])

        <div class="form-actions">
            <a href="{{ route('back.signalements.show', $signalement->getKey()) }}" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Enregistrer les modifications</button>
        </div>
    </form>
</div>
@endsection
