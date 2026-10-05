@extends('layouts.front')

@section('content')
<main class="container page-content">
    {{-- En-tête de page — même design que la page existante --}}
    <div class="page-title-row">
        <div>
            <p class="kicker">Planifier une réparation</p>
            <h1>Prendre rendez-vous</h1>
            <p class="muted">Choisissez votre vêtement, l'atelier et un créneau disponible.</p>
        </div>
    </div>

    {{-- Bandeau IA « Créneau recommandé » --}}
    <div class="ai-banner">
        <i data-lucide="sparkles"></i>
        <div>
            <strong>Créneau recommandé</strong>
            <p>Mercredi 14h — 30 min — faible affluence chez <b>Couture Plus</b></p>
        </div>
    </div>

    {{-- Formulaire de création --}}
    <div class="panel" style="padding:24px;max-width:720px">
        @include('rendez-vous._form')
    </div>
</main>
@endsection
