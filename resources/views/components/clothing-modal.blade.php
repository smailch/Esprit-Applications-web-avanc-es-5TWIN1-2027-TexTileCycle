@props(['disponible' => false])

<div class="modal-backdrop" id="clothing-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-labelledby="modal-title">
        <div class="modal-head">
            <div>
                <p class="kicker">Nouveau parcours</p>
                <h2 id="modal-title">Déclarer un vêtement</h2>
                @if($disponible)
                    <p class="muted" style="margin:6px 0 0;font-size:13px">La photo est analysée pour estimer l'état et dire si une réparation a une deuxième chance.</p>
                @elseif(app()->environment('local'))
                    <p class="muted" style="margin:6px 0 0;font-size:13px">Analyse photo inactive : renseignez GEMINI_API_KEY ou OPENAI_API_KEY dans le fichier .env, puis relancez le serveur.</p>
                @endif
            </div>
            <button type="button" class="icon-btn" data-close-modal aria-label="Fermer"><i data-lucide="x"></i></button>
        </div>
        <form action="{{ route('front.vetements.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            @if(isset($errors) && $errors->any())
                <div class="field-error" style="margin-bottom:12px">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <div class="form-grid">
                <label>Type de vêtement
                    <select name="type" required>
                        <option value="">Choisir un type</option>
                        @foreach(\App\Modules\Vetements\Models\Vetement::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Taille
                    <select name="size" required>
                        <option value="">Choisir une taille</option>
                        @foreach(['XS', 'S', 'M', 'L', 'XL', '42', '44', '46'] as $size)
                            <option value="{{ $size }}" @selected(old('size') === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="span-2">Matière
                    <input type="text" name="material" value="{{ old('material') }}" list="vetement-materials" required placeholder="Choisir dans la liste ou saisir…">
                    <datalist id="vetement-materials">
                        @foreach(\App\Modules\Vetements\Models\Vetement::MATERIALS as $material)
                            <option value="{{ $material }}"></option>
                        @endforeach
                    </datalist>
                    <small class="field-hint">Suggestions : coton, denim, laine… ou texte libre.</small>
                </label>
            </div>
            <fieldset class="field" style="border:0;padding:0;margin:0">
                <legend style="margin-bottom:8px;font-size:13px;font-weight:600">État du vêtement</legend>
                <div class="radio-row" data-condition-radios>
                    @foreach(\App\Modules\Vetements\Models\Vetement::ETATS as $state)
                        <label class="radio @if(old('condition_label', 'Bon état') === $state) active @endif">
                            <input type="radio" name="condition_label" value="{{ $state }}" @checked(old('condition_label', 'Bon état') === $state) required>
                            {{ $state }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset class="field" style="border:0;padding:0;margin:16px 0 0">
                <legend style="margin-bottom:8px;font-size:13px;font-weight:600">Que souhaitez-vous faire ?</legend>
                <div class="action-choice-grid" data-action-radios>
                    <label class="action-choice @if(old('intended_action', 'reparation') === 'reparation') active @endif">
                        <input type="radio" name="intended_action" value="reparation" @checked(old('intended_action', 'reparation') === 'reparation') required>
                        <i data-lucide="wrench"></i>
                        <strong>Réparer</strong>
                        <span>Retouches, couture, remise en état</span>
                    </label>
                    <label class="action-choice @if(old('intended_action') === 'don') active @endif">
                        <input type="radio" name="intended_action" value="don" @checked(old('intended_action') === 'don')>
                        <i data-lucide="gift"></i>
                        <strong>Donner</strong>
                        <span>Offrir à une association</span>
                    </label>
                </div>
            </fieldset>
            <label>Description
                <textarea name="description" placeholder="Décrivez la pièce et les éventuels défauts...">{{ old('description') }}</textarea>
            </label>
            <label class="upload-zone" data-upload-zone @if($disponible) data-analyse-url="{{ route('front.vetements.analyser') }}" @endif>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden data-upload-input>
                <img alt="" hidden data-upload-preview class="upload-preview">
                <i data-lucide="upload"></i>
                <strong data-upload-label>Déposez une photo ici</strong>
                <span>ou cliquez pour parcourir · JPG, PNG, WebP jusqu'à 5 Mo</span>
            </label>
            <div class="analyse-ia" data-analyse-panel hidden role="status" aria-live="polite">
                <div class="analyse-ia-head">
                    <i data-lucide="sparkles"></i>
                    <strong data-analyse-title>Analyse de la photo</strong>
                    <span class="analyse-badge" data-analyse-badge></span>
                </div>
                <p data-analyse-resume></p>
                <ul data-analyse-defauts></ul>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close-modal>Annuler</button>
                <button type="submit" class="btn btn-primary">Déclarer le vêtement <i data-lucide="arrow-right"></i></button>
            </div>
        </form>
    </div>
</div>
