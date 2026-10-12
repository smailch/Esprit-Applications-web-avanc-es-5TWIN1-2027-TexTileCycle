@extends('layouts.app')

{{--
    Gabarit minimaliste « trame », dérivé de l'accueil : barre de navigation et bandeau sombres, contenu clair.
    Pour y passer une page publique : @extends('layouts.trame'), puis
      @section('bandeau') fil d'Ariane, <h1>, intro ou actions (classes tr-bandeau__*) @endsection
      @section('content') le contenu de la page @endsection
--}}

{{-- viewport-fit=cover : nécessaire pour que env(safe-area-inset-*) ait une valeur (encoches des téléphones). --}}
@section('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover')

{{-- @prepend : trame.css passe avant les styles de la page, qui peuvent ainsi l'affiner. --}}
@prepend('styles')
    <link rel="stylesheet" href="{{ asset('css/trame.css') }}">
@endprepend

@push('scripts')
    <script src="{{ asset('js/trame.js') }}"></script>
@endpush

@section('body')
<div class="front-shell tr-page">
    @include('layouts.partials.barre-trame')

    <div class="tr-bandeau">
        <div class="tr-grille" aria-hidden="true"></div>
        @yield('bandeau')
    </div>

    <div class="tr-contenu">
        <div class="tr-grille" aria-hidden="true"></div>

        @if(session('success') || session('info') || session('error') || $errors->any())
            <div class="tr-messages">
                @if(session('success'))
                    <p class="tr-message">{{ session('success') }}</p>
                @endif
                @if(session('info'))
                    <p class="tr-message est-info">{{ session('info') }}</p>
                @endif
                @if(session('error'))
                    <p class="tr-message est-erreur" role="alert">{{ session('error') }}</p>
                @endif
                @foreach($errors->all() as $error)
                    <p class="tr-message est-erreur">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @yield('content')
    </div>

    @include('layouts.partials.front-pied', ['trame' => true])
</div>
@endsection
