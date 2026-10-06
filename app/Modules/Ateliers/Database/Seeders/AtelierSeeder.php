<?php

namespace App\Modules\Ateliers\Database\Seeders;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Données de test du module Ateliers (Grand Tunis). Idempotent.
 * Ne touche qu'aux comptes dont l'email se termine par @textilecycle.test.
 */
class AtelierSeeder extends Seeder
{
    public const EMAIL_DOMAIN = '@textilecycle.test';

    public const DEV_PASSWORD = 'Atelier2026!';

    public function run(): void
    {
        $passwordHash = Hash::make(self::DEV_PASSWORD);

        foreach (self::ateliers() as $index => $data) {
            $email = 'atelier'.($index + 1).self::EMAIL_DOMAIN;

            if (! str_ends_with($email, self::EMAIL_DOMAIN)) {
                throw new InvalidArgumentException("Email hors domaine de test : {$email}");
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $data['nom'],
                    'password' => $passwordHash,
                    'role' => User::ROLE_ATELIER,
                    'phone' => $data['telephone'],
                    'is_active' => true,
                ]
            );

            $atelier = Atelier::updateOrCreate(
                ['user_id' => (string) $user->getKey()],
                [
                    'nom' => $data['nom'],
                    'specialite' => $data['specialite'],
                    'description' => $data['description'],
                    'adresse' => $data['adresse'],
                    'ville' => $data['ville'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'telephone' => $data['telephone'],
                    'horaires' => $data['horaires'],
                    'note_moyenne' => $data['note_moyenne'],
                    'nb_avis' => $data['nb_avis'],
                    'statut' => $data['statut'],
                ]
            );

            foreach ($data['services'] as $service) {
                Service::updateOrCreate(
                    ['atelier_id' => (string) $atelier->getKey(), 'nom' => $service['nom']],
                    [
                        'description' => $service['description'],
                        'prix_estime' => $service['prix_estime'],
                        'duree_estimee' => $service['duree_estimee'],
                    ]
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function ateliers(): array
    {
        return [
            [
                'nom' => 'Couture Plus',
                'specialite' => 'Denim & retouches',
                'description' => 'Atelier de retouches spécialisé dans le denim et les ajustements sur mesure.',
                'adresse' => '45 Avenue Habib Bourguiba',
                'ville' => 'La Marsa',
                'latitude' => 36.8782,
                'longitude' => 10.3247,
                'telephone' => '+216 71 774 210',
                'horaires' => self::horaires(samediMatinSeulement: false),
                'note_moyenne' => 4.9,
                'nb_avis' => 58,
                'statut' => Atelier::STATUT_ACTIF,
                'services' => [
                    ['nom' => 'Retouche denim', 'description' => 'Reprise de jeans : coutures, poches, entrejambe.', 'prix_estime' => 25, 'duree_estimee' => 60],
                    ['nom' => 'Raccourcir un ourlet', 'description' => 'Ourlet pantalon ou jean, finition d\'origine possible.', 'prix_estime' => 12, 'duree_estimee' => 30],
                    ['nom' => 'Changer une fermeture', 'description' => 'Remplacement de fermeture éclair sur pantalon ou veste.', 'prix_estime' => 18, 'duree_estimee' => 45],
                    ['nom' => 'Recoudre une déchirure', 'description' => 'Réparation invisible ou décorative d\'un accroc.', 'prix_estime' => 10, 'duree_estimee' => 20],
                ],
            ],
            [
                'nom' => "L'Atelier Vert",
                'specialite' => 'Upcycling créatif',
                'description' => 'Upcycling créatif : transformer les vêtements usés en nouvelles pièces.',
                'adresse' => '18 Rue Ibn Khaldoun, Montplaisir',
                'ville' => 'Centre-ville Tunis',
                'latitude' => 36.8235,
                'longitude' => 10.2125,
                'telephone' => '+216 71 905 332',
                'horaires' => self::horaires(samediMatinSeulement: true),
                'note_moyenne' => 4.8,
                'nb_avis' => 41,
                'statut' => Atelier::STATUT_ACTIF,
                'services' => [
                    ['nom' => 'Upcycling créatif', 'description' => 'Transformation d\'une pièce en sac, top ou accessoire.', 'prix_estime' => 65, 'duree_estimee' => 180],
                    ['nom' => 'Patchs et broderies', 'description' => 'Ajout de patchs ou broderies pour masquer une usure.', 'prix_estime' => 20, 'duree_estimee' => 60],
                    ['nom' => 'Recoudre une déchirure', 'description' => 'Réparation visible façon sashiko ou invisible.', 'prix_estime' => 12, 'duree_estimee' => 30],
                ],
            ],
            [
                'nom' => 'Fil & Aiguille',
                'specialite' => 'Tricot & textile',
                'description' => 'Spécialiste du tricot et des textiles délicats.',
                'adresse' => '7 Rue Abou Kacem Chebbi, El Menzah 6',
                'ville' => 'El Menzah',
                'latitude' => 36.8510,
                'longitude' => 10.1705,
                'telephone' => '+216 22 418 905',
                'horaires' => self::horaires(samediMatinSeulement: true),
                'note_moyenne' => 4.7,
                'nb_avis' => 33,
                'statut' => Atelier::STATUT_ACTIF,
                'services' => [
                    ['nom' => 'Reprise de tricot', 'description' => 'Reprise de mailles filées et trous sur pulls en laine.', 'prix_estime' => 22, 'duree_estimee' => 75],
                    ['nom' => 'Réparation textile délicat', 'description' => 'Soie, lin, cachemire : réparation à la main.', 'prix_estime' => 35, 'duree_estimee' => 90],
                    ['nom' => 'Remplacement de doublure', 'description' => 'Nouvelle doublure pour veste ou manteau.', 'prix_estime' => 55, 'duree_estimee' => 150],
                ],
            ],
            [
                'nom' => 'Atelier Yasmine',
                'specialite' => 'Robes & cérémonie',
                'description' => 'Couture et ajustements pour robes et tenues de cérémonie.',
                'adresse' => '23 Avenue de la République',
                'ville' => 'Ariana',
                'latitude' => 36.8625,
                'longitude' => 10.1956,
                'telephone' => '+216 70 812 447',
                'horaires' => self::horaires(samediMatinSeulement: false),
                'note_moyenne' => 4.5,
                'nb_avis' => 27,
                'statut' => Atelier::STATUT_ACTIF,
                'services' => [
                    ['nom' => 'Ajustement de robe', 'description' => 'Reprise de taille, longueur et bretelles.', 'prix_estime' => 30, 'duree_estimee' => 90],
                    ['nom' => 'Raccourcir un ourlet', 'description' => 'Ourlet robe ou jupe, ourlet invisible.', 'prix_estime' => 15, 'duree_estimee' => 40],
                ],
            ],
            [
                'nom' => 'Carthage Retouches',
                'specialite' => 'Retouches express',
                'description' => 'Retouches rapides et réparations du quotidien.',
                'adresse' => '4 Rue Hannibal, Carthage Byrsa',
                'ville' => 'Carthage',
                'latitude' => 36.8528,
                'longitude' => 10.3233,
                'telephone' => '+216 71 733 019',
                'horaires' => self::horaires(samediMatinSeulement: true),
                'note_moyenne' => 4.3,
                'nb_avis' => 19,
                'statut' => Atelier::STATUT_ACTIF,
                'services' => [
                    ['nom' => 'Changer une fermeture', 'description' => 'Fermeture de jupe, robe ou blouson.', 'prix_estime' => 16, 'duree_estimee' => 40],
                    ['nom' => 'Recoudre une déchirure', 'description' => 'Couture simple sur tous textiles.', 'prix_estime' => 8, 'duree_estimee' => 15],
                    ['nom' => 'Remplacement de boutons', 'description' => 'Pose de boutons et reprise de boutonnières.', 'prix_estime' => 5, 'duree_estimee' => 15],
                ],
            ],
            [
                'nom' => 'Le Dé à Coudre',
                'specialite' => 'Couture traditionnelle',
                'description' => 'Atelier familial de couture traditionnelle et moderne.',
                'adresse' => '56 Avenue Habib Bourguiba, Le Bardo',
                'ville' => 'Le Bardo',
                'latitude' => 36.8092,
                'longitude' => 10.1406,
                'telephone' => '+216 98 305 662',
                'horaires' => self::horaires(samediMatinSeulement: false),
                'note_moyenne' => 4.1,
                'nb_avis' => 14,
                'statut' => Atelier::STATUT_ACTIF,
                'services' => [
                    ['nom' => 'Ajustement de robe', 'description' => 'Ajustements sur robes traditionnelles et modernes.', 'prix_estime' => 40, 'duree_estimee' => 120],
                    ['nom' => 'Remplacement de doublure', 'description' => 'Doublure de jebba, veste ou manteau.', 'prix_estime' => 60, 'duree_estimee' => 160],
                    ['nom' => 'Raccourcir un ourlet', 'description' => 'Ourlet simple ou double.', 'prix_estime' => 10, 'duree_estimee' => 30],
                    ['nom' => 'Recoudre une déchirure', 'description' => 'Reprise à la main ou à la machine.', 'prix_estime' => 9, 'duree_estimee' => 20],
                    ['nom' => 'Changer une fermeture', 'description' => 'Fermetures invisibles et métalliques.', 'prix_estime' => 15, 'duree_estimee' => 35],
                ],
            ],
            [
                'nom' => 'Goulette Couture',
                'specialite' => 'Ourlets & réparations',
                'description' => 'Nouvel atelier en cours de validation par l\'équipe TexTileCycle.',
                'adresse' => '12 Avenue Franklin Roosevelt',
                'ville' => 'La Goulette',
                'latitude' => 36.8181,
                'longitude' => 10.3050,
                'telephone' => '+216 55 902 118',
                'horaires' => self::horaires(samediMatinSeulement: true),
                'note_moyenne' => 3.9,
                'nb_avis' => 5,
                'statut' => Atelier::STATUT_EN_ATTENTE,
                'services' => [
                    ['nom' => 'Raccourcir un ourlet', 'description' => 'Ourlet pantalon, jean ou rideau.', 'prix_estime' => 10, 'duree_estimee' => 30],
                    ['nom' => 'Recoudre une déchirure', 'description' => 'Réparation rapide sur place.', 'prix_estime' => 7, 'duree_estimee' => 15],
                ],
            ],
            [
                'nom' => 'Atelier du Sud',
                'specialite' => 'Denim & fermetures',
                'description' => 'Atelier temporairement suspendu (test du statut suspendu).',
                'adresse' => '30 Avenue Farhat Hached',
                'ville' => 'Ben Arous',
                'latitude' => 36.7531,
                'longitude' => 10.2189,
                'telephone' => '+216 79 381 540',
                'horaires' => self::horaires(samediMatinSeulement: false),
                'note_moyenne' => 3.8,
                'nb_avis' => 9,
                'statut' => Atelier::STATUT_SUSPENDU,
                'services' => [
                    ['nom' => 'Retouche denim', 'description' => 'Reprise et renfort de jeans.', 'prix_estime' => 20, 'duree_estimee' => 50],
                    ['nom' => 'Changer une fermeture', 'description' => 'Remplacement de fermeture éclair.', 'prix_estime' => 14, 'duree_estimee' => 35],
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<array{0:string,1:string}>>
     */
    private static function horaires(bool $samediMatinSeulement): array
    {
        $journee = [['09:00', '12:00'], ['14:00', '18:00']];

        return [
            'lundi' => $journee,
            'mardi' => $journee,
            'mercredi' => $journee,
            'jeudi' => $journee,
            'vendredi' => [['09:00', '12:00'], ['15:00', '18:00']],
            'samedi' => $samediMatinSeulement ? [['09:00', '13:00']] : $journee,
            'dimanche' => [],
        ];
    }
}
