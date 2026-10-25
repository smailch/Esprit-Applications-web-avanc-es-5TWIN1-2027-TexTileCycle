@props(['disponible' => false])

@if($disponible)
<div class="modal-backdrop" id="style-modal" aria-hidden="true">
    <div class="modal modal-style" role="dialog" aria-labelledby="style-modal-title">
        <div class="modal-head">
            <div>
                <p class="kicker">Réparation stylée</p>
                <h2 id="style-modal-title">Nouveau style en 3D</h2>
                <p class="muted" style="margin:6px 0 0;font-size:13px">La photo d'une pièce à réparer sert de modèle. Le styliste propose un nouveau style, puis une maquette 3D du vêtement réparé.</p>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer"><i data-lucide="x"></i></button>
        </div>
        <form data-style-form data-style-url="{{ route('front.vetements.style') }}">
            @csrf
            <label class="upload-zone" data-upload-zone>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden data-upload-input data-style-photo>
                <img alt="" hidden data-upload-preview class="upload-preview">
                <i data-lucide="upload"></i>
                <strong data-upload-label>Photo réelle du vêtement à réparer</strong>
                <span>JPG, PNG ou WebP · 5 Mo maximum</span>
            </label>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Fermer</button>
                <button type="submit" class="btn btn-primary" data-style-envoyer>Construire la maquette 3D</button>
            </div>
        </form>
        <p class="style-message" data-style-message hidden></p>
        <div class="style-resultat" data-style-resultat hidden>
            <div class="style-scene">
                <canvas data-style-canvas width="640" height="420" aria-label="Maquette 3D du vêtement restylé"></canvas>
                <p class="muted small">Glissez pour tourner le vêtement après le nouveau style.</p>
            </div>
            <div class="style-fiche">
                <p class="kicker" data-style-nom></p>
                <h3 data-style-titre></h3>
                <p data-style-description></p>
                <ul data-style-gestes></ul>
                <figure data-style-figure hidden>
                    <img alt="Rendu du vêtement après le nouveau style" data-style-vue>
                    <figcaption class="muted small">Rendu du vêtement après le nouveau style.</figcaption>
                </figure>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('js/style-vetement.js') }}"></script>
@endpush
@endif
