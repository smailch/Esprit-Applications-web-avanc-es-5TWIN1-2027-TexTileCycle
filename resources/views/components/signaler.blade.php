{{--
    Bouton « Signaler » réutilisable (module 5 — Signalements).
    Usage : <x-signaler cible-type="Atelier" :cible-id="$atelier->id" />
    cible-type : Vetement | Atelier | Association | Don | User

    Le formulaire s'ouvre dans un <dialog> (couche supérieure du navigateur) :
    il n'est jamais coupé par un conteneur en overflow: hidden (bandeau « trame », cartes…).
--}}
@props(['cibleType', 'cibleId', 'label' => 'Signaler'])

@php($idDialogue = 'signaler-'.\Illuminate\Support\Str::lower($cibleType).'-'.$cibleId)

@auth
    <button type="button" {{ $attributes->merge(['class' => 'signaler-btn']) }} data-signaler-ouvrir="{{ $idDialogue }}">
        <i data-lucide="flag" aria-hidden="true"></i> {{ $label }}
    </button>

    <dialog id="{{ $idDialogue }}" class="signaler-dialog" aria-labelledby="{{ $idDialogue }}-titre">
        <form method="post" action="{{ route('front.signalements.store') }}" class="signaler-dialog__form">
            @csrf
            <input type="hidden" name="cible_type" value="{{ $cibleType }}">
            <input type="hidden" name="cible_id" value="{{ $cibleId }}">

            <div class="signaler-dialog__tete">
                <span class="signaler-dialog__icone" aria-hidden="true"><i data-lucide="flag"></i></span>
                <div>
                    <h2 id="{{ $idDialogue }}-titre">Signaler ce contenu</h2>
                    <p>Votre signalement est transmis à l'équipe de modération TexTileCycle. Vous pourrez suivre son traitement dans « Mes signalements ».</p>
                </div>
            </div>

            <label for="{{ $idDialogue }}-motif" class="signaler-dialog__label">Pourquoi signalez-vous ce contenu ? <span aria-hidden="true">*</span></label>
            <textarea id="{{ $idDialogue }}-motif" name="motif" rows="5" required minlength="10" maxlength="2000"
                      placeholder="Décrivez le problème : informations fausses, comportement abusif, contenu inapproprié… (10 caractères minimum)"></textarea>

            <div class="signaler-dialog__actions">
                <button type="button" class="btn btn-secondary" data-signaler-fermer>Annuler</button>
                <button type="submit" class="btn btn-primary"><i data-lucide="send" aria-hidden="true"></i> Envoyer le signalement</button>
            </div>
        </form>
    </dialog>

    @once
        @push('styles')
            <style>
                .signaler-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 4px; border: 0; background: none; color: #f0a35e; font: inherit; font-weight: 700; cursor: pointer; }
                .signaler-btn svg { width: 16px; height: 16px; }
                .signaler-btn:hover { color: #ffbe82; text-decoration: underline; }
                .signaler-dialog { width: min(480px, calc(100% - 32px)); padding: 0; border: 0; border-radius: 16px; background: #fff; color: #18332a; box-shadow: 0 28px 70px #17382a40; }
                .signaler-dialog::backdrop { background: #12352480; backdrop-filter: blur(2px); }
                .signaler-dialog__form { display: grid; gap: 10px; padding: 22px; margin: 0; text-align: left; }
                .signaler-dialog__tete { display: flex; gap: 12px; align-items: flex-start; }
                .signaler-dialog__icone { width: 40px; height: 40px; flex-shrink: 0; border-radius: 12px; display: grid; place-items: center; background: #fff3e6; color: #b8651a; }
                .signaler-dialog__icone svg { width: 20px; height: 20px; }
                .signaler-dialog h2 { margin: 2px 0 4px; font-size: 17px; color: #18332a; }
                .signaler-dialog p { margin: 0; font-size: 12px; line-height: 1.5; color: #6c7d75; }
                .signaler-dialog__label { margin: 6px 0 0; font-size: 12px; font-weight: 700; color: #52635a; }
                .signaler-dialog textarea { width: 100%; box-sizing: border-box; border: 1px solid #dce6df; border-radius: 10px; padding: 10px 12px; font: inherit; font-size: 13px; color: #18332a; background: #fff; resize: vertical; }
                .signaler-dialog textarea:focus { outline: 2px solid #cfe8d3; border-color: #b9d8c0; }
                .signaler-dialog__actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
                @media (max-width: 560px) { .signaler-dialog__actions { flex-direction: column-reverse; } .signaler-dialog__actions .btn { width: 100%; justify-content: center; } }
            </style>
        @endpush
        @push('scripts')
            <script>
                document.addEventListener('click', (e) => {
                    const ouvrir = e.target.closest('[data-signaler-ouvrir]');
                    if (ouvrir) {
                        const dialogue = document.getElementById(ouvrir.dataset.signalerOuvrir);
                        if (dialogue && typeof dialogue.showModal === 'function') {
                            dialogue.showModal();
                            dialogue.querySelector('textarea').focus();
                        }
                        return;
                    }
                    if (e.target.closest('[data-signaler-fermer]')) {
                        e.target.closest('dialog').close();
                    } else if (e.target.matches('.signaler-dialog')) {
                        e.target.close(); // clic sur le fond grisé
                    }
                });
            </script>
        @endpush
    @endonce
@else
    {{-- Non connecté : le bouton mène à la page de connexion. --}}
    <a href="{{ route('front.login') }}" {{ $attributes->merge(['class' => 'signaler-btn']) }}
       style="display:inline-flex;align-items:center;gap:6px;color:#f0a35e;font-weight:700">
        <i data-lucide="flag" aria-hidden="true"></i> {{ $label }}
    </a>
@endauth
