@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ateliers-back.css') }}">
@endpush

@section('content')
<div class="ab-page">
    <p class="ab-retour"><a href="{{ route('back.ateliers') }}" class="link-button"><i data-lucide="arrow-left" aria-hidden="true"></i> Ateliers & services</a></p>
    @include('back.ateliers.partials.flash')

    <section class="panel ab-passeports-intro" aria-labelledby="ab-passeports-admin-titre">
        <div class="ab-passeports-intro__icone" aria-hidden="true"><i data-lucide="qr-code"></i></div>
        <div>
            <h2 id="ab-passeports-admin-titre">Passeports de réparation</h2>
            <p>Générés par les ateliers après une réparation terminée. En cas de fraude, révoquez un passeport :
               sa page publique indique alors qu'il n'est plus valide. Rien n'est supprimé.</p>
        </div>
        <p class="ab-passeports-intro__compte"><strong>{{ $passeports->count() }}</strong> passeport{{ $passeports->count() > 1 ? 's' : '' }} · {{ $nbRevoques }} révoqué{{ $nbRevoques > 1 ? 's' : '' }}</p>
    </section>

    @if ($passeports->isEmpty())
        <section class="panel ab-empty" aria-labelledby="ab-passeports-admin-vide">
            <div class="ab-empty__icon" aria-hidden="true"><i data-lucide="qr-code"></i></div>
            <h2 id="ab-passeports-admin-vide">Aucun passeport pour l'instant</h2>
            <p>Les ateliers génèrent les passeports depuis leur espace, onglet « Passeports ».</p>
        </section>
    @else
        <div class="panel table-panel ab-table-panel">
            <table class="ab-table">
                <caption class="sr-only">Tous les passeports de réparation</caption>
                <thead>
                    <tr>
                        <th scope="col">N°</th>
                        <th scope="col">Pièce</th>
                        <th scope="col">Réparation</th>
                        <th scope="col">Atelier</th>
                        <th scope="col">Réparée le</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($passeports as $passeport)
                        <tr>
                            <td data-label="N°" class="ab-cell-primary"><b>{{ $passeport->numeroCourt() }}</b></td>
                            <td data-label="Pièce">{{ $passeport->vetementAffiche() }}</td>
                            <td data-label="Réparation">{{ $passeport->reparationAffichee() }}</td>
                            <td data-label="Atelier">{{ $passeport->info('atelier') }}</td>
                            <td data-label="Réparée le">{{ $passeport->dateReparationAffichee() }}</td>
                            <td data-label="Statut">
                                @if ($passeport->estRevoque())
                                    <x-status-badge tone="red">Révoqué</x-status-badge>
                                @else
                                    <x-status-badge tone="green">Valide</x-status-badge>
                                @endif
                            </td>
                            <td data-label="Actions" class="ab-actions-cell">
                                <div class="ab-actions">
                                    <a href="{{ route('front.passeport', ['code' => $passeport->code]) }}" class="icon-btn" target="_blank" rel="noopener"
                                       title="Voir la page publique" aria-label="Voir la page publique du passeport {{ $passeport->numeroCourt() }} (nouvel onglet)">
                                        <i data-lucide="external-link" aria-hidden="true"></i>
                                    </a>
                                    <form method="post" action="{{ route('back.ateliers.passeports.revocation', ['id' => (string) $passeport->getKey()]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="revoquer" value="{{ $passeport->estRevoque() ? '0' : '1' }}">
                                        @if ($passeport->estRevoque())
                                            <button type="submit" class="btn btn-secondary small">Rétablir</button>
                                        @else
                                            <button type="submit" class="btn btn-secondary small ab-btn-revoquer">Révoquer</button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
