@extends('layouts.back')

@section('page-title', $pageTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/module5-admin.css') }}">
@endpush

@php
    $f = fn ($n, $d = 0) => number_format((float) $n, $d, ',', ' ');
@endphp

@section('content')
<div class="module">
    @include('back.partials.flash')

    <div class="module-toolbar rapport-outils">
        <a href="{{ route('back.statistiques.index') }}" class="link-button"><i data-lucide="arrow-left" aria-hidden="true"></i> Statistiques</a>
        <div class="row-actions">
            <form method="get" action="{{ route('back.statistiques.rapport') }}" class="inline-form">
                <label class="sr-only" for="rapport-mois">Mois du rapport</label>
                <select id="rapport-mois" name="mois" onchange="this.form.submit()">
                    @foreach($disponibles as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected($valeur === $mois)>{{ $libelle }}{{ $historique->contains('mois', $valeur) ? ' ✓' : '' }}</option>
                    @endforeach
                </select>
            </form>
            <form method="post" action="{{ route('back.statistiques.rapport.generer') }}" data-rapport-generer>
                @csrf
                <input type="hidden" name="mois" value="{{ $mois }}">
                <button type="submit" class="btn btn-primary small">
                    <i data-lucide="sparkles" aria-hidden="true"></i> {{ $rapport ? 'Régénérer' : 'Générer le rapport' }}
                </button>
            </form>
            @if($rapport)
                <a href="{{ route('back.statistiques.rapport.telecharger', ['mois' => $mois]) }}" class="btn btn-secondary small"><i data-lucide="download" aria-hidden="true"></i> Markdown</a>
                <button type="button" class="btn btn-secondary small" onclick="window.print()"><i data-lucide="printer" aria-hidden="true"></i> Imprimer / PDF</button>
            @endif
        </div>
    </div>

    @if($rapport)
        @php
            $c = $rapport->contenu;
            $faits = $rapport->faits;
            $var = $faits['variation_vs_mois_precedent_pourcent'] ?? [];
        @endphp

        <article class="rapport panel">
            <header class="rapport__tete">
                <p class="kicker">Rapport d'impact · {{ ucfirst($faits['mois_libelle']) }}</p>
                <h2>{{ $c['titre'] }}</h2>
                <p class="rapport__source">
                    @if($rapport->estRedigeParIa())
                        <span class="ia-gravite ia-gravite--blue"><i data-lucide="sparkles" aria-hidden="true"></i> Rédigé par l'IA · chiffres vérifiés</span>
                    @else
                        <span class="ia-gravite ia-gravite--green"><i data-lucide="file-text" aria-hidden="true"></i> Rédaction automatique locale</span>
                    @endif
                    @if($faits['donnees_demo'] ?? false)<span class="ia-gravite ia-gravite--orange">Données de démonstration</span>@endif
                    @if($faits['mois_en_cours'] ?? false)<span class="ia-gravite ia-gravite--orange">Mois en cours</span>@endif
                    <span class="cell-sub">Généré le {{ $rapport->updated_at->format('d/m/Y à H:i') }}{{ $rapport->auteur ? ' par '.$rapport->auteur->name : '' }}</span>
                </p>
            </header>

            {{-- Chiffres clés : issus directement des données, jamais de l'IA. --}}
            <div class="rapport__chiffres">
                @foreach([
                    ['shirt', 'Vêtements sauvés', $f($faits['impact']['vetements_sauves']), $var['vetements_sauves'] ?? null],
                    ['wind', 'CO₂ évité', $f($faits['impact']['co2_evite_kg'], 1).' kg', $var['co2_evite'] ?? null],
                    ['droplets', 'Eau économisée', $f($faits['impact']['eau_economisee_litres']).' L', null],
                    ['wrench', 'Réparations', $f($faits['activite']['reparations']), $var['reparations'] ?? null],
                    ['gift', 'Dons', $f($faits['activite']['dons']), $var['dons'] ?? null],
                ] as [$icone, $libelle, $valeur, $evolution])
                    <div class="rapport__chiffre">
                        <i data-lucide="{{ $icone }}" aria-hidden="true"></i>
                        <strong>{{ $valeur }}</strong>
                        <span>{{ $libelle }}</span>
                        @if($evolution !== null)
                            <small @class(['est-hausse' => $evolution > 0, 'est-baisse' => $evolution < 0])>{{ $evolution > 0 ? '+' : '' }}{{ $evolution }} % vs mois précédent</small>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="rapport__resume">{{ $c['resume'] }}</p>

            <div class="rapport__grille">
                <section>
                    <h3><i data-lucide="star" aria-hidden="true"></i> Faits marquants</h3>
                    <ul>
                        @foreach($c['faits_marquants'] as $fait)<li>{{ $fait }}</li>@endforeach
                    </ul>
                </section>
                <section>
                    <h3><i data-lucide="leaf" aria-hidden="true"></i> Impact écologique</h3>
                    <p>{{ $c['impact'] }}</p>
                </section>
                <section>
                    <h3><i data-lucide="shield-check" aria-hidden="true"></i> Modération et sécurité</h3>
                    <p>{{ $c['moderation'] }}</p>
                </section>
                <section>
                    <h3><i data-lucide="lightbulb" aria-hidden="true"></i> Recommandations</h3>
                    <ol>
                        @foreach($c['recommandations'] as $reco)<li>{{ $reco }}</li>@endforeach
                    </ol>
                </section>
            </div>

            <details class="rapport__methode">
                <summary>Comment ce rapport est-il produit ?</summary>
                <p>
                    Les chiffres proviennent des statistiques consolidées de la plateforme (vêtements, réparations, dons,
                    impact CO₂ et eau, modération) et de l'analyse prédictive. Gemini rédige le texte à partir de ces seuls chiffres ;
                    chaque nombre cité est ensuite comparé automatiquement aux données. Si l'IA cite un chiffre absent des données,
                    sa rédaction est écartée au profit d'une rédaction locale.
                    Équivalences : voiture ≈ 0,12 kg de CO₂ par km, douche ≈ 60 litres (ordres de grandeur).
                </p>
                @if($rapport->chiffres_rejetes)
                    <p class="cell-sub">Dernière rédaction IA écartée : chiffres non vérifiables ({{ implode(', ', $rapport->chiffres_rejetes) }}).</p>
                @endif
            </details>
        </article>
    @else
        <section class="panel rapport-vide">
            <i data-lucide="file-chart-column" aria-hidden="true"></i>
            <h2>Aucun rapport pour {{ $disponibles[$mois] ?? $mois }}</h2>
            <p>L'IA rédige un bilan à partir des chiffres de la plateforme : vêtements sauvés, CO₂ et eau économisés, réparations, dons, modération et tendances.</p>
            <form method="post" action="{{ route('back.statistiques.rapport.generer') }}" data-rapport-generer>
                @csrf
                <input type="hidden" name="mois" value="{{ $mois }}">
                <button type="submit" class="btn btn-primary"><i data-lucide="sparkles" aria-hidden="true"></i> Générer le rapport</button>
            </form>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // La rédaction prend quelques secondes : on l'indique et on évite le double envoi.
    document.querySelectorAll('[data-rapport-generer]').forEach((form) => {
        form.addEventListener('submit', () => {
            const bouton = form.querySelector('button');
            bouton.disabled = true;
            bouton.textContent = 'Rédaction en cours…';
        });
    });
</script>
@endpush
