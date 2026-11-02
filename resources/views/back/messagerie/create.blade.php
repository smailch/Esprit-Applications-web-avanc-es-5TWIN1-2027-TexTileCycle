@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@section('content')
<div class="module">
    <div class="module-toolbar">
        <a href="{{ route('back.messagerie.index') }}" class="link-button"><i data-lucide="arrow-left" aria-hidden="true"></i> Retour à la messagerie</a>
    </div>

    <form method="post" action="{{ route('back.messagerie.store') }}" class="panel form-card" novalidate>
        @csrf
        <div class="panel-head">
            <div>
                <h2>Nouvelle conversation</h2>
                <p>Le destinataire retrouvera votre message dans son espace « Messages ».</p>
            </div>
        </div>

        @if(old('contexte_type', $prefill['contexte_type']))
            <input type="hidden" name="contexte_type" value="{{ old('contexte_type', $prefill['contexte_type']) }}">
            <input type="hidden" name="contexte_id" value="{{ old('contexte_id', $prefill['contexte_id']) }}">
        @endif

        <div class="form-card-grid">
            @if($contexteLabel)
                <p class="span-2 msg-contexte-note"><i data-lucide="link-2" aria-hidden="true"></i> Rattachée à : <b>{{ $contexteLabel }}</b></p>
            @endif
            @error('contexte_id')<p class="span-2 error-text">{{ $message }}</p>@enderror

            <label class="span-2">Destinataire <span class="req">*</span>
                <select name="user_id" @class(['is-invalid' => $errors->has('user_id')]) required>
                    <option value="">— Choisir un citoyen ou un partenaire —</option>
                    @foreach($destinataires as $groupe => $utilisateurs)
                        <optgroup label="{{ $groupe }}s">
                            @foreach($utilisateurs as $utilisateur)
                                <option value="{{ $utilisateur->getKey() }}" @selected(old('user_id', $prefill['user_id']) === (string) $utilisateur->getKey())>
                                    {{ $utilisateur->name }} — {{ $utilisateur->email }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('user_id')<span class="error-text">{{ $message }}</span>@enderror
            </label>

            <label class="span-2">Sujet <span class="req">*</span>
                <input type="text" name="sujet" maxlength="150" value="{{ old('sujet', $prefill['sujet']) }}" @class(['is-invalid' => $errors->has('sujet')]) required
                       placeholder="ex. Votre signalement sur l'atelier Couture Plus">
                @error('sujet')<span class="error-text">{{ $message }}</span>@enderror
            </label>

            <label class="span-2">Message <span class="req">*</span>
                <textarea name="message" rows="6" maxlength="2000" @class(['is-invalid' => $errors->has('message')]) required>{{ old('message') }}</textarea>
                @error('message')<span class="error-text">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="form-actions">
            <a href="{{ route('back.messagerie.index') }}" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><i data-lucide="send" aria-hidden="true"></i> Envoyer</button>
        </div>
    </form>
</div>
@endsection
