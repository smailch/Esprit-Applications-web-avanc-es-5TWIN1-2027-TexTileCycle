@extends('layouts.front')

@section('content')
<main class="auth-page container">
    <div class="auth-card">
        <p class="kicker">Espace citoyen</p>
        <h1>Connexion</h1>
        <p class="muted">Accédez à vos vêtements, rendez-vous et dons.</p>
        <p class="muted small" style="margin-top:8px">Comptes <strong>atelier / association / admin</strong> : vous serez redirigé vers l'espace professionnel après connexion.</p>

        @if($errors->any())
            <div class="field-error" style="margin-top:16px">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('front.login.store') }}" method="post" style="margin-top:24px">
            @csrf
            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="vous@exemple.tn" required>
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="field">
                <label><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Se souvenir de moi</label>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary full">Se connecter</button>
            </div>
            <p class="muted-link">Pas encore de compte ? <a href="{{ route('front.register') }}">Créer un compte</a></p>
        </form>
    </div>
</main>
@endsection
