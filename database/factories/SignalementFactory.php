<?php

namespace Database\Factories;

use App\Modules\Auth\Models\User;
use App\Modules\Signalements\Models\Signalement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Usage :
 *   Signalement::factory()->for($citoyen, 'auteur')->pour($atelier)->create();
 *   Signalement::factory()->traite($admin)->create();
 * Sans auteur ni cible fournis, des comptes utilisateurs sont créés en base.
 *
 * @extends Factory<Signalement>
 */
class SignalementFactory extends Factory
{
    protected $model = Signalement::class;

    private const MOTIFS = [
        Signalement::TYPE_ANNONCE => [
            'Les photos ne correspondent pas au vêtement décrit dans l\'annonce.',
            'Annonce en double, le même vêtement est publié plusieurs fois.',
            'Le vêtement proposé au don est en très mauvais état, contrairement à la description.',
        ],
        Signalement::TYPE_CONTENU => [
            'Les horaires affichés sont faux, l\'atelier était fermé à l\'heure indiquée.',
            'Les tarifs pratiqués sont très différents des prix affichés sur la plateforme.',
            'La description contient des propos commerciaux trompeurs.',
        ],
        Signalement::TYPE_UTILISATEUR => [
            'Comportement irrespectueux lors de l\'échange par téléphone.',
            'Rendez-vous non honorés à plusieurs reprises sans prévenir.',
            'Messages insistants et inappropriés.',
        ],
    ];

    public function definition(): array
    {
        return [
            'user_id' => fn () => (string) User::factory()->create(['role' => User::ROLE_CITOYEN])->getKey(),
            'cible_type' => 'User',
            'cible_id' => fn () => (string) User::factory()->create(['role' => User::ROLE_CITOYEN])->getKey(),
            'type' => fn (array $attributes) => Signalement::CIBLES[$attributes['cible_type']]['type'],
            'motif' => fn (array $attributes) => $this->faker->randomElement(self::MOTIFS[$attributes['type']]),
            'statut' => Signalement::STATUT_EN_ATTENTE,
        ];
    }

    /**
     * Cible polymorphe du signalement (Atelier, Vetement, Association, Don ou User).
     */
    public function pour(Model $cible): static
    {
        $alias = array_search(get_class($cible), Relation::morphMap(), true) ?: class_basename($cible);

        return $this->state([
            'cible_type' => $alias,
            'cible_id' => (string) $cible->getKey(),
        ]);
    }

    public function enAttente(): static
    {
        return $this->state(['statut' => Signalement::STATUT_EN_ATTENTE]);
    }

    public function traite(?User $admin = null): static
    {
        return $this->modere(Signalement::STATUT_TRAITE, $admin, 'Contenu vérifié et corrigé avec le partenaire.');
    }

    public function rejete(?User $admin = null): static
    {
        return $this->modere(Signalement::STATUT_REJETE, $admin, 'Après vérification, aucun manquement constaté.');
    }

    private function modere(string $statut, ?User $admin, string $note): static
    {
        return $this->state(fn () => [
            'statut' => $statut,
            'note_admin' => $note,
            'traite_par' => (string) ($admin ?? User::factory()->create(['role' => User::ROLE_ADMIN]))->getKey(),
            'traite_le' => now()->subDays($this->faker->numberBetween(0, 10)),
        ]);
    }
}
