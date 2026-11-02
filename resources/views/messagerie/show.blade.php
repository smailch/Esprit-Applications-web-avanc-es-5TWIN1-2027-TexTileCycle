@extends($layout)

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
            <li><a href="{{ route('front.messagerie.index') }}">Messages</a></li>
            <li><span aria-current="page">{{ \Illuminate\Support\Str::limit($conversation->sujet, 40) }}</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Messages</h1>
        </div>
    </div>
@endsection

@section('content')
<div class="msg-page">
    @if($layout === 'layouts.back')
        @include('back.partials.flash')
    @endif

    <div class="msg-app est-ouverte">
        @include('messagerie.partials.liste', ['routePrefixe' => 'front.messagerie', 'estAdmin' => false, 'actuelle' => $conversation])
        @include('messagerie.partials.fil', ['routePrefixe' => 'front.messagerie', 'estAdmin' => false])
    </div>
</div>
@endsection
