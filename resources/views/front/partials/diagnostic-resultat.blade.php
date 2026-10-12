{{--
    Écran « résultat » du diagnostic par photo. Rendu dans la page (envoi classique, sans JavaScript)
    ou renvoyé en JSON au script js/diagnostic.js (envoi sans rechargement).
    La photo et les choix « Ce n'est pas ça ? » ne sont affichés que par le script : sans lui, la photo n'existe plus.
--}}
@php
    $corrections = \App\Modules\Ateliers\Services\DiagnosticService::CORRECTIONS;
    $defautChoisi = $resultat['defaut_choisi'] ?? null;
@endphp

<section class="diag-resultat" aria-labelledby="diag-resultat-titre" data-resultat>
    <aside class="diag-resultat__constat">
        <figure class="diag-photo" data-resultat-photo hidden>
            <img alt="Votre photo" data-resultat-image>
        </figure>

        <h2 id="diag-resultat-titre" tabindex="-1">Résultat du diagnostic</h2>

        <div class="diag-constat">
            <p class="diag-constat__titre">{{ $defautChoisi ? 'Défaut que vous avez indiqué' : "Ce que l'IA voit" }}</p>
            <ul class="diag-pastilles">
                @if ($defautChoisi)
                    <li class="diag-pastille est-principale">{{ $corrections[$defautChoisi] }}</li>
                @else
                    @foreach ($resultat['defauts'] as $index => $libelle)
                        <li @class(['diag-pastille', 'est-principale' => $index === 0])>{{ $libelle }}</li>
                    @endforeach
                @endif
                @if ($resultat['vetement'])
                    <li class="diag-pastille est-vetement"><i data-lucide="shirt" aria-hidden="true"></i> {{ $resultat['vetement'] }}</li>
                @endif
            </ul>

            @unless ($resultat['certain'])
                <p class="diag-incertain">
                    <i data-lucide="info" aria-hidden="true"></i>
                    <span>L'IA hésite : vérifiez la réparation proposée ou indiquez le défaut ci-dessous.</span>
                </p>
            @endunless
        </div>

        <div class="diag-affiner" data-affiner hidden>
            <p id="diag-affiner-titre" class="diag-affiner__titre">Ce n'est pas ça ? Indiquez le défaut :</p>
            <div class="diag-affiner__choix" role="group" aria-labelledby="diag-affiner-titre">
                @foreach ($corrections as $cle => $libelle)
                    <button type="button" class="diag-choix" data-defaut="{{ $cle }}" aria-pressed="{{ $defautChoisi === $cle ? 'true' : 'false' }}">{{ $libelle }}</button>
                @endforeach
            </div>
        </div>

        <a href="{{ route('front.ateliers.diagnostic') }}" class="btn btn-secondary diag-recommencer" data-recommencer>
            <i data-lucide="rotate-ccw" aria-hidden="true"></i> Essayer avec une autre photo
        </a>
    </aside>

    <div class="diag-resultat__reparations">
        @if ($resultat['propositions'] === [])
            <p class="diag-vide">Aucune réparation du catalogue ne correspond à cette photo. <a href="{{ route('front.ateliers') }}">Parcourir les ateliers</a></p>
        @else
            <h3 class="diag-sous-titre">Réparations proposées, de la plus probable à la moins probable</h3>
            <p class="diag-explication">Pour chaque réparation, les ateliers sont classés selon leur note, leur prix, leur ouverture et, si vous avez partagé votre position, leur distance.</p>
            <ol class="diag-reparations">
                @foreach ($resultat['propositions'] as $rang => $proposition)
                    <li @class(['diag-reparation', 'est-principale' => $rang === 0])>
                        <div class="diag-reparation__tete">
                            <span class="diag-reparation__rang" aria-hidden="true">{{ $rang + 1 }}</span>
                            <h4>{{ $proposition['nom'] }}</h4>
                            @if ($proposition['prix_min'] !== null)
                                <p class="diag-reparation__prix">dès <strong>{{ \App\Modules\Ateliers\Models\Atelier::formatPrix($proposition['prix_min']) }}</strong></p>
                            @endif
                        </div>

                        <ul class="diag-ateliers" aria-label="Ateliers qui proposent : {{ $proposition['nom'] }}">
                            @foreach ($proposition['offres'] as $offre)
                                @php
                                    $atelier = $offre['atelier'];
                                    $service = $offre['service'];
                                    $atelierId = (string) $atelier->getKey();
                                    $ouvert = $offre['ouvert'];
                                @endphp
                                <li class="diag-atelier">
                                    <div class="atelier-avatar atelier-avatar--{{ $atelier->avatarTone() }}" aria-hidden="true">{{ $atelier->initiales() }}</div>
                                    <div class="diag-atelier__infos">
                                        <p class="diag-atelier__nom">{{ $atelier->nom }}</p>
                                        @if ($offre['raisons'] !== [])
                                            <ul class="diag-raisons" aria-label="Pourquoi cet atelier">
                                                @foreach ($offre['raisons'] as $raison)
                                                    <li>{{ $raison }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        <p class="diag-atelier__meta">
                                            <span><i data-lucide="map-pin" aria-hidden="true"></i> {{ $atelier->ville }}@if ($offre['distance_km'] !== null), à {{ \App\Modules\Ateliers\Services\AtelierService::formatDistance($offre['distance_km']) }}@endif</span>
                                            @if ((int) $atelier->nb_avis > 0)
                                                <span><i data-lucide="star" aria-hidden="true"></i> {{ $atelier->noteFormatee() }}<span class="sr-only"> sur 5</span> <span class="diag-atelier__avis">({{ (int) $atelier->nb_avis }} avis)</span></span>
                                            @else
                                                <span class="diag-atelier__avis">Pas encore d'avis</span>
                                            @endif
                                            @if (is_numeric($service->prix_estime))
                                                <span>{{ \App\Modules\Ateliers\Models\Atelier::formatPrix((float) $service->prix_estime) }}</span>
                                            @endif
                                            <span @class(['open-badge', 'is-open' => $ouvert])><span class="open-badge__dot" aria-hidden="true"></span>{{ $ouvert ? 'Ouvert maintenant' : 'Fermé' }}</span>
                                        </p>
                                    </div>
                                    <div class="diag-atelier__actions">
                                        <a href="{{ route('front.ateliers.show', ['id' => $atelierId]) }}" class="btn btn-secondary small" aria-label="Voir la fiche de {{ $atelier->nom }}">Voir</a>
                                        <a href="{{ route('front.rdv.create', ['atelier_id' => $atelierId, 'service_id' => (string) $service->getKey()]) }}"
                                           class="btn btn-primary small" aria-label="Prendre RDV chez {{ $atelier->nom }} pour : {{ $proposition['nom'] }}">Prendre RDV</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        @if ($proposition['nb_ateliers'] > count($proposition['offres']))
                            <a href="{{ route('front.ateliers', ['service' => $proposition['nom']]) }}" class="diag-plus">
                                Voir les {{ $proposition['nb_ateliers'] }} ateliers qui proposent ce service
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
