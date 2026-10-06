@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="module-toolbar">
        <div class="chips">
            <a href="{{ route('back.signalements.index') }}" @class(['chip', 'active' => empty($filters['statut'])])>
                Tous <b>{{ array_sum($counts) }}</b>
            </a>
            @foreach($statuts as $s)
                <a href="{{ route('back.signalements.index', array_merge($filters, ['statut' => $s])) }}" @class(['chip', 'active' => ($filters['statut'] ?? null) === $s])>
                    {{ \App\Modules\Signalements\Models\Signalement::STATUT_LABELS[$s] }} <b>{{ $counts[$s] }}</b>
                </a>
            @endforeach
        </div>
        <details class="action-menu">
            <summary class="btn btn-primary small"><i data-lucide="plus"></i> Nouveau signalement</summary>
            <div class="panel action-body" style="position:absolute;right:32px;z-index:5;padding:16px;width:340px">
                <form method="post" action="{{ route('back.signalements.store') }}" class="action-body">
                    @csrf
                    <label>Élément signalé
                        <select name="cible_type" required>
                            @foreach($cibles as $cible)
                                <option value="{{ $cible }}" @selected(old('cible_type') === $cible)>{{ $cible }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Identifiant de l'élément
                        <input type="text" name="cible_id" value="{{ old('cible_id') }}" placeholder="ex : 6ab50fc6a6d0bfbca703c5cf" required>
                    </label>
                    <label>Type
                        <select name="type">
                            <option value="">Automatique</option>
                            @foreach($types as $t)
                                <option value="{{ $t }}" @selected(old('type') === $t)>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Motif
                        <textarea name="motif" required minlength="10">{{ old('motif') }}</textarea>
                    </label>
                    <button type="submit" class="btn btn-primary small">Enregistrer</button>
                </form>
            </div>
        </details>
    </div>

    <form method="get" class="inline-form panel" style="padding:14px 16px;margin-bottom:14px">
        <input type="hidden" name="statut" value="{{ $filters['statut'] ?? '' }}">
        <label>Type
            <select name="type">
                <option value="">Tous les types</option>
                @foreach($types as $t)
                    <option value="{{ $t }}" @selected(($filters['type'] ?? null) === $t)>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </label>
        <label>Élément
            <select name="cible_type">
                <option value="">Tous les éléments</option>
                @foreach($cibles as $cible)
                    <option value="{{ $cible }}" @selected(($filters['cible_type'] ?? null) === $cible)>{{ $cible }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-secondary small"><i data-lucide="filter"></i> Filtrer</button>
        @if(array_filter($filters))
            <a href="{{ route('back.signalements.index') }}" class="link-button">Réinitialiser</a>
        @endif
    </form>

    <div class="panel table-panel">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Élément signalé</th>
                    <th>Type</th>
                    <th>Motif</th>
                    <th>Auteur</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signalements as $signalement)
                    <tr>
                        <td>{{ $signalement->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <b>{{ $signalement->cibleLabel() }}</b>
                            <span class="cell-sub">{{ $signalement->cible_type }} · {{ \Illuminate\Support\Str::limit($signalement->cible_id, 12, '…') }}</span>
                        </td>
                        <td>{{ $signalement->typeLabel() }}</td>
                        <td class="motif">
                            {{ \Illuminate\Support\Str::limit($signalement->motif, 120) }}
                            @if($signalement->note_admin)
                                <span class="cell-sub">Note admin : {{ $signalement->note_admin }}</span>
                            @endif
                        </td>
                        <td>{{ $signalement->auteur?->name ?? 'Compte supprimé' }}</td>
                        <td>
                            <x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge>
                            @if($signalement->traite_le)
                                <span class="cell-sub">le {{ $signalement->traite_le->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td>
                            <details class="action-menu">
                                <summary class="link-button">Gérer <i data-lucide="chevron-down"></i></summary>
                                <div class="action-body">
                                    @if($signalement->statut === 'en_attente')
                                        <form method="post" action="{{ route('back.signalements.moderer', $signalement->getKey()) }}" class="action-body">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="note_admin" rows="2" placeholder="Note de modération (optionnelle)"></textarea>
                                            @if(in_array($signalement->cible_type, ['Atelier', 'Association', 'User'], true))
                                                <label class="check">
                                                    <input type="checkbox" name="sanctionner" value="1">
                                                    {{ $signalement->cible_type === 'User' ? 'Désactiver le compte signalé' : 'Suspendre le partenaire signalé' }}
                                                </label>
                                            @endif
                                            <div class="row-actions">
                                                <button type="submit" name="statut" value="traite" class="btn btn-primary small"><i data-lucide="check"></i> Traiter</button>
                                                <button type="submit" name="statut" value="rejete" class="btn btn-secondary small"><i data-lucide="x"></i> Rejeter</button>
                                            </div>
                                        </form>
                                    @endif

                                    <form method="post" action="{{ route('back.signalements.update', $signalement->getKey()) }}" class="action-body">
                                        @csrf
                                        @method('PUT')
                                        <select name="type">
                                            @foreach($types as $t)
                                                <option value="{{ $t }}" @selected($signalement->type === $t)>{{ ucfirst($t) }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="motif" rows="3" required minlength="10">{{ $signalement->motif }}</textarea>
                                        <textarea name="note_admin" rows="2" placeholder="Note admin">{{ $signalement->note_admin }}</textarea>
                                        <button type="submit" class="btn btn-secondary small">Enregistrer les modifications</button>
                                    </form>

                                    <form method="post" action="{{ route('back.signalements.destroy', $signalement->getKey()) }}" onsubmit="return confirm('Supprimer définitivement ce signalement ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary small btn-danger"><i data-lucide="trash-2"></i> Supprimer</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">Aucun signalement pour ces critères.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($signalements->hasPages())
            <div style="padding:16px">{{ $signalements->links() }}</div>
        @endif
    </div>
</div>
@endsection
