@extends('layouts.front')

@section('content')
<main class="page-content container" style="padding:40px 0 64px">
    <div class="page-title-row">
        <div>
            <p class="kicker">Modération</p>
            <h1>Mes signalements</h1>
            <p class="muted">Suivez le traitement des contenus que vous avez signalés à l'équipe TexTileCycle.</p>
        </div>
    </div>

    <div class="panel table-panel" style="margin-top:20px">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Élément signalé</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Réponse de l'équipe</th>
                </tr>
            </thead>
            <tbody>
                @forelse($signalements as $signalement)
                    <tr>
                        <td>{{ $signalement->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <b>{{ $signalement->cibleLabel() }}</b>
                            <span style="display:block;color:var(--muted);font-size:11px">{{ $signalement->cibleNom() }}</span>
                        </td>
                        <td style="max-width:340px;white-space:normal">{{ \Illuminate\Support\Str::limit($signalement->motif, 120) }}</td>
                        <td><x-status-badge :tone="$signalement->statutTone()">{{ $signalement->statutLabel() }}</x-status-badge></td>
                        <td style="max-width:260px;white-space:normal">{{ $signalement->estEnAttente() ? '—' : ($signalement->note_admin ?: 'Traité par l\'équipe.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--muted);padding:40px">
                            Vous n'avez encore rien signalé. Un contenu vous semble inapproprié ? Utilisez le bouton « Signaler » sur la fiche concernée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($signalements->hasPages())
            <div style="padding:16px">{{ $signalements->links() }}</div>
        @endif
    </div>
</main>
@endsection
