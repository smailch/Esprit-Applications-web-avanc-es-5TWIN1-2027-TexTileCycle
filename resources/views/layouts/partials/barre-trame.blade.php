{{-- Barre de navigation « trame » (styles : css/trame.css, comportement : js/trame.js).
     Fixe en haut de l'écran et toujours sur une seule ligne : sur petit écran, les liens défilent au doigt. --}}
<header class="tr-nav" data-tr-nav>
    <div class="tr-logo"><x-logo :light="true" /></div>

    <nav class="tr-liens" aria-label="Navigation principale">
        @include('layouts.partials.front-liens')
    </nav>

    <div class="tr-compte">
        <button type="button" class="tr-langue" aria-label="Langue : français">
            <i data-lucide="globe"></i> FR
        </button>
        @auth
            @if(auth()->user()->canAccessBackOffice())
                <a href="{{ route('back.dashboard') }}" class="tr-lien-compte">Espace professionnel</a>
            @endif
            <span class="tr-utilisateur">{{ auth()->user()->name }}</span>
            <form method="post" action="{{ route('front.logout') }}" class="tr-form">
                @csrf
                <button type="submit" class="tr-pilule">Déconnexion</button>
            </form>
        @else
            <a href="{{ route('front.login') }}" class="tr-pilule">Connexion</a>
            <a href="{{ route('front.register') }}" class="tr-pilule est-claire">Inscription</a>
        @endauth
    </div>
</header>
