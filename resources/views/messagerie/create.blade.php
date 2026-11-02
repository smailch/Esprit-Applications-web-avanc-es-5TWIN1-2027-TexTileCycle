@extends($layout)

@section('title', 'Écrire à l\'équipe')
@section('page-title', 'Écrire à l\'équipe')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/messagerie.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><a href="{{ route('front.messagerie.index') }}">Messages</a></li>
            <li><span aria-current="page">Nouveau message</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Écrire à l'équipe</h1>
            <p class="tr-bandeau__intro">Un administrateur vous répondra dans votre espace Messages.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="msg-page">
    <form method="post" action="{{ route('front.messagerie.store') }}" class="panel form-card msg-form" novalidate>
        @csrf
        <div class="form-card-grid">
            <label class="span-2">Sujet <span class="req">*</span>
                <input type="text" name="sujet" maxlength="150" value="{{ old('sujet', $sujet) }}" @class(['is-invalid' => $errors->has('sujet')]) required
                       placeholder="ex. Question sur mon signalement">
                @error('sujet')<span class="error-text">{{ $message }}</span>@enderror
            </label>

            <label class="span-2">Message <span class="req">*</span>
                <textarea name="message" rows="7" maxlength="2000" @class(['is-invalid' => $errors->has('message')]) required>{{ old('message') }}</textarea>
                @error('message')<span class="error-text">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="form-actions">
            <a href="{{ route('front.messagerie.index') }}" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><i data-lucide="send" aria-hidden="true"></i> Envoyer</button>
        </div>
    </form>
</div>
@endsection
