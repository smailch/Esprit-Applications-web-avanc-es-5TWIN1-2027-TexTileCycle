{{--
    Bouton « Signaler » réutilisable (module 5 — Signalements).
    Usage : <x-signaler cible-type="Atelier" :cible-id="$atelier->id" />
    cible-type : Vetement | Atelier | Association | Don | User
--}}
@props(['cibleType', 'cibleId', 'label' => 'Signaler'])

@auth
    <details {{ $attributes->merge(['class' => 'signaler']) }} style="display:inline-block;position:relative">
        <summary class="link-button" style="cursor:pointer;list-style:none;color:#b8651a">
            <i data-lucide="flag"></i> {{ $label }}
        </summary>
        <form method="post" action="{{ route('front.signalements.store') }}"
              style="position:absolute;z-index:20;right:0;margin-top:8px;width:300px;padding:14px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 30px #18332a1f">
            @csrf
            <input type="hidden" name="cible_type" value="{{ $cibleType }}">
            <input type="hidden" name="cible_id" value="{{ $cibleId }}">
            <label>Pourquoi signalez-vous ce contenu ?
                <textarea name="motif" required minlength="10" maxlength="2000" placeholder="Décrivez le problème (contenu inapproprié, informations fausses, comportement abusif…)"></textarea>
            </label>
            <button type="submit" class="btn btn-primary small full">Envoyer le signalement</button>
        </form>
    </details>
@endauth
