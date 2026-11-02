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
