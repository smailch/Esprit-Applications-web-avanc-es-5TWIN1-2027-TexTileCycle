{{--
    Avis clients de la fiche atelier. Variables : $atelier, $avisResume (moyenne, total, repartition), $avisRecents,
    $monAvis (avis du client connecté ou null), $peutDonnerAvis (rendez-vous terminé chez cet atelier), $rdvUrl.
    Contrôles de saisie uniquement en PHP (AvisRequest) ; avis.js n'ajoute que le libellé de la note et le compteur.
--}}
@php
    $libelles = \App\Modules\Ateliers\Models\Avis::LIBELLES;
    $maxCommentaire = \App\Modules\Ateliers\Models\Avis::COMMENTAIRE_MAX;
    $erreursAvis = $errors->getBag(\App\Modules\Ateliers\Services\AvisService::BAG);
    $erreurNote = $erreursAvis->first('note');
    $erreurCommentaire = $erreursAvis->first('commentaire');
    $noteChoisie = (int) old('note', $monAvis?->note);
    $commentaire = (string) old('commentaire', $monAvis?->commentaire);
    $total = $avisResume['total'];
    $idAtelier = (string) $atelier->getKey();
@endphp

<section id="avis" class="atelier-section avis tr-fil" aria-labelledby="titre-avis">
    <div class="atelier-section__head">
        <h2 id="titre-avis">Avis clients</h2>
        <p class="muted avis__verifies"><i data-lucide="badge-check" aria-hidden="true"></i> Avis vérifiés, laissés après une réparation</p>
    </div>

    @if (session('avis_succes'))
        <p class="avis-message" role="status"><i data-lucide="check-circle-2" aria-hidden="true"></i> {{ session('avis_succes') }}</p>
    @endif

    @if ($total > 0)
        <div class="avis-resume">
            <div class="avis-resume__note">
                <strong>{{ number_format($avisResume['moyenne'], 1, ',', '') }}</strong>
                @include('front.partials.etoiles', ['note' => $avisResume['moyenne'], 'taille' => 'lg'])
                <span class="muted">{{ $total }} avis</span>
            </div>

            <ul class="avis-barres" aria-label="Répartition des notes">
                @foreach ($avisResume['repartition'] as $note => $nombre)
                    <li>
                        <span class="avis-barres__note">{{ $note }} <i data-lucide="star" aria-hidden="true"></i></span>
                        <progress max="{{ $total }}" value="{{ $nombre }}" aria-label="{{ $nombre }} avis à {{ $note }} étoile{{ $note > 1 ? 's' : '' }}"></progress>
                        <span class="avis-barres__nombre" aria-hidden="true">{{ $nombre }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="atelier-section__empty">Pas encore d'avis vérifié pour cet atelier.</p>
    @endif

    {{-- Espace du client : invitation, son avis, ou le formulaire. --}}
    <div class="avis-moi">
        @guest
            <p class="avis-invite">
                <i data-lucide="message-square-heart" aria-hidden="true"></i>
                <span>Vous avez fait réparer un vêtement ici ? <a href="{{ route('front.login') }}">Connectez-vous</a> pour donner votre avis.</span>
            </p>
        @else
            @if ($monAvis)
                <article class="avis-carte avis-carte--moi" aria-labelledby="titre-mon-avis">
                    <div class="avis-carte__tete">
                        <h3 id="titre-mon-avis">Votre avis</h3>
                        @include('front.partials.etoiles', ['note' => $monAvis->note])
                        <span class="avis-carte__libelle">{{ $monAvis->libelle() }}</span>
                    </div>
                    @if (filled($monAvis->commentaire))
                        <p class="avis-carte__texte">{{ $monAvis->commentaire }}</p>
                    @endif

                    <details class="avis-supprimer">
                        <summary>Supprimer mon avis</summary>
                        <form method="post" action="{{ route('front.ateliers.avis.destroy', ['id' => $idAtelier]) }}">
                            @csrf
                            @method('DELETE')
                            <p>Votre avis sera retiré et la note de l'atelier recalculée.</p>
                            <button type="submit" class="btn btn-secondary small">Confirmer la suppression</button>
                        </form>
                    </details>
                </article>
            @endif

            @if ($peutDonnerAvis)
                {{-- Le formulaire de modification reste replié tant qu'il n'y a pas d'erreur à corriger. --}}
                <details class="avis-formulaire" @if (! $monAvis || $erreursAvis->any()) open @endif>
                    <summary>{{ $monAvis ? 'Modifier mon avis' : 'Donner mon avis' }}</summary>

                    <form method="post" action="{{ route('front.ateliers.avis.store', ['id' => $idAtelier]) }}" class="avis-form" data-avis-form novalidate>
                        @csrf

                        <fieldset @class(['avis-note', 'has-error' => $erreurNote]) @if ($erreurNote) aria-describedby="avis-note-erreur" @endif>
                            <legend>Votre note</legend>
                            <div class="avis-note__choix">
                                @foreach (range(5, 1) as $n)
                                    <input type="radio" class="sr-only" id="avis-note-{{ $n }}" name="note" value="{{ $n }}" data-libelle="{{ $libelles[$n] }}" @checked($noteChoisie === $n)>
                                    <label for="avis-note-{{ $n }}" title="{{ $libelles[$n] }}">
                                        <i data-lucide="star" aria-hidden="true"></i>
                                        <span class="sr-only">{{ $n }} étoile{{ $n > 1 ? 's' : '' }} : {{ $libelles[$n] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <span class="avis-note__libelle" data-avis-libelle aria-hidden="true">{{ $libelles[$noteChoisie] ?? 'Choisissez une note' }}</span>
                            @if ($erreurNote)
                                <p id="avis-note-erreur" class="diag-erreur"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $erreurNote }}</p>
                            @endif
                        </fieldset>

                        <div @class(['diag-champ', 'avis-champ', 'has-error' => $erreurCommentaire])>
                            <label for="avis-commentaire">Votre commentaire <span class="diag-facultatif">(facultatif)</span></label>
                            <textarea id="avis-commentaire" name="commentaire" rows="3" data-avis-commentaire data-max="{{ $maxCommentaire }}"
                                      placeholder="Qualité de la réparation, délai, accueil…"
                                      aria-describedby="avis-compteur{{ $erreurCommentaire ? ' avis-commentaire-erreur' : '' }}">{{ $commentaire }}</textarea>
                            <span id="avis-compteur" class="avis-compteur" data-avis-compteur>{{ mb_strlen($commentaire) }} / {{ $maxCommentaire }}</span>
                            @if ($erreurCommentaire)
                                <p id="avis-commentaire-erreur" class="diag-erreur"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $erreurCommentaire }}</p>
                            @endif
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="send" aria-hidden="true"></i> {{ $monAvis ? 'Mettre à jour' : 'Publier mon avis' }}
                        </button>
                    </form>
                </details>
            @elseif (! $monAvis)
                <p class="avis-invite">
                    <i data-lucide="calendar-check" aria-hidden="true"></i>
                    <span>Vous pourrez donner votre avis après une réparation terminée chez cet atelier.
                        <a href="{{ $rdvUrl }}">Prendre rendez-vous</a></span>
                </p>
            @endif

            @if ($erreurNote && ! $peutDonnerAvis)
                <p class="diag-erreur"><i data-lucide="alert-circle" aria-hidden="true"></i> {{ $erreurNote }}</p>
            @endif
        @endguest
    </div>

    @if ($avisRecents->isNotEmpty())
        <ol class="avis-liste">
            @foreach ($avisRecents as $avis)
                <li class="avis-carte">
                    <div class="avis-carte__tete">
                        <span class="avis-carte__avatar" aria-hidden="true">{{ $avis->initiale() }}</span>
                        <div class="avis-carte__auteur">
                            <strong>{{ $avis->auteurAffiche() }}</strong>
                            <span class="avis-carte__badge"><i data-lucide="badge-check" aria-hidden="true"></i> Réparation vérifiée</span>
                        </div>
                        <span class="avis-carte__date">
                            {{ $avis->dateRelative() }}@if ($avis->estModifie()) · modifié @endif
                        </span>
                    </div>
                    <div class="avis-carte__note">
                        @include('front.partials.etoiles', ['note' => $avis->note])
                        <span class="avis-carte__libelle">{{ $avis->libelle() }}</span>
                    </div>
                    @if (filled($avis->commentaire))
                        <p class="avis-carte__texte">{{ $avis->commentaire }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
        @if ($total > $avisRecents->count())
            <p class="muted avis-liste__suite">Les {{ $avisRecents->count() }} avis les plus récents sur {{ $total }}.</p>
        @endif
    @endif
</section>
