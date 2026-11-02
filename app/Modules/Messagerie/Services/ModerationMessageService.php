<?php

namespace App\Modules\Messagerie\Services;

use App\Modules\Core\Support\GeminiJson;
use Illuminate\Support\Str;

/**
 * Modération IA des messages, analysés AVANT l'envoi.
 *
 *  1. Règles locales (instantanées, fonctionnent hors ligne) : coordonnées, liens, arnaque,
 *     insultes (français et darija en lettres latines), menaces.
 *  2. Gemini (si une clé est configurée) : harcèlement, sous-entendus, ton agressif…
 *
 * Le verdict le plus sévère l'emporte :
 *  - ok      : le message part ;
 *  - avertir : l'auteur doit confirmer (« Envoyer quand même ») ; le message est marqué pour l'admin ;
 *  - bloquer : le message n'est pas remis au destinataire et un signalement est créé automatiquement.
 */
class ModerationMessageService
{
    public const OK = 'ok';

    public const AVERTIR = 'avertir';

    public const BLOQUER = 'bloquer';

    private const SEVERITE = [self::OK => 0, self::AVERTIR => 1, self::BLOQUER => 2];

    public const CATEGORIES = [
        'insulte' => 'Insulte',
        'harcelement' => 'Harcèlement',
        'menace' => 'Menace',
        'arnaque' => 'Arnaque / paiement hors plateforme',
        'coordonnees' => 'Coordonnées personnelles',
        'lien' => 'Lien externe',
        'spam' => 'Spam',
    ];

    /** Insultes graves (bloquées) — français et darija tunisienne en lettres latines. */
    private const INSULTES_GRAVES = [
        'pute', 'putain', 'salope', 'encule', 'enculer', 'fdp', 'fils de pute', 'nique', 'niquer', 'ntm', 'batard',
        'zebi', 'zeby', 'kahba', '9ahba', 'nayek', 'mnayek', 'nik', 'zok', 'tahan',
    ];

    /** Langage limite (avertissement). */
    private const INSULTES_LEGERES = [
        'con', 'conne', 'connard', 'connasse', 'abruti', 'debile', 'idiot', 'imbecile', 'merde', 'ta gueule', 'tg',
        'bhim', 'hmar', '7mar', 'msatek', 'mahbool', 'mahboul',
    ];

    private const MENACES = [
        '/\bje (vais|vas) (te|vous) (tuer|frapper|buter|retrouver|faire payer)\b/u',
        '/\btu (vas|va) (le )?regretter\b/u',
        '/\bje sais o[uù] (tu habites|vous habitez)\b/u',
        '/\bnadhbhek\b|\bn9atlek\b|\bnoktlek\b/u',
    ];

    private const ARNAQUE = [
        '/\bwestern ?union\b/u', '/\bmoney ?gram\b/u', '/\bd17\b/u', '/\bflouci\b/u', '/\bpaypal\b/u',
        '/\b(virement|mandat|rib)\b/u', '/\bcarte (bancaire|bleue)\b/u', '/\bcode (de )?confirmation\b/u',
        '/\bmot de passe\b/u', '/\b(pay|regl)\w* (hors|en dehors) (de )?(la )?plateforme\b/u', '/\bacompte\b/u',
    ];

    public function __construct(
        private GeminiJson $ia
    ) {
    }

    /**
     * @return array{verdict: string, categories: list<string>, raison: string, source: string}
     */
    public function analyser(string $contenu): array
    {
        $local = $this->reglesLocales($contenu);

        // Inutile d'interroger l'IA si les règles bloquent déjà, ou pour un message très court.
        $ia = ($local['verdict'] !== self::BLOQUER && mb_strlen(trim($contenu)) >= 6)
            ? $this->analyseIa($contenu)
            : null;

        if (! $ia) {
            return $local;
        }

        $retenu = self::SEVERITE[$ia['verdict']] > self::SEVERITE[$local['verdict']] ? $ia : $local;

        return [
            'verdict' => $retenu['verdict'],
            'categories' => array_values(array_unique(array_merge($local['categories'], $ia['categories']))),
            'raison' => $retenu['raison'],
            'source' => $local['categories'] && $ia['categories'] ? 'mixte' : ($ia['categories'] ? 'ia' : 'local'),
        ];
    }

    public static function libelleCategories(array $categories): string
    {
        return implode(', ', array_map(fn ($c) => self::CATEGORIES[$c] ?? $c, $categories));
    }

    /**
     * @return array{verdict: string, categories: list<string>, raison: string, source: string}
     */
    public function reglesLocales(string $contenu): array
    {
        $texte = $this->normaliser($contenu);
        $trouve = [];

        foreach (self::MENACES as $motif) {
            if (preg_match($motif, $texte)) {
                $trouve['menace'] = [self::BLOQUER, 'Le message contient une menace.'];
                break;
            }
        }

        if ($this->contientMot($texte, self::INSULTES_GRAVES)) {
            $trouve['insulte'] = [self::BLOQUER, 'Le message contient des propos injurieux.'];
        } elseif ($this->contientMot($texte, self::INSULTES_LEGERES)) {
            $trouve['insulte'] = [self::AVERTIR, 'Le message contient des termes qui peuvent être perçus comme insultants.'];
        }

        foreach (self::ARNAQUE as $motif) {
            if (preg_match($motif, $texte)) {
                $trouve['arnaque'] = [self::AVERTIR, 'Le message évoque un paiement ou des informations sensibles en dehors de la plateforme : attention aux arnaques.'];
                break;
            }
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]{2,}/u', $contenu)
            || preg_match('/(?<!\d)(?:\+?216[\s.-]?)?[2-9]\d(?:[\s.-]?\d){6}(?!\d)/u', $contenu)) {
            $trouve['coordonnees'] = [self::AVERTIR, 'Le message contient un numéro de téléphone ou une adresse e-mail. Pour votre sécurité, échangez de préférence sur TexTileCycle.'];
        }

        if (preg_match('~https?://|www\.~i', $contenu)) {
            $trouve['lien'] = [self::AVERTIR, 'Le message contient un lien externe. Vérifiez qu\'il est sûr avant de le partager.'];
        }

        if (preg_match('/(.)\1{9,}/u', $contenu) || $this->tropDeMajuscules($contenu)) {
            $trouve['spam'] = [self::AVERTIR, 'Le message ressemble à du spam (majuscules ou caractères répétés).'];
        }

        if (! $trouve) {
            return ['verdict' => self::OK, 'categories' => [], 'raison' => '', 'source' => 'local'];
        }

        uasort($trouve, fn ($a, $b) => self::SEVERITE[$b[0]] <=> self::SEVERITE[$a[0]]);
        $premier = reset($trouve);

        return [
            'verdict' => $premier[0],
            'categories' => array_keys($trouve),
            'raison' => $premier[1],
            'source' => 'local',
        ];
    }

    /**
     * @return array{verdict: string, categories: list<string>, raison: string}|null
     */
    private function analyseIa(string $contenu): ?array
    {
        $categories = implode(', ', array_keys(self::CATEGORIES));
        $message = Str::limit($contenu, 1500);

        $reponse = $this->ia->demander(<<<PROMPT
Tu es le modérateur de TexTileCycle, une plateforme tunisienne d'économie circulaire textile
(citoyens, ateliers de couture, associations). Analyse le message ci-dessous, écrit en français,
en arabe ou en darija tunisienne (lettres arabes ou latines, chiffres 3/7/9 compris).

Réponds UNIQUEMENT avec un JSON :
{"verdict": "ok" | "avertir" | "bloquer", "categories": [...], "raison": "..."}

- "bloquer" : insultes graves, menaces, harcèlement, propos haineux ou tentative d'arnaque évidente.
- "avertir" : langage limite, agressivité, demande de paiement ou de coordonnées hors plateforme.
- "ok" : message normal, même s'il exprime un mécontentement poli.
- "categories" : sous-ensemble de [{$categories}] (liste vide si ok).
- "raison" : une phrase courte en français, adressée à l'auteur, sans répéter les insultes.

Message :
"""
{$message}
"""
PROMPT);

        if (! $reponse || ! isset($reponse['verdict']) || ! isset(self::SEVERITE[$reponse['verdict']])) {
            return null;
        }

        return [
            'verdict' => $reponse['verdict'],
            'categories' => array_values(array_intersect((array) ($reponse['categories'] ?? []), array_keys(self::CATEGORIES))),
            'raison' => Str::limit(trim((string) ($reponse['raison'] ?? '')), 240) ?: 'Ce message ne respecte pas les règles de la plateforme.',
        ];
    }

    /** Minuscules sans accents, lettres répétées réduites (« connnard » → « connard »). */
    private function normaliser(string $texte): string
    {
        $texte = Str::lower(Str::ascii($texte));

        return preg_replace('/([a-z])\1{2,}/', '$1$1', $texte);
    }

    private function contientMot(string $texte, array $mots): bool
    {
        foreach ($mots as $mot) {
            if (preg_match('/(?<![a-z0-9])'.preg_quote($mot, '/').'(?![a-z0-9])/', $texte)) {
                return true;
            }
        }

        return false;
    }

    private function tropDeMajuscules(string $contenu): bool
    {
        $lettres = preg_replace('/[^\p{L}]/u', '', $contenu);

        return mb_strlen($lettres) >= 30 && mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $lettres)) / mb_strlen($lettres) > 0.8;
    }
}
