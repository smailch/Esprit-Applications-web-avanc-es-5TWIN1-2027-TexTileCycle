{{--
    Fil de discussion + zone de saisie, partagé admin / utilisateurs.
    Variables : $conversation, $messages, $peutRepondre, $routePrefixe, $estAdmin
    Mis à jour toutes les quelques secondes par public/js/messagerie.js (polling).
--}}
@php
    $lecteur = auth()->user();
    $correspondant = $conversation->correspondantPour($lecteur);
    $enModeration = ! $conversation->estParticipant($lecteur);
    $luParAutre = $conversation->luParAutreCote($lecteur);
    $dernierMoi = $messages->filter(fn ($m) => (string) $m->user_id === (string) $lecteur->getKey())->last();
    $dernier = $messages->last();
@endphp

<section class="msg-fil"
         data-messagerie-fil
         data-poll-url="{{ route($routePrefixe.'.messages.index', $conversation->getKey()) }}"
         data-last="{{ $dernier?->created_at?->format('Y-m-d\TH:i:s.vP') }}"
         data-lu-autre="{{ $luParAutre?->format('Y-m-d\TH:i:s.vP') }}"
         aria-label="Conversation : {{ $conversation->sujet }}">

    <header class="msg-fil__tete">
        <a href="{{ route($routePrefixe.'.index') }}" class="icon-btn msg-retour" aria-label="Retour aux conversations"><i data-lucide="arrow-left" aria-hidden="true"></i></a>
        <span @class(['msg-avatar', 'est-partenaire' => $conversation->estAvecPartenaire()]) aria-hidden="true">{{ $correspondant['initiales'] }}</span>
        <div class="msg-fil__titre">
            <h2>{{ $correspondant['nom'] }}</h2>
            <p>
                {{ $conversation->sujet }}
                @if($estAdmin && ! $enModeration && $conversation->interlocuteur)
                    · {{ $conversation->interlocuteur->roleLabel() }} · {{ $conversation->interlocuteur->email }}
                @elseif($correspondant['detail'])
                    · {{ $correspondant['detail'] }}
                @endif
            </p>
            @if($label = $conversation->contexteLabel())
                <p class="msg-contexte">
                    <i data-lucide="link-2" aria-hidden="true"></i>
                    @if($estAdmin && $conversation->contexte_type === 'Signalement' && $conversation->contexte)
                        <a href="{{ route('back.signalements.show', $conversation->contexte_id) }}">{{ $label }}</a>
                    @elseif($estAdmin && in_array($conversation->contexte_type, ['Atelier', 'Association'], true))
                        <a href="{{ route('back.partenaires.index', ['type' => strtolower($conversation->contexte_type)]) }}">{{ $label }}</a>
                    @else
                        {{ $label }}
                    @endif
                </p>
            @endif
        </div>
        <x-status-badge :tone="$conversation->statutTone()" data-messagerie-statut>{{ $conversation->statutLabel() }}</x-status-badge>
        @if($estAdmin)
            <div class="row-actions">
                <a href="{{ route('back.messagerie.edit', $conversation->getKey()) }}" class="icon-btn" aria-label="Modifier la conversation"><i data-lucide="pencil" aria-hidden="true"></i></a>
                <form method="post" action="{{ route('back.messagerie.destroy', $conversation->getKey()) }}"
                      data-confirm="La conversation avec {{ $conversation->interlocuteur?->name ?? 'cet utilisateur' }} et tous ses messages seront définitivement supprimés."
                      data-confirm-title="Supprimer la conversation ?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="icon-btn btn-danger" aria-label="Supprimer la conversation"><i data-lucide="trash-2" aria-hidden="true"></i></button>
                </form>
            </div>
        @endif
    </header>

    <ol class="msg-fil__messages" data-messagerie-messages aria-live="polite">
        @php $jour = null; @endphp
        @foreach($messages as $message)
            @php $info = $message->pourAffichage($lecteur); @endphp
            @if($info['date'] !== $jour)
                @php $jour = $info['date']; @endphp
                <li class="msg-jour" data-jour="{{ $jour }}"><span>{{ $message->created_at->isToday() ? "Aujourd'hui" : $jour }}</span></li>
            @endif
            <li @class(['msg', 'msg--moi' => $info['moi'], 'msg--autre' => ! $info['moi'], 'msg--bloque' => $message->bloque]) data-id="{{ $info['id'] }}" data-created="{{ $info['created_at'] }}">
                <div class="msg__bulle">{{ $message->contenu }}</div>
                @if($info['alerte'])
                    <div class="msg__alerte" title="{{ $message->moderation['raison'] ?? '' }}"><i data-lucide="shield-alert" aria-hidden="true"></i> {{ $info['alerte'] }}</div>
                @endif
                <div class="msg__meta">
                    @unless($info['moi'])<span>{{ $info['auteur'] }} ·</span>@endunless
                    <time datetime="{{ $info['created_at'] }}">{{ $info['heure'] }}</time>
                    @if($info['moi'] && $dernierMoi && (string) $dernierMoi->getKey() === $info['id'])
                        <span class="msg__lu" data-messagerie-lu>· {{ $luParAutre && $luParAutre->gte($message->created_at) ? 'Lu' : 'Envoyé' }}</span>
                    @endif
                </div>
            </li>
        @endforeach
        <li id="fin" aria-hidden="true"></li>
    </ol>

    @if($enModeration)
        <p class="msg-ferme msg-moderation">
            <i data-lucide="shield-check" aria-hidden="true"></i>
            Échange entre un citoyen et un partenaire : vous le consultez en modération (lecture seule).
            <a href="{{ route('back.messagerie.edit', $conversation->getKey()) }}">Fermer la conversation</a>
        </p>
    @elseif($peutRepondre)
        <form method="post" action="{{ route($routePrefixe.'.messages.store', $conversation->getKey()) }}" class="msg-saisie" data-messagerie-form>
            @csrf
            <label class="sr-only" for="msg-contenu">Votre message</label>
            <textarea id="msg-contenu" name="contenu" rows="1" maxlength="2000" required
                      placeholder="Écrire un message… (Entrée pour envoyer, Maj+Entrée pour aller à la ligne)"
                      @class(['is-invalid' => $errors->has('contenu')])>{{ old('contenu') }}</textarea>
            <button type="submit" class="btn btn-primary" aria-label="Envoyer"><i data-lucide="send" aria-hidden="true"></i></button>
            <input type="hidden" name="confirmer" value="0" data-messagerie-confirmer>
            <p class="error-text" data-messagerie-erreur @if(! $errors->has('contenu') || session('moderation.verdict') === 'avertir') hidden @endif>{{ $errors->first('contenu') }}</p>

            {{-- Avertissement de la modération IA : l'auteur modifie son message ou confirme l'envoi. --}}
            <div class="msg-moderation-alerte" data-messagerie-avertissement role="alert" @unless(session('moderation.verdict') === 'avertir') hidden @endunless>
                <i data-lucide="shield-alert" aria-hidden="true"></i>
                <div>
                    <b>Vérification avant envoi</b>
                    <p data-messagerie-avertissement-texte>{{ session('moderation.raison') }}</p>
                    <div class="row-actions">
                        <button type="button" class="btn btn-secondary small" data-messagerie-modifier>Modifier mon message</button>
                        <button type="submit" name="confirmer" value="1" class="btn btn-primary small" data-messagerie-envoyer-quand-meme>Envoyer quand même</button>
                    </div>
                </div>
            </div>
        </form>
    @else
        <p class="msg-ferme" data-messagerie-ferme>
            <i data-lucide="lock" aria-hidden="true"></i>
            Cette conversation est fermée.
            @if($estAdmin)
                <a href="{{ route('back.messagerie.edit', $conversation->getKey()) }}">La rouvrir</a>
            @else
                <a href="{{ route($routePrefixe.'.create', ['sujet' => 'Suite : '.$conversation->sujet]) }}">Écrire un nouveau message</a>
            @endif
        </p>
    @endif
</section>
