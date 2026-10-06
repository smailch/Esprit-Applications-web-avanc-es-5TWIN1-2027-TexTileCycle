@extends('layouts.front')

@php
    $atelierNom = $ateliers[$atelierPreselect ?? ''] ?? null;
    $vetementNom = null;
    if (! empty($vetementPreselect)) {
        $vetementNom = optional($vetements->first(fn ($v) => (string) $v->getKey() === (string) $vetementPreselect))->displayName();
    }
@endphp

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Planifier une réparation</p>
            <h1>Prendre rendez-vous</h1>
            @if($vetementNom || $atelierNom)
                <p class="muted">Complétez la date, la durée et un commentaire : le vêtement et l'atelier sont déjà renseignés.</p>
            @else
                <p class="muted">Choisissez votre vêtement, l'atelier et un créneau disponible.</p>
            @endif
        </div>
    </div>

    @if($vetementNom || $atelierNom)
        <div class="ai-banner">
            <i data-lucide="calendar-check"></i>
            <div>
                <strong>Formulaire prérempli</strong>
                <p>
                    @if($vetementNom) Vêtement : <b>{{ $vetementNom }}</b>@endif
                    @if($vetementNom && $atelierNom) — @endif
                    @if($atelierNom) Atelier : <b>{{ $atelierNom }}</b>@endif
                </p>
            </div>
        </div>
    @endif

    <div class="panel" style="padding:24px;max-width:720px">
        @include('rendez-vous._form')
    </div>
</main>
@endsection
