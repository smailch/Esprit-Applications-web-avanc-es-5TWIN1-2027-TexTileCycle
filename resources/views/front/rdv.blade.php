@extends('layouts.front')

@section('content')
<main class="container page-content">
    <div class="page-title-row">
        <div>
            <p class="kicker">Planifier une réparation</p>
            <h1>Prendre rendez-vous</h1>
            <p class="muted">Choisissez votre vêtement, l'atelier et un créneau disponible.</p>
        </div>
    </div>

    <div class="ai-banner">
        <i data-lucide="sparkles"></i>
        <div>
            <strong>Créneau recommandé</strong>
            <p>Mercredi 14h — 30 min — faible affluence chez <b>Couture Plus</b></p>
        </div>
    </div>

    <div class="panel" style="padding:24px;max-width:720px">
        <form action="#" method="post">
            @csrf
            <div class="form-grid">
                <label>Vêtement
                    <select name="vetement" required>
                        <option value="">Sélectionner</option>
                        <option>Veste en jean</option>
                        <option>Pull en laine</option>
                    </select>
                </label>
                <label>Atelier
                    <select name="atelier" required>
                        <option value="">Sélectionner</option>
                        <option>Couture Plus</option>
                        <option>L'Atelier Vert</option>
                    </select>
                </label>
            </div>
            <div class="form-grid">
                <label>Service
                    <select name="service" required>
                        <option value="">Sélectionner</option>
                        <option>Retouche simple</option>
                        <option>Réparation denim</option>
                    </select>
                </label>
                <label>Date et heure
                    <input type="datetime-local" name="datetime" required>
                </label>
            </div>
            <label>Commentaire
                <textarea name="commentaire" placeholder="Précisez la nature de la réparation souhaitée..."></textarea>
            </label>
            <div class="modal-actions">
                <a href="{{ route('front.ateliers') }}" class="btn btn-secondary">Retour</a>
                <button type="submit" class="btn btn-primary">Confirmer le RDV <i data-lucide="calendar-days"></i></button>
            </div>
        </form>
    </div>
</main>
@endsection
