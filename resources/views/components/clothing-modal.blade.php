<div class="modal-backdrop" id="clothing-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-labelledby="modal-title">
        <div class="modal-head">
            <div>
                <p class="kicker">Nouveau parcours</p>
                <h2 id="modal-title">Déclarer un vêtement</h2>
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer"><i data-lucide="x"></i></button>
        </div>
        <form action="#" method="post">
            @csrf
            <div class="form-grid">
                <label>Type de vêtement
                    <select name="type" required>
                        <option value="">Choisir un type</option>
                        <option>Veste</option>
                        <option>Pantalon</option>
                        <option>Pull</option>
                        <option>Robe</option>
                    </select>
                </label>
                <label>Taille
                    <select name="taille" required>
                        <option value="">Choisir une taille</option>
                        <option>S</option>
                        <option>M</option>
                        <option>L</option>
                        <option>XL</option>
                    </select>
                </label>
            </div>
            <label>État du vêtement
                <div class="radio-row">
                    <span class="radio active">Excellent</span>
                    <span class="radio">Bon état</span>
                    <span class="radio">À réparer</span>
                </div>
            </label>
            <label>Matière
                <input type="text" name="matiere" placeholder="Ex. Denim, coton bio...">
            </label>
            <label>Description
                <textarea name="description" placeholder="Décrivez la pièce et les éventuels défauts..."></textarea>
            </label>
            <div class="upload-zone">
                <i data-lucide="upload"></i>
                <strong>Déposez une photo ici</strong>
                <span>ou cliquez pour parcourir · JPG, PNG jusqu'à 5 Mo</span>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary">Déclarer le vêtement <i data-lucide="arrow-right"></i></button>
            </div>
        </form>
    </div>
</div>
