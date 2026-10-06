@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@php
    $cible = $signalement->cible;
    $lienCible = match ($signalement->cible_type) {
        'Atelier' => Route::has('back.ateliers.edit') ? route('back.ateliers.edit', $signalement->cible_id) : null,
        'Association' => Route::has('back.associations.edit') ? route('back.associations.edit', $signalement->cible_id) : null,
        'User' => route('back.users.index'),
        default => null,
    };
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="module-toolbar">
        <a href="{{ route('back.signalements.index') }}" class="link-button"><i data-lucide="arrow-left"></i> Retour aux signalements</a>
        <div class="row-actions">
            <a href="{{ route('back.signalements.edit', $signalement->getKey()) }}" class="btn btn-secondary small"><i data-lucide="pencil"></i> Modifier</a>
            <form method="post" action="{{ route('back.signalements.destroy', $signalement->getKey()) }}" onsubmit="return confirm('Supprimer définitivement ce signalement ? Cette action est irréversible.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-secondary small btn-danger"><i data-lucide="trash-2"></i> Supprimer</button>
            </form>
        </div>
    </div>

    <div class="detail-grid">
        <div class="panel detail-card">
            <div class="panel-head">
                <div>
                    <h2>{{ $signalement->typeLabel() }} signalé(e)</h2>
                    <p>Reçu le {{ $signalement->created_at?->format('d/m/Y à H:i') }}</p>
                </div>
                <x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge>
            </div>

            <dl class="detail-list">
                <dt>Motif</dt>
                <dd class="motif-full">{{ $signalement->motif }}</dd>

                <dt>Auteur</dt>
                <dd>
                    @if($signalement->auteur)
                        <b>{{ $signalement->auteur->name }}</b> · {{ $signalement->auteur->email }}
                        <span class="cell-sub">{{ $signalement->auteur->roleLabel() }}</span>
                    @else
                        <span class="muted">Compte supprimé</span>
                    @endif
                </dd>

                <dt>Élément signalé</dt>
                <dd>
                    <b>{{ $signalement->cibleLabel() }}</b>
                    <span class="cell-sub">{{ $signalement->cibleNom() }} · {{ $signalement->cible_id }}</span>
                    @if($cible && $signalement->cible_type === 'Atelier')
                        <span class="cell-sub">{{ $cible->adresse }} {{ $cible->ville }} · statut : {{ $cible->statutLabel() }}</span>
                    @elseif($cible && $signalement->cible_type === 'Association')
                        <span class="cell-sub">{{ $cible->adresse }} · statut : {{ $cible->statutLabel() }}</span>
                    @elseif($cible && $signalement->cible_type === 'User')
                        <span class="cell-sub">{{ $cible->email }} · {{ $cible->is_active ? 'compte actif' : 'compte désactivé' }}</span>
                    @elseif($cible && $signalement->cible_type === 'Vetement')
                        <span class="cell-sub">{{ $cible->size }} · {{ $cible->condition_label }} · propriétaire : {{ $cible->owner?->name ?? '—' }}</span>
                    @elseif(! $cible)
                        <span class="cell-sub">L'élément n'existe plus.</span>
                    @endif
                    @if($lienCible)
                        <a href="{{ $lienCible }}" class="link-button">Ouvrir la fiche <i data-lucide="arrow-up-right"></i></a>
                    @endif
                </dd>

                @unless($signalement->estEnAttente())
                    <dt>Modération</dt>
                    <dd>
                        {{ $signalement->statutLabel() }} par <b>{{ $signalement->traitePar?->name ?? '—' }}</b>
                        le {{ $signalement->traite_le?->format('d/m/Y à H:i') }}
                        @if($signalement->note_admin)
                            <span class="cell-sub">« {{ $signalement->note_admin }} »</span>
                        @endif
                    </dd>
                @endunless
            </dl>
        </div>

        <div>
            @if($signalement->estEnAttente())
                <form method="post" action="{{ route('back.signalements.moderer', $signalement->getKey()) }}" class="panel detail-card action-body">
                    @csrf
                    @method('PATCH')
                    <h2 style="font-size:16px">Modérer</h2>
                    <label>Note de modération <small class="muted">(obligatoire pour un rejet)</small>
                        <textarea name="note_admin" rows="3" @class(['is-invalid' => $errors->has('note_admin')])>{{ old('note_admin') }}</textarea>
                        @error('note_admin')<span class="error-text">{{ $message }}</span>@enderror
                    </label>
                    @if(in_array($signalement->cible_type, ['Atelier', 'Association', 'User'], true))
                        <label class="check">
                            <input type="checkbox" name="sanctionner" value="1" @checked(old('sanctionner'))>
                            {{ $signalement->cible_type === 'User' ? 'Désactiver le compte signalé' : 'Suspendre le partenaire signalé' }}
                        </label>
                    @endif
                    <div class="row-actions">
                        <button type="submit" name="statut" value="traite" class="btn btn-primary small"><i data-lucide="check"></i> Traiter</button>
                        <button type="submit" name="statut" value="rejete" class="btn btn-secondary small"><i data-lucide="x"></i> Rejeter</button>
                    </div>
                </form>
            @endif

            <div class="panel detail-card" style="margin-top:16px">
                <h2 style="font-size:16px">Autres signalements sur cet élément</h2>
                @forelse($autres as $autre)
                    <a href="{{ route('back.signalements.show', $autre->getKey()) }}" class="mini-row">
                        <span>
                            <b>{{ $autre->auteur?->name ?? 'Compte supprimé' }}</b>
                            <span class="cell-sub">{{ \Illuminate\Support\Str::limit($autre->motif, 70) }}</span>
                        </span>
                        <x-status-badge :tone="$autre->statutTone()">{{ $autre->statutLabel() }}</x-status-badge>
                    </a>
                @empty
                    <p class="muted" style="margin:8px 0 0">Aucun autre signalement.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
