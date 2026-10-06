@extends('layouts.front')

@section('content')
<main class="container page-content">
    {{-- En-tête de page --}}
    <div class="page-title-row">
        <div>
            <p class="kicker">Modifier le rendez-vous</p>
            <h1>Modifier le rendez-vous</h1>
            <p class="muted">Vous pouvez modifier les détails tant que le RDV est en attente.</p>
        </div>
    </div>

    {{-- Formulaire de modification (même design, pré-rempli) --}}
    <div class="panel" style="padding:24px;max-width:720px">
        @include('rendez-vous._form', ['rendezVous' => $rendezVous])
    </div>
</main>
@endsection
