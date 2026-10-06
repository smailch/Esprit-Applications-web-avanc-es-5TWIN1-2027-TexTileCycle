@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@php
    use App\Modules\Signalements\Models\Signalement;
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="module-toolbar">
        <div class="chips">
            <a href="{{ route('back.signalements.index', array_diff_key($filters, ['statut' => 1])) }}" @class(['chip', 'active' => empty($filters['statut'])])>
                Tous <b>{{ array_sum($counts) }}</b>
            </a>
            @foreach(Signalement::STATUT_LABELS as $statut => $libelle)
                <a href="{{ route('back.signalements.index', array_merge($filters, ['statut' => $statut])) }}" @class(['chip', 'active' => ($filters['statut'] ?? null) === $statut])>
                    {{ $libelle }} <b>{{ $counts[$statut] }}</b>
                </a>
            @endforeach
        </div>
        <a href="{{ route('back.signalements.create') }}" class="btn btn-primary small"><i data-lucide="plus"></i> Nouveau signalement</a>
    </div>

    <form method="get" class="inline-form panel" style="padding:14px 16px;margin-bottom:14px">
        @if(! empty($filters['statut']))
            <input type="hidden" name="statut" value="{{ $filters['statut'] }}">
        @endif
        <label>Type
            <select name="type">
                <option value="">Tous les types</option>
                @foreach(Signalement::TYPE_LABELS as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(($filters['type'] ?? null) === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
        </label>
        <label>Élément
            <select name="cible_type">
                <option value="">Tous les éléments</option>
                @foreach(Signalement::CIBLES as $alias => $config)
                    <option value="{{ $alias }}" @selected(($filters['cible_type'] ?? null) === $alias)>{{ $config['nom'] }}</option>
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
                            <span class="cell-sub">{{ $signalement->cibleNom() }}</span>
                        </td>
                        <td>{{ $signalement->typeLabel() }}</td>
                        <td class="motif">{{ \Illuminate\Support\Str::limit($signalement->motif, 90) }}</td>
                        <td>{{ $signalement->auteur?->name ?? 'Compte supprimé' }}</td>
                        <td><x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('back.signalements.show', $signalement->getKey()) }}" class="icon-btn" title="Voir le détail" aria-label="Voir"><i data-lucide="eye"></i></a>
                                <a href="{{ route('back.signalements.edit', $signalement->getKey()) }}" class="icon-btn" title="Modifier" aria-label="Modifier"><i data-lucide="pencil"></i></a>
                                <form method="post" action="{{ route('back.signalements.destroy', $signalement->getKey()) }}" onsubmit="return confirm('Supprimer définitivement ce signalement ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn btn-danger" title="Supprimer" aria-label="Supprimer"><i data-lucide="trash-2"></i></button>
                                </form>
                            </div>
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
