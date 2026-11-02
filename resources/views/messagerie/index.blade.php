@extends($layout)

{{-- Citoyen : gabarit « trame » ; atelier / association : gabarit du back-office. --}}
@section('title', 'Messages')
@section('page-title', 'Messages de l\'équipe')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/messagerie.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Messages</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Messages</h1>
            <p class="tr-bandeau__intro">Vos échanges avec l'équipe TexTileCycle.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="msg-page">
    @if($layout === 'layouts.back')
        @include('back.partials.flash')
    @endif

    <div class="msg-app">
        @include('messagerie.partials.liste', ['routePrefixe' => 'front.messagerie', 'estAdmin' => false, 'actuelle' => null])

        <section class="msg-fil msg-fil--vide">
            <i data-lucide="messages-square" aria-hidden="true"></i>
            <h2>Une question ? Écrivez-nous</h2>
            <p>Contactez l'équipe TexTileCycle, un atelier pour une réparation ou une association pour un don. Les réponses arrivent ici.</p>
            <a href="{{ route('front.messagerie.create') }}" class="btn btn-primary"><i data-lucide="square-pen" aria-hidden="true"></i> Nouveau message</a>
        </section>
    </div>
</div>
@endsection
