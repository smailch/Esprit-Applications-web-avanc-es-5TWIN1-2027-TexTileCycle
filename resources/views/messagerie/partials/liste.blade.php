{{--
    Liste des conversations (colonne de gauche), partagée admin / utilisateurs.
    Variables : $conversations (paginator), $filtres, $routePrefixe ('back.messagerie' | 'front.messagerie'),
                $estAdmin (bool), $actuelle (?Conversation), $roles (admin uniquement)
--}}
@php
    $lecteur = auth()->user();
    $filtreActif = $filtres['filtre'] ?? 'toutes';
@endphp

<aside class="msg-liste" aria-label="Conversations">
    <div class="msg-liste__tete">
        <form method="get" action="{{ route($routePrefixe.'.index') }}" class="msg-recherche" role="search">
            <i data-lucide="search" aria-hidden="true"></i>
            <label class="sr-only" for="msg-q">Rechercher une conversation</label>
            <input id="msg-q" type="search" name="q" value="{{ $filtres['q'] ?? '' }}" placeholder="{{ $estAdmin ? 'Nom, e-mail, sujet…' : 'Rechercher…' }}">
            @if($filtreActif !== 'toutes')<input type="hidden" name="filtre" value="{{ $filtreActif }}">@endif
            @if($estAdmin && ! empty($filtres['role']))<input type="hidden" name="role" value="{{ $filtres['role'] }}">@endif
        </form>
        <a href="{{ route($routePrefixe.'.create') }}" class="btn btn-primary small" aria-label="Nouvelle conversation"><i data-lucide="square-pen" aria-hidden="true"></i> Nouveau</a>
    </div>

    <nav class="msg-filtres" aria-label="Filtrer les conversations">
        @foreach(\App\Modules\Messagerie\Services\MessagerieService::FILTRES as $cle => $libelle)
            <a href="{{ route($routePrefixe.'.index', array_filter(['filtre' => $cle === 'toutes' ? null : $cle, 'role' => $filtres['role'] ?? null, 'q' => $filtres['q'] ?? null])) }}"
               @class(['chip', 'active' => $filtreActif === $cle])>{{ $libelle }}</a>
        @endforeach
        @if($estAdmin)
            <form method="get" action="{{ route($routePrefixe.'.index') }}" class="msg-role">
                @if($filtreActif !== 'toutes')<input type="hidden" name="filtre" value="{{ $filtreActif }}">@endif
                <label class="sr-only" for="msg-role">Type d'interlocuteur</label>
                <select id="msg-role" name="role" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach($roles as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(($filtres['role'] ?? null) === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </nav>

    <ul class="msg-liste__items">
        @forelse($conversations as $conv)
            @php
                $nonLus = $conv->nonLusPour($lecteur);
                $titre = $estAdmin ? ($conv->interlocuteur?->name ?? 'Compte supprimé') : $conv->sujet;
                $estActuelle = isset($actuelle) && (string) $actuelle->getKey() === (string) $conv->getKey();
            @endphp
            <li>
                <a href="{{ route($routePrefixe.'.show', $conv->getKey()) }}" @class(['msg-item', 'est-actuelle' => $estActuelle, 'est-non-lue' => $nonLus > 0]) @if($estActuelle) aria-current="page" @endif>
                    <span class="msg-avatar" aria-hidden="true">{{ $estAdmin ? ($conv->interlocuteur?->initials() ?? '?') : 'TC' }}</span>
                    <span class="msg-item__corps">
                        <span class="msg-item__ligne">
                            <b>{{ $titre }}</b>
                            <time datetime="{{ $conv->dernier_message_at?->toIso8601String() }}">{{ $conv->dernier_message_at?->isToday() ? $conv->dernier_message_at->format('H:i') : $conv->dernier_message_at?->format('d/m') }}</time>
                        </span>
                        @if($estAdmin)
                            <span class="msg-item__sujet">{{ $conv->interlocuteur?->roleLabel() }} · {{ $conv->sujet }}</span>
                        @endif
                        <span class="msg-item__ligne">
                            <span class="msg-item__apercu">{{ $conv->dernier_message_cote === \App\Modules\Messagerie\Models\Conversation::coteDe($lecteur) ? 'Vous : ' : '' }}{{ $conv->apercu }}</span>
                            @if($nonLus > 0)
                                <span class="nav-badge" aria-label="{{ $nonLus }} message(s) non lu(s)">{{ $nonLus }}</span>
                            @elseif(! $conv->estOuverte())
                                <i data-lucide="lock" class="msg-item__verrou" aria-label="Conversation fermée"></i>
                            @endif
                        </span>
                    </span>
                </a>
            </li>
        @empty
            <li class="msg-vide">
                <i data-lucide="inbox" aria-hidden="true"></i>
                <p>Aucune conversation{{ ($filtres['q'] ?? null) || $filtreActif !== 'toutes' ? ' pour ces critères' : '' }}.</p>
            </li>
        @endforelse
    </ul>

    @if($conversations->hasPages())
        <div class="msg-pagination">{{ $conversations->links() }}</div>
    @endif
</aside>
