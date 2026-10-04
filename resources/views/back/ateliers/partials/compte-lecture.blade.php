{{-- Compte rattaché, non modifiable. $userIdCache : valeur à renvoyer en champ caché (formulaire admin), sinon rien. --}}
<div class="ab-field">
    <span class="ab-label" id="compte-label">Compte utilisateur</span>
    <div class="ab-readonly" aria-labelledby="compte-label">
        <i data-lucide="user-round" aria-hidden="true"></i>
        <div>
            <b>{{ $compte?->name ?? 'Compte introuvable' }}</b>
            <span>{{ $compte?->email }}</span>
        </div>
        <i data-lucide="lock" aria-hidden="true" class="ab-readonly__lock"></i>
    </div>
    <p class="ab-hint">{{ $aide ?? 'Le compte rattaché ne se modifie pas depuis ce formulaire.' }}</p>
    @isset($userIdCache)
        <input type="hidden" name="user_id" value="{{ $userIdCache }}">
        @error('user_id')
            <p class="ab-error"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $message }}</p>
        @enderror
    @endisset
</div>
