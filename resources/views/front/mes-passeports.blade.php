@extends('layouts.trame')

@section('title', 'Mes passeports de réparation')

@push('styles')
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="{{ asset('css/passeport.css') }}">
@endpush

@section('bandeau')
    <nav class="tr-ariane" aria-label="Fil d'Ariane">
        <ol>
            <li><a href="{{ route('front.home') }}">Accueil</a></li>
            <li><span aria-current="page">Mes passeports</span></li>
        </ol>
    </nav>
    <div class="tr-bandeau__grille">
        <div class="tr-bandeau__principal">
            <h1>Mes passeports de réparation</h1>
            <p class="tr-bandeau__intro">Chaque pièce réparée par un atelier partenaire reçoit un passeport : montrez son QR code
               quand vous la revendez ou la donnez, il prouve qu'elle a été réparée par un professionnel.</p>
        </div>
    </div>
@endsection

@section('content')
<main class="container page-content pp">
    @if ($passeports->isEmpty())
        <section class="tr-fil" aria-labelledby="mp-vide">
            <h2 id="mp-vide">Aucun passeport pour l'instant</h2>
            <p class="muted">Après une réparation terminée, l'atelier génère le passeport de votre pièce : il apparaîtra ici.</p>
            <p><a href="{{ route('front.ateliers') }}" class="btn btn-primary">
                <i data-lucide="search" aria-hidden="true"></i> Trouver un atelier
            </a></p>
        </section>
    @else
        <ul class="mp-liste">
            @foreach ($passeports as $passeport)
                <li @class(['mp-carte', 'is-revoque' => $passeport->estRevoque()])>
                    <img class="mp-carte__qr" src="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" alt="QR code du passeport {{ $passeport->numeroCourt() }}" width="88" height="88" loading="lazy">
                    <div class="mp-carte__corps">
                        <h2>{{ $passeport->vetementAffiche() }}</h2>
                        <p>{{ $passeport->reparationAffichee() }} · {{ $passeport->info('atelier') }}</p>
                        <p>{{ $passeport->dateReparationAffichee() }} · n° {{ $passeport->numeroCourt() }}</p>
                        @if ($passeport->estRevoque())
                            <x-status-badge tone="red">Révoqué</x-status-badge>
                        @endif
                        <div class="mp-carte__liens">
                            <a href="{{ route('front.passeport', ['code' => $passeport->code]) }}">Voir le passeport</a>
                            <a href="{{ route('front.passeport.qr', ['code' => $passeport->code]) }}" download="passeport-{{ $passeport->numeroCourt() }}.svg">Télécharger le QR</a>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</main>
@endsection
