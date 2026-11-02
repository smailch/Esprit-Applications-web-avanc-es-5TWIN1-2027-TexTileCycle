@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@section('content')
<div class="module">
    <div class="module-toolbar">
        <a href="{{ route('back.messagerie.show', $conversation->getKey()) }}" class="link-button"><i data-lucide="arrow-left" aria-hidden="true"></i> Retour à la conversation</a>
    </div>

    <form method="post" action="{{ route('back.messagerie.update', $conversation->getKey()) }}" class="panel form-card" novalidate>
        @csrf
        @method('PUT')
        <div class="panel-head">
            <div>
                <h2>Modifier la conversation</h2>
                <p>Avec <b>{{ $conversation->interlocuteur?->name ?? 'compte supprimé' }}</b>
                   ({{ $conversation->interlocuteur?->roleLabel() }}) · ouverte le {{ $conversation->created_at?->format('d/m/Y') }}</p>
            </div>
            <x-status-badge :tone="$conversation->statutTone()">{{ $conversation->statutLabel() }}</x-status-badge>
        </div>

        <div class="form-card-grid">
            <label class="span-2">Sujet <span class="req">*</span>
                <input type="text" name="sujet" maxlength="150" value="{{ old('sujet', $conversation->sujet) }}" @class(['is-invalid' => $errors->has('sujet')]) required>
                @error('sujet')<span class="error-text">{{ $message }}</span>@enderror
            </label>

            <label>Statut <span class="req">*</span>
                <select name="statut" @class(['is-invalid' => $errors->has('statut')]) required>
                    @foreach(\App\Modules\Messagerie\Models\Conversation::STATUTS as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('statut', $conversation->statut) === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
                <small class="muted">Une conversation fermée ne peut plus recevoir de réponse.</small>
                @error('statut')<span class="error-text">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="form-actions">
            <a href="{{ route('back.messagerie.show', $conversation->getKey()) }}" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary"><i data-lucide="save" aria-hidden="true"></i> Enregistrer</button>
        </div>
    </form>
</div>
@endsection
