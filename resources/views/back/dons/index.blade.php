@extends('layouts.back')

@section('page-title', 'Gestion des dons')

@section('content')
<div class="module">

    {{-- Toolbar --}}
    <div class="module-toolbar">
        <form method="GET" class="filter-row">
            <select name="statut" class="select-btn" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                @foreach([
                    'en_attente' => 'En attente',
                    'accepte'    => 'Accepté',
                    'refuse'     => 'Refusé',
                ] as $val => $label)
                    <option value="{{ $val }}" {{ $statut === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        {{-- KPI rapide --}}
        <div class="kpi-row-mini">
            <span class="kpi-chip orange">
                <i data-lucide="clock"></i>
                {{ $dons->where('statut', 'en_attente')->count() }} en attente
            </span>
            <span class="kpi-chip green">
                <i data-lucide="check-circle"></i>
                {{ $dons->where('statut', 'accepte')->count() }} acceptés
            </span>
            <span class="kpi-chip red">
                <i data-lucide="x-circle"></i>
                {{ $dons->where('statut', 'refuse')->count() }} refusés
            </span>
        </div>
    </div>

    {{-- Table --}}
    <div class="panel table-panel">
        <table>
            <thead>
                <tr>
                    <th>Vêtement</th>
                    <th>Association</th>
                    <th>Donateur</th>
                    <th>Message</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dons as $don)
                    <tr>
                        <td>
                            <div class="table-item">
                                <div class="table-thumb">
                                    @if($don->vetement?->image_path)
                                        <img src="{{ asset('storage/'.$don->vetement->image_path) }}"
                                             alt="{{ $don->vetement->displayName() }}">
                                    @else
                                        <i data-lucide="shirt"></i>
                                    @endif
                                </div>
                                <b>{{ $don->vetement?->displayName() ?? '—' }}</b>
                            </div>
                        </td>
                        <td>
                            <span class="muted small">
                                <i data-lucide="heart-handshake" style="width:12px;height:12px"></i>
                                {{ $don->association?->nom ?? '—' }}
                            </span>
                        </td>
                        <td class="muted small">{{ $don->donateur?->name ?? '—' }}</td>
                        <td class="muted small">{{ Str::limit($don->message, 50) ?: '—' }}</td>
                        <td>
                            <x-status-badge :tone="$don->statutTone()">
                                {{ $don->statutLabel() }}
                            </x-status-badge>
                        </td>
                        <td class="muted small">
                            {{ $don->created_at->format('d/m/Y') }}
                            @if($don->date_reponse)
                                <br><span style="color:var(--muted);font-size:11px">
                                    Réponse : {{ $don->date_reponse->format('d/m/Y') }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="action-row">
                                @if($don->isPending())
                                    {{-- Accepter --}}
                                    <form method="POST"
                                          action="{{ route('back.dons.accept', $don->_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary small" title="Accepter">
                                            <i data-lucide="check"></i> Accepter
                                        </button>
                                    </form>
                                    {{-- Refuser --}}
                                    <form method="POST"
                                          action="{{ route('back.dons.refuse', $don->_id) }}"
                                          data-confirm="Le donateur sera informé que sa proposition n'a pas été retenue."
                                          data-confirm-title="Refuser ce don ?"
                                          data-confirm-button="Refuser le don"
                                          data-confirm-tone="warning">
                                        @csrf
                                        <button type="submit" class="btn btn-danger small" title="Refuser">
                                            <i data-lucide="x"></i> Refuser
                                        </button>
                                    </form>
                                @else
                                    {{-- Supprimer --}}
                                    <form method="POST"
                                          action="{{ route('back.dons.destroy', $don->_id) }}"
                                          data-confirm="Ce don sera définitivement supprimé. Cette action est irréversible."
                                          data-confirm-title="Supprimer ce don ?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="icon-btn danger" title="Supprimer">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-cell">
                            <div class="empty-state compact">
                                <i data-lucide="gift"></i>
                                <p>Aucun don à afficher.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
