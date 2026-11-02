<?php

namespace App\Modules\Messagerie\Services;

use App\Modules\Associations\Models\Association;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Models\Message;
use App\Modules\Signalements\Models\Signalement;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MessagerieService
{
    public const FILTRES = [
        'toutes' => 'Toutes',
        'non_lues' => 'Non lues',
        'ouverte' => 'Ouvertes',
        'fermee' => 'Fermées',
    ];

    /**
     * Boîte de réception : toutes les conversations pour un admin,
     * uniquement les siennes pour un citoyen ou un partenaire.
     */
    public function boite(User $user, array $filtres = [], int $parPage = 20): LengthAwarePaginator
    {
        $estAdmin = $user->isAdmin();

        return Conversation::query()
            ->with(['interlocuteur', 'partenaire', 'contexte'])
            ->when(! $estAdmin, fn ($q) => $q->where(fn ($s) => $this->participations($s, $user)))
            ->when(($filtres['filtre'] ?? null) === 'non_lues', fn ($q) => $this->nonLuesPour($q, $user))
            ->when($estAdmin && ($filtres['type'] ?? null), fn ($q) => $filtres['type'] === Conversation::TYPE_PARTENAIRE
                ? $q->where('type', Conversation::TYPE_PARTENAIRE)
                : $q->where('type', '!=', Conversation::TYPE_PARTENAIRE))
            ->when(in_array($filtres['filtre'] ?? null, [Conversation::STATUT_OUVERTE, Conversation::STATUT_FERMEE], true),
                fn ($q) => $q->where('statut', $filtres['filtre']))
            ->when($estAdmin && ($filtres['role'] ?? null), fn ($q) => $q->whereIn('user_id', $this->idsParRole($filtres['role'])))
            ->when($filtres['q'] ?? null, function ($q, $recherche) use ($estAdmin) {
                $regex = '/'.preg_quote($recherche, '/').'/i';
                $q->where(function ($sous) use ($regex, $recherche, $estAdmin) {
                    $sous->where('sujet', 'regex', $regex)->orWhere('apercu', 'regex', $regex);
                    if ($estAdmin) {
                        $sous->orWhereIn('user_id', $this->idsParNom($recherche));
                    }
                });
            })
            ->orderBy('dernier_message_at', 'desc')
            ->paginate($parPage)
            ->withQueryString();
    }

    public function nonLues(User $user): int
    {
        return $this->nonLuesPour(Conversation::query(), $user)->count();
    }

    /**
     * Conversations non lues par l'utilisateur. Un admin n'est concerné que par les échanges
     * support ; les autres par leurs échanges en tant que citoyen OU en tant que partenaire.
     */
    private function nonLuesPour($query, User $user)
    {
        if ($user->isAdmin()) {
            return $query->where('type', '!=', Conversation::TYPE_PARTENAIRE)->where('non_lus_admin', '>', 0);
        }

        $id = (string) $user->getKey();

        return $query->where(function ($q) use ($id) {
            $q->where(fn ($s) => $s->where('user_id', $id)->where('non_lus_utilisateur', '>', 0))
                ->orWhere(fn ($s) => $s->where('partenaire_id', $id)->where('non_lus_admin', '>', 0));
        });
    }

    /** Conversations où l'utilisateur est le citoyen OU le partenaire contacté. */
    private function participations($query, User $user)
    {
        $id = (string) $user->getKey();

        return $query->where('user_id', $id)->orWhere('partenaire_id', $id);
    }

    /**
     * @param  User|null  $partenaire  responsable de l'atelier / l'association contacté (échange citoyen ↔ partenaire)
     */
    public function creer(User $auteur, User $interlocuteur, array $data, ?User $partenaire = null, ?array $moderation = null): Conversation
    {
        $conversation = Conversation::create([
            'type' => $partenaire ? Conversation::TYPE_PARTENAIRE : Conversation::TYPE_SUPPORT,
            'sujet' => $data['sujet'],
            'user_id' => (string) $interlocuteur->getKey(),
            'admin_id' => $auteur->isAdmin() ? (string) $auteur->getKey() : null,
            'partenaire_id' => $partenaire ? (string) $partenaire->getKey() : null,
            'contexte_type' => $data['contexte_type'] ?? null,
            'contexte_id' => isset($data['contexte_id']) ? (string) $data['contexte_id'] : null,
            'statut' => Conversation::STATUT_OUVERTE,
            'initiee_par' => (string) $auteur->getKey() === (string) $interlocuteur->getKey()
                ? Conversation::COTE_UTILISATEUR
                : Conversation::COTE_ADMIN,
        ]);

        $this->envoyer($conversation, $auteur, $data['message'], $moderation);

        return $conversation->fresh();
    }

    /**
     * @param  array|null  $moderation  résultat de ModerationMessageService::analyser()
     */
    public function envoyer(Conversation $conversation, User $auteur, string $contenu, ?array $moderation = null): Message
    {
        $cote = $conversation->cotePour($auteur)
            ?? throw new \LogicException('Cet utilisateur ne participe pas à la conversation.');
        $contenu = trim($contenu);
        $bloque = ($moderation['verdict'] ?? null) === ModerationMessageService::BLOQUER;

        $message = Message::create(array_filter([
            'conversation_id' => (string) $conversation->getKey(),
            'user_id' => (string) $auteur->getKey(),
            'cote' => $cote,
            'contenu' => $contenu,
            'moderation' => ($moderation['verdict'] ?? ModerationMessageService::OK) !== ModerationMessageService::OK ? $moderation : null,
            'bloque' => $bloque ?: null,
        ], fn ($v) => $v !== null));

        // Message bloqué : conservé comme preuve pour l'administration, jamais remis au destinataire.
        if ($bloque) {
            $this->signalerMessageBloque($message, $conversation, $auteur, $moderation);

            return $message;
        }

        // L'expéditeur a forcément lu le fil ; le destinataire a un message de plus à lire.
        $conversation->fill([
            'dernier_message_at' => $message->created_at,
            'dernier_message_cote' => $cote,
            'apercu' => Str::limit(preg_replace('/\s+/', ' ', $contenu), 90),
            $cote === Conversation::COTE_ADMIN ? 'lu_admin_at' : 'lu_utilisateur_at' => $message->created_at,
            $cote === Conversation::COTE_ADMIN ? 'non_lus_admin' : 'non_lus_utilisateur' => 0,
        ]);

        if ($cote === Conversation::COTE_ADMIN && ! $conversation->estAvecPartenaire()) {
            $conversation->admin_id ??= (string) $auteur->getKey();
        }

        $conversation->save();
        $conversation->increment($cote === Conversation::COTE_ADMIN ? 'non_lus_utilisateur' : 'non_lus_admin');

        return $message;
    }

    public function marquerLue(Conversation $conversation, User $lecteur): void
    {
        // Un admin qui consulte un échange citoyen ↔ partenaire ne le marque pas comme lu.
        if (! $cote = $conversation->cotePour($lecteur)) {
            return;
        }
        $champ = $cote === Conversation::COTE_ADMIN ? 'non_lus_admin' : 'non_lus_utilisateur';

        $conversation->fill([
            $champ => 0,
            $cote === Conversation::COTE_ADMIN ? 'lu_admin_at' : 'lu_utilisateur_at' => now(),
        ])->save();
    }

    /**
     * Messages postérieurs à une date (polling) ; tout le fil si $apres est null.
     *
     * @return Collection<int, Message>
     */
    public function messages(Conversation $conversation, ?Carbon $apres = null, ?User $lecteur = null): Collection
    {
        return $conversation->messages()
            ->with('auteur')
            // Les messages bloqués par la modération ne sont visibles que par l'administration.
            ->when(! $lecteur?->isAdmin(), fn ($q) => $q->where('bloque', '!=', true))
            ->when($apres, fn ($q) => $q->where('created_at', '>', $apres))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Signalement automatique d'un message bloqué (un seul par auteur et par 24 h).
     */
    private function signalerMessageBloque(Message $message, Conversation $conversation, User $auteur, array $moderation): void
    {
        $dejaSignale = Signalement::where('source', Signalement::SOURCE_IA)
            ->where('statut', Signalement::STATUT_EN_ATTENTE)
            ->where('meta.auteur_message_id', (string) $auteur->getKey())
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($dejaSignale) {
            return;
        }

        $signalement = Signalement::create([
            'user_id' => null,
            'type' => Signalement::TYPE_CONTENU,
            'cible_type' => 'Message',
            'cible_id' => (string) $message->getKey(),
            'motif' => 'Message bloqué automatiquement par la modération IA ('
                .ModerationMessageService::libelleCategories($moderation['categories'] ?? []).'). '
                .($moderation['raison'] ?? ''),
            'statut' => Signalement::STATUT_EN_ATTENTE,
            'source' => Signalement::SOURCE_IA,
            'meta' => [
                'conversation_id' => (string) $conversation->getKey(),
                'auteur_message_id' => (string) $auteur->getKey(),
                'moderation' => $moderation,
            ],
        ]);

        app(\App\Modules\Signalements\Services\SignalementService::class)->programmerTriage($signalement);
    }

    public function supprimer(Conversation $conversation): void
    {
        $conversation->messages()->delete();
        $conversation->delete();
    }

    /**
     * Destinataires possibles pour l'admin : citoyens et responsables de partenaires actifs.
     *
     * @return Collection<string, Collection<int, User>> rôle => utilisateurs
     */
    public function destinataires(): Collection
    {
        return User::whereIn('role', [User::ROLE_CITOYEN, User::ROLE_ATELIER, User::ROLE_ASSOCIATION])
            ->where('is_active', '!=', false)
            ->orderBy('name')
            ->get(['name', 'email', 'role'])
            ->groupBy(fn (User $u) => $u->roleLabel());
    }

    /**
     * Destinataires possibles pour un citoyen (recherche à partir de 3 caractères) :
     * l'équipe TexTileCycle, les ateliers et les associations actifs.
     *
     * @return list<array{valeur: string, nom: string, detail: string, icone: string}>
     */
    public function rechercherDestinataires(User $auteur, string $recherche, int $limite = 6): array
    {
        $recherche = trim($recherche);

        if (mb_strlen($recherche) < 3) {
            return [];
        }

        $regex = '/'.preg_quote($recherche, '/').'/i';
        $moi = (string) $auteur->getKey();
        $resultats = [];

        if (preg_match($regex.'u', 'Équipe TexTileCycle administration admin support equipe')) {
            $resultats[] = ['valeur' => 'equipe', 'nom' => 'Équipe TexTileCycle', 'detail' => 'Administration de la plateforme', 'icone' => 'shield-check'];
        }

        $ateliers = Atelier::where('statut', Atelier::STATUT_ACTIF)
            ->where(fn ($q) => $q->where('nom', 'regex', $regex)->orWhere('ville', 'regex', $regex)->orWhere('specialite', 'regex', $regex))
            ->where('user_id', '!=', $moi)
            ->orderBy('nom')->take($limite)->get();

        foreach ($ateliers as $atelier) {
            $resultats[] = [
                'valeur' => 'atelier:'.$atelier->getKey(),
                'nom' => $atelier->nom,
                'detail' => implode(' · ', array_filter(['Atelier', $atelier->ville, $atelier->specialite])),
                'icone' => 'scissors',
            ];
        }

        $associations = Association::where('statut', Association::STATUT_ACTIF)
            ->where(fn ($q) => $q->where('nom', 'regex', $regex)->orWhere('adresse', 'regex', $regex))
            ->where('user_id', '!=', $moi)
            ->orderBy('nom')->take($limite)->get();

        foreach ($associations as $association) {
            $resultats[] = [
                'valeur' => 'association:'.$association->getKey(),
                'nom' => $association->nom,
                'detail' => implode(' · ', array_filter(['Association', $association->adresse])),
                'icone' => 'heart-handshake',
            ];
        }

        return $resultats;
    }

    /**
     * Résout la valeur choisie (« equipe », « atelier:<id> », « association:<id> »).
     * Renvoie null si le destinataire n'existe pas ou n'est pas joignable.
     *
     * @return array{partenaire: ?User, contexte: ?\Illuminate\Database\Eloquent\Model, nom: string}|null
     */
    public function resoudreDestinataire(?string $valeur, User $auteur): ?array
    {
        if ($valeur === 'equipe') {
            return ['partenaire' => null, 'contexte' => null, 'nom' => 'Équipe TexTileCycle'];
        }

        [$type, $id] = array_pad(explode(':', (string) $valeur, 2), 2, null);
        $modele = ['atelier' => Atelier::class, 'association' => Association::class][$type] ?? null;
        $partenaire = $modele && $id ? $modele::where('statut', $modele::STATUT_ACTIF)->find($id) : null;
        $responsable = $partenaire ? User::find($partenaire->user_id) : null;

        if (! $responsable || ! $responsable->isActive() || (string) $responsable->getKey() === (string) $auteur->getKey()) {
            return null;
        }

        return ['partenaire' => $responsable, 'contexte' => $partenaire, 'nom' => $partenaire->nom];
    }

    private function idsParRole(string $role): array
    {
        return User::where('role', $role)->get(['_id'])->map(fn ($u) => (string) $u->getKey())->all();
    }

    private function idsParNom(string $recherche): array
    {
        $regex = '/'.preg_quote($recherche, '/').'/i';

        return User::where('name', 'regex', $regex)->orWhere('email', 'regex', $regex)
            ->get(['_id'])->map(fn ($u) => (string) $u->getKey())->all();
    }
}
