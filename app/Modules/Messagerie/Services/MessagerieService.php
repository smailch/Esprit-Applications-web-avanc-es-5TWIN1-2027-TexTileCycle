<?php

namespace App\Modules\Messagerie\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Models\Message;
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
        $champNonLus = $estAdmin ? 'non_lus_admin' : 'non_lus_utilisateur';

        return Conversation::query()
            ->with(['interlocuteur'])
            ->when(! $estAdmin, fn ($q) => $q->where('user_id', (string) $user->getKey()))
            ->when(($filtres['filtre'] ?? null) === 'non_lues', fn ($q) => $q->where($champNonLus, '>', 0))
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
        return Conversation::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', (string) $user->getKey()))
            ->where($user->isAdmin() ? 'non_lus_admin' : 'non_lus_utilisateur', '>', 0)
            ->count();
    }

    public function creer(User $auteur, User $interlocuteur, array $data): Conversation
    {
        $conversation = Conversation::create([
            'sujet' => $data['sujet'],
            'user_id' => (string) $interlocuteur->getKey(),
            'admin_id' => $auteur->isAdmin() ? (string) $auteur->getKey() : null,
            'contexte_type' => $data['contexte_type'] ?? null,
            'contexte_id' => isset($data['contexte_id']) ? (string) $data['contexte_id'] : null,
            'statut' => Conversation::STATUT_OUVERTE,
            'initiee_par' => Conversation::coteDe($auteur),
        ]);

        $this->envoyer($conversation, $auteur, $data['message']);

        return $conversation->fresh();
    }

    public function envoyer(Conversation $conversation, User $auteur, string $contenu): Message
    {
        $cote = Conversation::coteDe($auteur);
        $contenu = trim($contenu);

        $message = Message::create([
            'conversation_id' => (string) $conversation->getKey(),
            'user_id' => (string) $auteur->getKey(),
            'cote' => $cote,
            'contenu' => $contenu,
        ]);

        // L'expéditeur a forcément lu le fil ; le destinataire a un message de plus à lire.
        $conversation->fill([
            'dernier_message_at' => $message->created_at,
            'dernier_message_cote' => $cote,
            'apercu' => Str::limit(preg_replace('/\s+/', ' ', $contenu), 90),
            $cote === Conversation::COTE_ADMIN ? 'lu_admin_at' : 'lu_utilisateur_at' => $message->created_at,
            $cote === Conversation::COTE_ADMIN ? 'non_lus_admin' : 'non_lus_utilisateur' => 0,
        ]);

        if ($cote === Conversation::COTE_ADMIN) {
            $conversation->admin_id ??= (string) $auteur->getKey();
        }

        $conversation->save();
        $conversation->increment($cote === Conversation::COTE_ADMIN ? 'non_lus_utilisateur' : 'non_lus_admin');

        return $message;
    }

    public function marquerLue(Conversation $conversation, User $lecteur): void
    {
        $cote = Conversation::coteDe($lecteur);
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
    public function messages(Conversation $conversation, ?Carbon $apres = null): Collection
    {
        return $conversation->messages()
            ->with('auteur')
            ->when($apres, fn ($q) => $q->where('created_at', '>', $apres))
            ->orderBy('created_at')
            ->get();
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
