@extends('layouts.front')

@section('content')
<main class="auth-page container">
    <div class="auth-card">
        <p class="kicker">Accès refusé</p>
        <h1>403 — Non autorisé</h1>
        <p class="muted">{{ $exception->getMessage() ?: 'Vous n\'avez pas les droits pour afficher cette page.' }}</p>
        <div class="actions" style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap">
            @auth
                @if(auth()->user()->canAccessBackOffice())
                    <a href="{{ route('back.dashboard') }}" class="btn btn-primary">Retour au back-office</a>
                @else
                    <a href="{{ route('front.home') }}" class="btn btn-primary">Retour à l'accueil</a>
                @endif
            @else
                <a href="{{ route('front.login') }}" class="btn btn-primary">Se connecter</a>
            @endauth
        </div>
    </div>
</main>
@endsection
