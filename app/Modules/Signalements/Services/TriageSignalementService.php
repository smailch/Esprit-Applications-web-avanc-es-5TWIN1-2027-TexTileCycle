<?php

namespace App\Modules\Signalements\Services;

use App\Modules\Core\Support\GeminiJson;
use App\Modules\Signalements\Models\Signalement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Triage IA des signalements : catégorie, gravité (1 à 5), priorité, doublons,
 * décision suggérée et note de modération pré-rédigée.
 *
 *  - Doublons et récurrence : toujours calculés localement (même cible + similarité du texte).
 *  - Classement et rédaction : Gemini si disponible, sinon règles par mots-clés.
 * La décision reste à l'administrateur : l'IA ne fait que proposer.
 */
class TriageSignalementService
{
    public const CATEGORIES = [
        'comportement' => 'Comportement / propos',
        'arnaque' => 'Arnaque / fraude',
        'contenu_inapproprie' => 'Contenu inapproprié',
        'annonce_trompeuse' => 'Annonce trompeuse',
        'qualite_service' => 'Qualité du service',
        'prix' => 'Prix / tarifs',
        'informations' => 'Informations erronées (horaires, adresse…)',
        'spam' => 'Spam / doublon',
        'autre' => 'Autre',
    ];

    public const DECISIONS = [
        'traiter' => 'Traiter',
        'sanctionner' => 'Traiter et sanctionner',
        'rejeter' => 'Rejeter',
    ];

    public const PRIORITES = [5 => 'Urgente', 4 => 'Haute', 3 => 'Normale', 2 => 'Basse', 1 => 'Basse'];

    /** mots-clés (sans accents) => [catégorie, gravité de base] — du plus grave au moins grave. */
    private const REGLES = [
        '/menac|frapp|tuer|violen|agress|harcel/' => ['comportement', 5],
        '/arnaq|escroq|vol(e|é)?\b|fraud|faux compte|western|paiement hors/' => ['arnaque', 5],
        '/insult|injur|irrespect|impoli|grossier|raciste|propos/' => ['comportement', 4],
        '/inappropri|choquant|obscen|nudit|haineu/' => ['contenu_inapproprie', 4],
        '/trompeu|mensong|ne correspond|pas conforme|fausse annonce|photo/' => ['annonce_trompeuse', 3],
        '/qualite|mal (fait|repare|cousu)|abime|dechir|rate|en retard|pas honore|non honore/' => ['qualite_service', 3],
        '/prix|tarif|cher|factur|surfactur/' => ['prix', 2],
        '/horaire|ferme|adresse|telephone|numero|introuvable/' => ['informations', 2],
        '/doublon|plusieurs fois|spam|publicite/' => ['spam', 1],
    ];

    private const SEUIL_SIMILARITE = 0.3;

    public function __construct(
        private GeminiJson $ia
    ) {
    }

    /**
     * Analyse le signalement et enregistre le résultat dans son champ `triage`.
     */
    public function trier(Signalement $signalement): array
    {
        $autres = $this->autresSurLaCible($signalement);
        $doublons = $this->doublons($signalement, $autres);
        $local = $this->triageLocal($signalement, $autres);
        $ia = $this->triageIa($signalement, $autres);

        $triage = array_merge($local, $ia ?? [], [
            'doublons' => $doublons,
            'nb_autres_signalements' => $autres->count(),
            'source' => $ia ? 'gemini' : 'local',
            'analyse_le' => now()->toIso8601String(),
        ]);

        // Analyse locale : plusieurs citoyens différents signalent la même cible → gravité remontée
        // (Gemini reçoit déjà cet historique dans sa consigne).
        $auteursDistincts = $autres->pluck('user_id')->filter()->unique()->count();
        if (! $ia && $auteursDistincts >= 2) {
            $triage['gravite'] = min(5, $triage['gravite'] + ($auteursDistincts >= 4 ? 2 : 1));
        }

        $triage['priorite'] = self::PRIORITES[$triage['gravite']];

        $signalement->forceFill(['triage' => $triage])->save();

        return $triage;
    }

    /**
     * Trie les signalements en attente qui n'ont pas encore d'analyse (ou tous si $tous).
     */
    public function trierEnAttente(int $limite = 10, bool $tous = false): int
    {
        $signalements = Signalement::where('statut', Signalement::STATUT_EN_ATTENTE)
            ->when(! $tous, fn ($q) => $q->whereNull('triage'))
            ->orderBy('created_at', 'desc')
            ->take($limite)
            ->get();

        $signalements->each(fn (Signalement $s) => $this->trier($s));

        return $signalements->count();
    }

    public static function libelleCategorie(?string $categorie): string
    {
        return self::CATEGORIES[$categorie] ?? self::CATEGORIES['autre'];
    }

    public static function toneGravite(int $gravite): string
    {
        return match (true) {
            $gravite >= 5 => 'danger',
            $gravite === 4 => 'orange',
            $gravite === 3 => 'blue',
            default => 'green',
        };
    }

    /**
     * @return Collection<int, Signalement>
     */
    private function autresSurLaCible(Signalement $signalement): Collection
    {
        return Signalement::where('cible_type', $signalement->cible_type)
            ->where('cible_id', $signalement->cible_id)
            ->where('_id', '!=', $signalement->getKey())
            ->where('created_at', '>=', now()->subDays(90))
            ->get();
    }

    /**
     * Signalements de la même cible dont le motif est proche (similarité de Jaccard sur les mots).
     *
     * @return list<array{id: string, similarite: int, statut: string}>
     */
    private function doublons(Signalement $signalement, Collection $autres): array
    {
        $mots = $this->mots($signalement->motif);

        return $autres
            ->map(function (Signalement $autre) use ($mots) {
                $autresMots = $this->mots($autre->motif);
                $union = count(array_unique(array_merge($mots, $autresMots)));
                $similarite = $union ? count(array_intersect($mots, $autresMots)) / $union : 0;

                return ['id' => (string) $autre->getKey(), 'similarite' => (int) round($similarite * 100), 'statut' => $autre->statut];
            })
            ->filter(fn ($d) => $d['similarite'] >= self::SEUIL_SIMILARITE * 100)
            ->sortByDesc('similarite')
            ->values()
            ->take(5)
            ->all();
    }

    private function triageLocal(Signalement $signalement, Collection $autres): array
    {
        $texte = Str::lower(Str::ascii($signalement->motif.' '.implode(' ', (array) ($signalement->meta['moderation']['categories'] ?? []))));
        [$categorie, $gravite] = ['autre', 2];

        foreach (self::REGLES as $motif => [$cat, $grav]) {
            if (preg_match($motif, $texte)) {
                [$categorie, $gravite] = [$cat, $grav];
                break;
            }
        }

        // Signalement créé par la modération IA : le message a déjà été jugé bloquant.
        if ($signalement->estAutomatique()) {
            $gravite = max($gravite, 4);
            $categorie = $categorie === 'autre' ? 'comportement' : $categorie;
        }

        $sanctionnable = in_array($signalement->cible_type, ['Atelier', 'Association', 'User', 'Message'], true);
        $decision = match (true) {
            $gravite >= 4 && $sanctionnable => 'sanctionner',
            $gravite <= 1 || mb_strlen(trim($signalement->motif)) < 15 => 'rejeter',
            default => 'traiter',
        };

        return [
            'categorie' => $categorie,
            'gravite' => $gravite,
            'decision' => $decision,
            'resume' => Str::limit(preg_replace('/\s+/', ' ', $signalement->motif), 110),
            'note_suggeree' => $this->noteModele($categorie, $decision, $signalement),
            'justification' => 'Analyse par mots-clés'.($autres->count() ? ' et récurrence sur la cible ('.$autres->count().' autre(s) signalement(s))' : '').'.',
        ];
    }

    private function triageIa(Signalement $signalement, Collection $autres): ?array
    {
        $categories = implode(', ', array_keys(self::CATEGORIES));
        $historique = sprintf(
            '%d autre(s) signalement(s) sur cette cible en 90 jours (%d traité(s), %d rejeté(s), %d en attente).',
            $autres->count(),
            $autres->where('statut', Signalement::STATUT_TRAITE)->count(),
            $autres->where('statut', Signalement::STATUT_REJETE)->count(),
            $autres->where('statut', Signalement::STATUT_EN_ATTENTE)->count()
        );
        $motif = Str::limit($signalement->motif, 1500);
        $cible = $signalement->cibleNom().' « '.Str::limit($signalement->cibleLabel(), 120).' »';
        $origine = $signalement->estAutomatique() ? 'créé automatiquement par la modération IA (message bloqué)' : 'émis par un utilisateur';

        $reponse = $this->ia->demander(<<<PROMPT
Tu assistes l'administrateur de TexTileCycle, plateforme tunisienne d'économie circulaire textile
(citoyens, ateliers de réparation, associations caritatives). Trie ce signalement.

Élément signalé : {$cible}
Type : {$signalement->typeLabel()} — signalement {$origine}
Historique : {$historique}
Motif :
"""
{$motif}
"""

Réponds UNIQUEMENT avec un JSON :
{
  "categorie": un de [{$categories}],
  "gravite": entier de 1 (mineur) à 5 (danger, fraude, menace),
  "decision": "traiter" | "sanctionner" | "rejeter",
  "resume": "résumé neutre en une phrase (max 110 caractères)",
  "note_suggeree": "note de modération en français, factuelle et polie, adressée à l'auteur du signalement (max 300 caractères)",
  "justification": "pourquoi cette gravité et cette décision (max 160 caractères)"
}
"sanctionner" seulement si les faits sont graves et que l'élément est un atelier, une association, un utilisateur ou un message.
"rejeter" si le motif est vague, hors sujet ou sans manquement.
PROMPT);

        if (! $reponse || ! isset(self::CATEGORIES[$reponse['categorie'] ?? '']) || ! isset(self::DECISIONS[$reponse['decision'] ?? ''])) {
            return null;
        }

        return [
            'categorie' => $reponse['categorie'],
            'gravite' => max(1, min(5, (int) ($reponse['gravite'] ?? 2))),
            'decision' => $reponse['decision'],
            'resume' => Str::limit(trim((string) ($reponse['resume'] ?? '')), 140),
            'note_suggeree' => Str::limit(trim((string) ($reponse['note_suggeree'] ?? '')), 400),
            'justification' => Str::limit(trim((string) ($reponse['justification'] ?? '')), 220),
        ];
    }

    private function noteModele(string $categorie, string $decision, Signalement $signalement): string
    {
        if ($decision === 'rejeter') {
            return 'Merci pour votre signalement. Après vérification, nous n\'avons pas constaté de manquement aux règles de TexTileCycle.';
        }

        $action = match ($categorie) {
            'informations' => 'Nous avons demandé au partenaire de mettre à jour ses informations.',
            'prix' => 'Nous avons rappelé au partenaire que les tarifs affichés doivent être respectés.',
            'qualite_service' => 'Nous avons contacté le partenaire pour comprendre la situation et éviter que cela se reproduise.',
            'annonce_trompeuse' => 'L\'annonce a été vérifiée et sera corrigée ou retirée.',
            default => 'Les mesures nécessaires ont été prises.',
        };

        return 'Merci pour votre signalement concernant « '.Str::limit($signalement->cibleLabel(), 60).' ». '.$action
            .($decision === 'sanctionner' ? ' Le compte concerné a été suspendu le temps de l\'enquête.' : '');
    }

    /** @return list<string> mots significatifs (4 lettres et plus, sans accents) */
    private function mots(?string $texte): array
    {
        preg_match_all('/[a-z0-9]{4,}/', Str::lower(Str::ascii((string) $texte)), $m);

        return array_values(array_unique($m[0]));
    }
}
