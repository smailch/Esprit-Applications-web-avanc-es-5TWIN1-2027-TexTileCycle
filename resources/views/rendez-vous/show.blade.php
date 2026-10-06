@extends('layouts.front')

@section('content')
<main class="container page-content">
    {{-- En-tête de page --}}
    <div class="page-title-row">
        <div>
            <p class="kicker">Détail du rendez-vous</p>
            <h1>Rendez-vous du {{ $rendezVous->date_rdv ? $rendezVous->date_rdv->format('d/m/Y') : '—' }}</h1>
            <p class="muted">Consultez les informations de votre rendez-vous.</p>
        </div>
        <a href="{{ route('front.rdv') }}" class="btn btn-secondary">
            <i data-lucide="arrow-left"></i> Retour à la liste
        </a>
    </div>

    {{-- Détail complet --}}
    <div class="panel" style="padding:24px;max-width:720px">
        {{-- Statut en évidence --}}
        <div style="margin-bottom:20px;display:flex;align-items:center;gap:12px">
            <span style="font-weight:600;font-size:.9rem;color:var(--muted)">Statut :</span>
            <x-status-badge :tone="$rendezVous->statutTone()">{{ $rendezVous->statutLabel() }}</x-status-badge>
        </div>

        {{-- Informations détaillées --}}
        <div class="form-grid" style="gap:16px">
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Vêtement</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->vetement ? $rendezVous->vetement->displayName() : '—' }}
                </p>
            </div>
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Atelier</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->atelierNom() }}
                </p>
            </div>
        </div>

        <div class="form-grid" style="gap:16px;margin-top:16px">
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Service</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->serviceNom() }}
                </p>
            </div>
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Date et heure</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->date_rdv ? $rendezVous->date_rdv->format('d/m/Y à H\hi') : '—' }}
                </p>
            </div>
        </div>

        <div class="form-grid" style="gap:16px;margin-top:16px">
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Durée estimée</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->dureeFormatee() }}
                </p>
            </div>
            <div>
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Créé le</span>
                <p style="margin-top:4px;font-size:.95rem;font-weight:500">
                    {{ $rendezVous->created_at ? $rendezVous->created_at->format('d/m/Y à H\hi') : '—' }}
                </p>
            </div>
        </div>

        @if($rendezVous->commentaire)
            <div style="margin-top:16px">
                <span style="font-size:.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.03em">Commentaire</span>
                <p style="margin-top:4px;font-size:.95rem;background:var(--bg-alt, #f5f5f5);padding:12px 16px;border-radius:8px;line-height:1.5">
                    {{ $rendezVous->commentaire }}
                </p>
            </div>
        @endif

        {{-- Boutons d'action selon le statut --}}
        <div class="modal-actions" style="margin-top:24px">
            {{-- Modifier — seulement si en_attente --}}
            @if($rendezVous->peutEtreModifie())
                <a href="{{ route('front.rdv.edit', $rendezVous->getKey()) }}" class="btn btn-primary">
                    <i data-lucide="pencil"></i> Modifier
                </a>
            @endif

            {{-- Annuler — seulement si en_attente ou confirme --}}
            @if($rendezVous->peutEtreAnnule())
                <form action="{{ route('front.rdv.annuler', $rendezVous->getKey()) }}" method="post"
                      onsubmit="return confirm('Voulez-vous vraiment annuler ce rendez-vous ?')" style="display:inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-secondary" style="color:var(--red, #e53935)">
                        <i data-lucide="x-circle"></i> Annuler le RDV
                    </button>
                </form>
            @endif

            {{-- Supprimer — seulement si en_attente ou annule --}}
            @if($rendezVous->peutEtreSupprime())
                <form action="{{ route('front.rdv.destroy', $rendezVous->getKey()) }}" method="post"
                      onsubmit="return confirm('Voulez-vous vraiment supprimer ce rendez-vous ?')" style="display:inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="color:var(--red, #e53935)">
                        <i data-lucide="trash-2"></i> Supprimer
                    </button>
                </form>
            @endif
        </div>
    </div>
</main>
@endsection
