@extends('layouts.app')

@section('body')
<div class="front-shell">
    <header class="front-header">
        <x-logo />
        <nav data-front-nav>
            <a href="{{ route('front.home') }}" @class(['active' => request()->routeIs('front.home')])>Accueil</a>
            <a href="{{ route('front.ateliers') }}" @class(['active' => request()->routeIs('front.ateliers')])>Ateliers</a>
            <a href="{{ route('front.associations') }}" @class(['active' => request()->routeIs('front.associations')])>Associations</a>
            <a href="{{ route('front.vetements') }}" @class(['active' => request()->routeIs('front.vetements')])>Mes vêtements</a>
            <a href="{{ route('front.dons') }}" @class(['active' => request()->routeIs('front.dons')])>Dons</a>
        </nav>
        <div class="header-actions">
            <button type="button" class="icon-btn mobile-menu" data-mobile-menu aria-label="Menu"><i data-lucide="menu"></i></button>
            <a href="{{ route('back.dashboard') }}" class="link-button">Espace professionnel</a>
            <a href="{{ route('front.login') }}" class="btn btn-secondary small">Connexion</a>
            <a href="{{ route('front.register') }}" class="btn btn-primary small">Inscription</a>
        </div>
    </header>

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
