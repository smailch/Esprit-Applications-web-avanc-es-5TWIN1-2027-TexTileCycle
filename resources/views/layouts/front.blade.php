@extends('layouts.app')

@section('body')
<div class="front-shell">
    <header class="front-header">
        <x-logo />
        <nav data-front-nav>
            <a href="{{ route('front.home') }}" @class(['active' => request()->routeIs('front.home')])>Accueil</a>
            <a href="{{ route('front.ateliers') }}" @class(['active' => request()->routeIs('front.ateliers')])>Ateliers</a>
            <a href="{{ route('front.associations') }}" @class(['active' => request()->routeIs('front.associations')])>Associations</a>
            @auth
                <a href="{{ route('front.vetements') }}" @class(['active' => request()->routeIs('front.vetements')])>Mes vêtements</a>
                <a href="{{ route('front.rdv') }}" @class(['active' => request()->routeIs('front.rdv*')])>Rendez-vous</a>
                <a href="{{ route('front.dons') }}" @class(['active' => request()->routeIs('front.dons')])>Dons</a>
            @endauth
        </nav>
        <div class="header-actions">
            <button type="button" class="icon-btn mobile-menu" data-mobile-menu aria-label="Menu"><i data-lucide="menu"></i></button>
            @auth
                @if(auth()->user()->canAccessBackOffice())
                    <a href="{{ route('back.dashboard') }}" class="link-button">Espace professionnel</a>
                @endif
                <span class="muted small">{{ auth()->user()->name }} · {{ auth()->user()->roleLabel() }}</span>
                <form method="post" action="{{ route('front.logout') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary small">Déconnexion</button>
                </form>
            @else
                <a href="{{ route('front.login') }}" class="btn btn-secondary small">Connexion</a>
                <a href="{{ route('front.register') }}" class="btn btn-primary small">Inscription</a>
            @endauth
        </div>
    </header>

    @if(session('success') || session('info') || session('error') || $errors->any())
        <div class="container" style="padding-top:16px">
            @if(session('success'))
                <div class="panel" style="padding:12px 16px;background:var(--green-50);border:1px solid var(--green);margin-bottom:8px">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="panel" style="padding:12px 16px;background:#e3f2fd;border:1px solid #64b5f6;margin-bottom:8px">{{ session('info') }}</div>
            @endif
            @if(session('error'))
                <div class="panel" style="padding:12px 16px;background:#ffebee;border:1px solid #ef5350;margin-bottom:8px;color:#c62828">{{ session('error') }}</div>
            @endif
            @foreach($errors->all() as $error)
                <div class="panel" style="padding:12px 16px;background:#fff3e0;border:1px solid #ffb74d;margin-bottom:8px">{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')

    <footer class="footer">
        <div class="container footer-grid">
            <div>
                <x-logo :light="true" />
                <p>La plateforme tunisienne de l'économie circulaire textile.</p>
            </div>
            <div>
                <b>Explorer</b>
                <a href="{{ route('front.ateliers') }}">Ateliers</a>
                <a href="{{ route('front.associations') }}">Associations</a>
                <a href="{{ route('front.home') }}">Notre impact</a>
            </div>
            <div>
                <b>Ressources</b>
                <a href="{{ route('front.home') }}">Comment ça marche</a>
                <a href="#">FAQ</a>
                <a href="#">Contact</a>
            </div>
            <div>
                <b>Suivez-nous</b>
                <p>hello@textilecycle.tn</p>
                <p>Tunis, Tunisie</p>
            </div>
        </div>
        <div class="container footer-bottom">© 2026 TexTileCycle <span>Fait avec soin pour la planète.</span></div>
    </footer>
</div>
@endsection
