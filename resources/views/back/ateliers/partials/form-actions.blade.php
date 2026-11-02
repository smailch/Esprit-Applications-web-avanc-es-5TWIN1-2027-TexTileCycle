<div class="ab-form__actions">
    <p class="ab-form__legend"><span class="ab-required" aria-hidden="true">*</span> Champ obligatoire</p>
    @if (! empty($annulerUrl))
        <a href="{{ $annulerUrl }}" class="btn btn-secondary">Annuler</a>
    @endif
    <button type="submit" class="btn btn-primary">
        <i data-lucide="save" aria-hidden="true"></i> {{ $libelle }}
    </button>
</div>
