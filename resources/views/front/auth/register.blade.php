@extends('layouts.front')

@section('content')
<main class="auth-page container">
    <div class="auth-card">
        <p class="kicker">Rejoindre TexTileCycle</p>
        <h1>Inscription</h1>
        <p class="muted">Créez votre compte citoyen en quelques secondes.</p>

        @if($errors->any())
            <div class="field-error" style="margin-top:16px">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('front.register.store') }}" method="post" style="margin-top:24px">
            @csrf
            <div class="field">
                <label for="name">Nom complet</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Yasmine Ben Ali" required>
            </div>
            <div class="field">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="vous@exemple.tn" required>
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required minlength="8">
            </div>
            <div class="field">
                <label for="password_confirmation">Confirmer le mot de passe</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required minlength="8">
            </div>
            <div class="field">
                <label for="role">Rôle</label>
                <select id="role" name="role" required>
                    <option value="citoyen" @selected(old('role', 'citoyen') === 'citoyen')>Citoyen</option>
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
