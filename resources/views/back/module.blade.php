@extends('layouts.back')

@section('page-title', $pageTitle)

@section('content')
<div class="module">
    <div class="module-toolbar">
        <div class="filter-row">
            <button type="button" class="select-btn">Tous les statuts <i data-lucide="chevron-down"></i></button>
            <button type="button" class="select-btn">Ce mois-ci <i data-lucide="chevron-down"></i></button>
        </div>
        <button type="button" class="btn btn-primary"><i data-lucide="plus"></i> Ajouter</button>
    </div>

    <div class="panel table-panel">
        <table>
            <thead>
                <tr>
                    <th>Élément</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Propriétaire</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows ?? [] as $row)
                    <tr>
                        <td>
                            <div class="table-item">
                                <div class="table-thumb"><i data-lucide="shirt"></i></div>
                                <b>{{ $row['name'] }}</b>
                            </div>
                        </td>
                        <td><x-status-badge :tone="$row['tone']">{{ $row['status'] }}</x-status-badge></td>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['owner'] }}</td>
                        <td><button type="button" class="icon-btn" aria-label="Actions"><i data-lucide="more-horizontal"></i></button></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--muted);padding:40px">Aucun élément pour le moment — module {{ $pageTitle }} (statique)</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
