@extends($layout)

@section('title', 'Nouveau message')
@section('page-title', 'Nouveau message')

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
            <h1>Nouveau message</h1>
            <p class="tr-bandeau__intro">Écrivez à l'équipe TexTileCycle, à un atelier ou à une association.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="msg-page">
    <form method="post" action="{{ route('front.messagerie.store') }}" class="panel form-card msg-form" novalidate>
        @csrf
        <div class="form-card-grid">
            {{-- Recherche du destinataire : suggestions dès 3 caractères (public/js/messagerie.js). --}}
            <div class="span-2 msg-dest" data-messagerie-destinataire data-url="{{ route('front.messagerie.destinataires') }}">
                <label for="msg-dest-recherche">À <span class="req">*</span></label>
                <input type="hidden" name="destinataire" value="{{ $destinataire['valeur'] ?? '' }}" data-dest-valeur>

                <div class="msg-dest__choisi" data-dest-choisi @unless($destinataire) hidden @endunless>
                    <i data-lucide="user-round-check" aria-hidden="true"></i>
                    <span data-dest-nom>{{ $destinataire['nom'] ?? '' }}</span>
                    <button type="button" class="icon-btn" data-dest-effacer aria-label="Changer de destinataire"><i data-lucide="x" aria-hidden="true"></i></button>
                </div>

                <div class="msg-dest__zone" data-dest-zone @if($destinataire) hidden @endif>
                    <input type="search" id="msg-dest-recherche" autocomplete="off" data-dest-recherche
                           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="msg-dest-resultats"
                           placeholder="Tapez au moins 3 caractères : nom d'un atelier, d'une ville, d'une association, « équipe »…"
                           @class(['is-invalid' => $errors->has('destinataire')])>
                    <ul id="msg-dest-resultats" class="msg-dest__resultats" role="listbox" data-dest-resultats hidden></ul>
                    <p class="msg-dest__aide" data-dest-aide>Exemples : « Couture », « Marsa », « équipe ».</p>
                </div>
                @error('destinataire')<span class="error-text">{{ $message }}</span>@enderror
            </div>

            <label class="span-2">Sujet <span class="req">*</span>
                <input type="text" name="sujet" maxlength="150" value="{{ old('sujet', $sujet) }}" @class(['is-invalid' => $errors->has('sujet')]) required
                       placeholder="ex. Retouche d'un jean, question sur un don…">
                @error('sujet')<span class="error-text">{{ $message }}</span>@enderror
            </label>

            <label class="span-2">Message <span class="req">*</span>
                <textarea name="message" rows="7" maxlength="2000" @class(['is-invalid' => $errors->has('message')]) required>{{ old('message') }}</textarea>
                @error('message')<span class="error-text">{{ $message }}</span>@enderror
            </label>

            @if(session('moderation.verdict') === 'avertir')
                <label class="span-2 check msg-confirmer">
                    <input type="checkbox" name="confirmer" value="1">
                    Je confirme vouloir envoyer ce message tel quel.
                </label>
            @endif
        </div>

        <div class="form-actions">
            <a href="{{ route('front.messagerie.index') }}" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><i data-lucide="send" aria-hidden="true"></i> Envoyer</button>
        </div>
    </form>
</div>
@endsection
