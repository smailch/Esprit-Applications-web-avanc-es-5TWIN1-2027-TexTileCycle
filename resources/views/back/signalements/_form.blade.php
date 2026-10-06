{{--
    Formulaire partagé création / modification d'un signalement.
    Variables : $signalement, $cibles (groupe => ["Type|id" => libellé]), $auteurs, $edition (bool)
--}}
@php
    $cibleActuelle = old('cible', $signalement->cible_type ? $signalement->cible_type.'|'.$signalement->cible_id : '');
@endphp

<div class="form-card-grid">
    @unless($edition)
        <label class="span-2">Auteur du signalement <span class="req">*</span>
            <select name="user_id" @class(['is-invalid' => $errors->has('user_id')]) required>
                <option value="">— Choisir un utilisateur —</option>
                @foreach($auteurs as $auteur)
                    <option value="{{ $auteur->getKey() }}" @selected(old('user_id') === (string) $auteur->getKey())>
                        {{ $auteur->name }} — {{ $auteur->email }} ({{ $auteur->roleLabel() }})
                    </option>
                @endforeach
            </select>
            @error('user_id')<span class="error-text">{{ $message }}</span>@enderror
        </label>
    @endunless

    <label class="span-2">Élément signalé <span class="req">*</span>
        <select name="cible" @class(['is-invalid' => $errors->hasAny(['cible', 'cible_type', 'cible_id'])]) required>
            <option value="">— Choisir l'élément signalé —</option>
            @foreach($cibles as $groupe => $options)
                <optgroup label="{{ $groupe }}">
                    @foreach($options as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected($cibleActuelle === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('cible_type')<span class="error-text">{{ $message }}</span>@enderror
        @error('cible_id')<span class="error-text">{{ $message }}</span>@enderror
    </label>

    <label>Type de signalement @if($edition)<span class="req">*</span>@endif
        <select name="type" @class(['is-invalid' => $errors->has('type')]) @required($edition)>
            @unless($edition)
                <option value="">Automatique (selon l'élément)</option>
            @endunless
            @foreach(\App\Modules\Signalements\Models\Signalement::TYPE_LABELS as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected(old('type', $signalement->type) === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
        @error('type')<span class="error-text">{{ $message }}</span>@enderror
    </label>

    @if($edition)
        <label>Statut <span class="req">*</span>
            <select name="statut" @class(['is-invalid' => $errors->has('statut')]) required>
                @foreach(\App\Modules\Signalements\Models\Signalement::STATUT_LABELS as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(old('statut', $signalement->statut) === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            @error('statut')<span class="error-text">{{ $message }}</span>@enderror
        </label>
    @endif

    <label class="span-2">Motif <span class="req">*</span>
        <textarea name="motif" rows="5" minlength="10" maxlength="2000" @class(['is-invalid' => $errors->has('motif')]) required
                  placeholder="Décrivez précisément le problème constaté (10 caractères minimum)">{{ old('motif', $signalement->motif) }}</textarea>
        @error('motif')<span class="error-text">{{ $message }}</span>@enderror
    </label>

    @if($edition)
        <label class="span-2">Note de modération <small class="muted">(obligatoire en cas de rejet)</small>
            <textarea name="note_admin" rows="3" maxlength="2000" @class(['is-invalid' => $errors->has('note_admin')])>{{ old('note_admin', $signalement->note_admin) }}</textarea>
            @error('note_admin')<span class="error-text">{{ $message }}</span>@enderror
        </label>
    @endif
</div>
