<?php

namespace Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Usage :
 *   Conversation::factory()->for($citoyen, 'interlocuteur')->for($admin, 'admin')->avecEchange()->create();
 *   Conversation::factory()->surContexte($signalement)->create();
 * Sans interlocuteur ni admin fournis, des comptes sont créés en base.
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    private const SUJETS = [
        'Suivi de votre signalement',
        'Validation de votre compte partenaire',
        'Question sur la plateforme',
        'Mise à jour de vos horaires',
        'Informations manquantes sur votre fiche',
    ];

    public function definition(): array
    {
        return [
            'sujet' => $this->faker->randomElement(self::SUJETS),
            'user_id' => fn () => (string) User::factory()->create(['role' => User::ROLE_CITOYEN])->getKey(),
            'admin_id' => fn () => (string) User::factory()->create(['role' => User::ROLE_ADMIN])->getKey(),
            'statut' => Conversation::STATUT_OUVERTE,
            'initiee_par' => Conversation::COTE_ADMIN,
            'non_lus_admin' => 0,
            'non_lus_utilisateur' => 0,
        ];
    }

    public function fermee(): static
    {
        return $this->state(['statut' => Conversation::STATUT_FERMEE]);
    }

    /** Rattache la conversation à un signalement, un atelier ou une association (morphTo). */
    public function surContexte(Model $contexte): static
    {
        return $this->state([
            'contexte_type' => array_search(get_class($contexte), Conversation::CONTEXTES, true) ?: class_basename($contexte),
            'contexte_id' => (string) $contexte->getKey(),
        ]);
    }

    /**
     * Crée un échange (relation hasMany) : les messages alternent admin / interlocuteur,
     * puis les champs dénormalisés de la conversation (aperçu, non-lus…) sont recalculés.
     *
     * @param  list<string>  $contenus  premier message côté admin, puis alternance
     */
    public function avecEchange(array $contenus = [], int $dernierNonLu = 1): static
    {
        return $this->afterCreating(function (Conversation $conversation) use ($contenus, $dernierNonLu) {
            $contenus = $contenus ?: [$this->faker->sentence(14), $this->faker->sentence(10), $this->faker->sentence(12)];
            $debut = now()->subDays(count($contenus))->setTime(9, 30);

            foreach ($contenus as $i => $contenu) {
                $cote = ($i % 2 === 0) === ($conversation->initiee_par === Conversation::COTE_ADMIN)
                    ? Conversation::COTE_ADMIN
                    : Conversation::COTE_UTILISATEUR;

                Message::factory()
                    ->for($conversation)
                    ->create([
                        'user_id' => $cote === Conversation::COTE_ADMIN ? $conversation->admin_id : $conversation->user_id,
                        'cote' => $cote,
                        'contenu' => $contenu,
                        'source' => $conversation->source,
                        'created_at' => $debut->copy()->addHours($i * 5)->addMinutes($i * 7),
                    ]);
            }

            $dernier = $conversation->messages()->orderBy('created_at', 'desc')->first();
            $autreCote = $dernier->cote === Conversation::COTE_ADMIN ? 'utilisateur' : 'admin';

            $conversation->fill([
                'dernier_message_at' => $dernier->created_at,
                'dernier_message_cote' => $dernier->cote,
                'apercu' => Str::limit($dernier->contenu, 90),
                'lu_admin_at' => $dernier->created_at,
                'lu_utilisateur_at' => $dernier->created_at,
                "non_lus_{$autreCote}" => $dernierNonLu,
                "lu_{$autreCote}_at" => $dernierNonLu ? $dernier->created_at->copy()->subMinute() : $dernier->created_at,
            ])->save();
        });
    }
}
