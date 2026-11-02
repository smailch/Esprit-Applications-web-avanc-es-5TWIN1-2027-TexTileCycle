@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/messagerie.css') }}">
@endpush

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="msg-app">
        @include('messagerie.partials.liste', ['routePrefixe' => 'back.messagerie', 'estAdmin' => true, 'actuelle' => null])

        <section class="msg-fil msg-fil--vide">
            <i data-lucide="messages-square" aria-hidden="true"></i>
            <h2>Messagerie de l'administration</h2>
            <p>Échangez avec les citoyens et les responsables d'ateliers ou d'associations.<br>
               Sélectionnez une conversation, ou démarrez-en une depuis un signalement ou la page Partenaires.</p>
            <a href="{{ route('back.messagerie.create') }}" class="btn btn-primary"><i data-lucide="square-pen" aria-hidden="true"></i> Nouvelle conversation</a>
        </section>
    </div>
</div>
@endsection
