@extends('layouts.front')

@section('title', 'Rendez-vous chez '.$atelier->nom)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/rdv.css') }}">
@endpush

@php
    $atelierId = (string) $atelier->getKey();
    $telLien = $atelier->telephoneLien();
    $vetementChoisi = (string) old('vetement', '');
@endphp

@section('content')
<main class="container page-content rdv-page">
    <div class="page-title-row">
        <div>
            <p class="kicker">Planifier une réparation</p>
            <h1>Prendre rendez-vous</h1>
            <p class="muted">Choisissez le service, votre vêtement et un créneau pendant les horaires d'ouverture.</p>
        </div>
    </div>

    <div class="ai-banner">
        <i data-lucide="calendar-check" aria-hidden="true"></i>
        <div>
            <strong>Demande de rendez-vous chez {{ $atelier->nom }}</strong>
            <p>{{ $atelier->nom }} confirmera votre créneau. Les horaires sont exprimés en heure de Tunis.</p>
        </div>
    </div>

    <div class="rdv-layout">
        <section class="panel rdv-atelier" aria-labelledby="rdv-atelier-titre">
            <div class="rdv-atelier__head">
                <span class="rdv-atelier__avatar rdv-atelier__avatar--{{ $atelier->avatarTone() }}" aria-hidden="true">{{ $atelier->initiales() }}</span>
                <div>
                    <p class="kicker">Atelier choisi</p>
                    <h2 id="rdv-atelier-titre">{{ $atelier->nom }}</h2>
                </div>
            </div>
            <ul class="rdv-atelier__infos">
                @if ($atelier->ville)
                    <li><i data-lucide="map-pin" aria-hidden="true"></i> {{ $atelier->ville }}</li>
                @endif
                @if ($telLien)
                    <li><i data-lucide="phone" aria-hidden="true"></i> <a href="{{ $telLien }}">{{ $atelier->telephone }}</a></li>
                @endif
            </ul>

            <h3 class="rdv-atelier__subtitle">Horaires d'ouverture</h3>
            <ul class="rdv-horaires">
                @foreach ($semaine as $jour => $plages)
                    <li>
                        <span>{{ ucfirst($jour) }}</span>
                        <span>
                            @if (empty($plages))
                                <span class="muted">Fermé</span>
                            @else
                                {{ implode(', ', array_map(fn ($p) => $p[0].' – '.$p[1], $plages)) }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>

            <div class="rdv-atelier__links">
                <a href="{{ route('front.ateliers.show', ['id' => $atelierId]) }}" class="link-button">Voir la fiche</a>
                <a href="{{ route('front.ateliers') }}" class="link-button">Changer d'atelier</a>
            </div>
        </section>

        <div class="panel rdv-form-panel">
            @if ($services->isEmpty())
                <p class="rdv-empty">
                    {{ $atelier->nom }} n'a pas encore publié de services réservables en ligne.
                    @if ($telLien)
                        Contactez-le au <a href="{{ $telLien }}">{{ $atelier->telephone }}</a>.
                    @endif
                </p>
            @else
                <form action="{{ route('front.rdv.store') }}" method="post" novalidate>
                    @csrf
                    <input type="hidden" name="atelier" value="{{ $atelierId }}">

                    <label for="rdv-service">Service
                        <select id="rdv-service" name="service" required @error('service') aria-invalid="true" aria-describedby="rdv-service-error" @enderror>
                            <option value="">Sélectionner un service</option>
                            @foreach ($services as $service)
                                @php
                                    $details = array_filter([$service->dureeFormatee(), $service->prixFormate()]);
                                @endphp
                                <option value="{{ $service->getKey() }}" @selected($serviceSelectionne === (string) $service->getKey())>
                                    {{ $service->nom }}@if ($details) — {{ implode(' · ', $details) }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('service')<span class="rdv-error" id="rdv-service-error">{{ $message }}</span>@enderror
                    </label>

                    <label for="rdv-vetement">Vêtement concerné <span class="muted">(facultatif)</span>
                        <select id="rdv-vetement" name="vetement" @error('vetement') aria-invalid="true" aria-describedby="rdv-vetement-error" @enderror>
                            <option value="">Aucun vêtement précisé</option>
                            @foreach ($vetements as $vetement)
                                <option value="{{ $vetement->getKey() }}" @selected($vetementChoisi === (string) $vetement->getKey())>{{ $vetement->displayName() }}</option>
                            @endforeach
                        </select>
                        @error('vetement')<span class="rdv-error" id="rdv-vetement-error">{{ $message }}</span>@enderror
                        @if ($vetements->isEmpty())
                            <span class="field-hint">Aucun vêtement déclaré pour une réparation. <a href="{{ route('front.vetements') }}">Déclarer un vêtement</a></span>
                        @endif
                    </label>

                    <div class="form-grid">
                        <label for="rdv-date">Date
                            <input id="rdv-date" type="date" name="date" value="{{ old('date') }}" min="{{ $dateMin }}" required @error('date') aria-invalid="true" aria-describedby="rdv-date-error" @enderror>
                            @error('date')<span class="rdv-error" id="rdv-date-error">{{ $message }}</span>@enderror
                        </label>
                        <label for="rdv-heure">Heure
                            <input id="rdv-heure" type="time" name="heure" value="{{ old('heure') }}" step="300" required @error('heure') aria-invalid="true" aria-describedby="rdv-heure-error" @enderror>
                            @error('heure')<span class="rdv-error" id="rdv-heure-error">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <label for="rdv-notes">Commentaire
                        <textarea id="rdv-notes" name="notes" maxlength="1000" placeholder="Précisez la nature de la réparation souhaitée...">{{ old('notes') }}</textarea>
                        @error('notes')<span class="rdv-error">{{ $message }}</span>@enderror
                    </label>

                    <div class="modal-actions">
                        <a href="{{ route('front.ateliers.show', ['id' => $atelierId]) }}" class="btn btn-secondary">Retour</a>
                        <button type="submit" class="btn btn-primary">Envoyer la demande <i data-lucide="calendar-days" aria-hidden="true"></i></button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</main>
@endsection
