@extends('layouts.front')

@section('content')
<main class="auth-page container">
    <div class="auth-card">
        <p class="kicker">Rejoindre TexTileCycle</p>
        <h1>Inscription</h1>
        <p class="muted">Créez votre compte citoyen en quelques secondes.</p>
        <form action="#" method="post" style="margin-top:24px">
            @csrf
            <div class="field">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" placeholder="Yasmine Ben Ali" required>
            </div>
            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" placeholder="vous@exemple.tn" required>
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="field">
                <label for="role">Rôle</label>
                <select id="role" name="role" required>
                    <option value="citoyen" selected>Citoyen</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary full">Créer mon compte</button>
            </div>
            <p class="muted-link">Déjà inscrit ? <a href="{{ route('front.login') }}">Se connecter</a></p>
        </form>
    </div>
</main>
@endsection
