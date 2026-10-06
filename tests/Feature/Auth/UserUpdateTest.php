<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Http\Requests\UpdateUserRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Jenssegers\Mongodb\Query\Builder as MongoBuilder;
use Mockery;
use MongoDB\BSON\ObjectId;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Modification d'un utilisateur depuis l'admin, sans aucun accès à Atlas : l'unicité de l'e-mail
 * passe par le vrai DatabasePresenceVerifier, dont la requête Mongo compilée est évaluée sur des
 * utilisateurs en mémoire ; les écritures (save) sont simulées.
 */
class UserUpdateTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_ATELIER = '652f00000000000000000011';

    private const ID_AUTRE = '652f00000000000000000022';

    private const ID_ADMIN = '652f00000000000000000033';

    private const UTILISATEURS = [
        self::ID_ATELIER => 'atelier@example.test',
        self::ID_AUTRE => 'autre@example.test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    /**
     * Vérificateur Laravel réel ; seul count() est évalué en mémoire à partir des filtres Mongo compilés.
     * Les _id comparés doivent être de vrais ObjectId : une chaîne non convertie ne neutraliserait rien.
     */
    private function verificateurEnMemoire(): DatabasePresenceVerifier
    {
        return new class(app('db'), self::UTILISATEURS) extends DatabasePresenceVerifier
        {
            public function __construct($db, private array $utilisateurs)
            {
                parent::__construct($db);
            }

            protected function table($table)
            {
                $connexion = $this->db->connection($this->connection);

                return (new class($connexion, $connexion->getPostProcessor(), $this->utilisateurs) extends MongoBuilder
                {
                    public function __construct($connexion, $processor, private array $utilisateurs)
                    {
                        parent::__construct($connexion, $processor);
                    }

                    public function count($columns = '*')
                    {
                        $filtre = $this->compileWheres();
                        $correspondances = 0;

                        foreach ($this->utilisateurs as $id => $email) {
                            $correspondances += (int) $this->correspond($filtre, ['_id' => new ObjectId($id), 'email' => $email]);
                        }

                        return $correspondances;
                    }

                    private function correspond(array $filtre, array $document): bool
                    {
                        foreach ($filtre as $cle => $condition) {
                            if ($cle === '$and') {
                                foreach ($condition as $sousFiltre) {
                                    if (! $this->correspond($sousFiltre, $document)) {
                                        return false;
                                    }
                                }

                                continue;
                            }

                            $valeur = $document[$cle] ?? null;

                            if (is_array($condition) && array_key_exists('$ne', $condition)) {
                                if ($this->egal($valeur, $condition['$ne'])) {
                                    return false;
                                }

                                continue;
                            }

                            if (! $this->egal($valeur, $condition)) {
                                return false;
                            }
                        }

                        return true;
                    }

                    private function egal(mixed $a, mixed $b): bool
                    {
                        if ($a instanceof ObjectId || $b instanceof ObjectId) {
                            return $a instanceof ObjectId && $b instanceof ObjectId && (string) $a === (string) $b;
                        }

                        return $a === $b;
                    }
                })->from($table);
            }
        };
    }

    private function admin(): User
    {
        $admin = new User(['name' => 'Admin', 'email' => 'admin@example.test', 'role' => User::ROLE_ADMIN]);
        $admin->setAttribute('_id', self::ID_ADMIN);

        return $admin;
    }

    private function rulesPour(mixed $parametreUser): array
    {
        $request = UpdateUserRequest::create('/admin/utilisateurs/'.self::ID_ATELIER, 'PUT');
        $route = (new Route('PUT', '/admin/utilisateurs/{user}', []))->bind($request);
        $route->setParameter('user', $parametreUser);
        $request->setRouteResolver(fn () => $route);

        return $request->rules();
    }

    private function donnees(array $override = []): array
    {
        return array_replace([
            'name' => 'Atelier Couture',
            'email' => 'atelier@example.test',
            'password' => null,
            'role' => User::ROLE_ATELIER,
            'phone' => null,
            'is_active' => '1',
        ], $override);
    }

    private function valider(array $donnees, mixed $parametreUser = self::ID_ATELIER): \Illuminate\Validation\Validator
    {
        $validator = Validator::make($donnees, $this->rulesPour($parametreUser));
        $validator->setPresenceVerifier($this->verificateurEnMemoire());

        return $validator;
    }

    /* ------------------------------------------------- Paramètre de route */

    public function test_rules_accepte_l_identifiant_en_chaine_comme_un_modele(): void
    {
        $modele = new User(['email' => 'atelier@example.test']);
        $modele->setAttribute('_id', self::ID_ATELIER);

        foreach ([self::ID_ATELIER, $modele] as $parametre) {
            $unique = (string) $this->rulesPour($parametre)['email'][3];

            $this->assertStringContainsString('"'.self::ID_ATELIER.'"', $unique);
            $this->assertStringEndsWith(',_id', $unique);
        }
    }

    public function test_la_route_admin_ne_plante_plus_sur_un_identifiant_en_chaine(): void
    {
        $this->app['validator']->setPresenceVerifier($this->verificateurEnMemoire());

        $this->actingAs($this->admin())
            ->from('/admin/utilisateurs')
            ->put('/admin/utilisateurs/'.self::ID_ATELIER, $this->donnees(['email' => 'autre@example.test']))
            ->assertRedirect('/admin/utilisateurs')
            ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
    }

    /* ------------------------------------------------------ Unicité e-mail */

    public function test_garder_son_propre_email_est_accepte(): void
    {
        $this->assertTrue($this->valider($this->donnees())->passes());
    }

    public function test_un_email_deja_pris_par_un_autre_utilisateur_est_refuse(): void
    {
        $validator = $this->valider($this->donnees(['email' => 'autre@example.test']));

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_sans_exclusion_son_propre_email_serait_considere_comme_pris(): void
    {
        $this->assertTrue($this->valider($this->donnees(), null)->fails());
    }

    /* -------------------------------------------------------- Mot de passe */

    public function test_mot_de_passe_vide_ou_absent_passe_la_validation(): void
    {
        $this->assertTrue($this->valider($this->donnees(['password' => null]))->passes());
        $this->assertTrue($this->valider($this->donnees(['password' => '']))->passes());
        $this->assertTrue($this->valider($this->donnees(['password' => 'court']))->fails());
    }

    private function utilisateurExistant(string $ancienHash): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['_id' => self::ID_ATELIER, 'email' => 'atelier@example.test', 'password' => $ancienHash]);
        $user->shouldReceive('save')->once()->andReturnTrue();

        return $user;
    }

    public function test_mot_de_passe_vide_ne_modifie_pas_le_mot_de_passe_existant(): void
    {
        $ancienHash = Hash::make('ancienMotDePasse');

        foreach ([null, ''] as $vide) {
            $user = $this->utilisateurExistant($ancienHash);

            (new UserService())->update($user, $this->donnees(['password' => $vide]));

            $this->assertSame($ancienHash, $user->getAttributes()['password']);
        }
    }

    public function test_nouveau_mot_de_passe_est_hashe_une_seule_fois(): void
    {
        $ancienHash = Hash::make('ancienMotDePasse');
        $user = $this->utilisateurExistant($ancienHash);

        (new UserService())->update($user, $this->donnees(['password' => 'nouveauMotDePasse']));

        $nouveauHash = $user->getAttributes()['password'];
        $this->assertNotSame($ancienHash, $nouveauHash);
        $this->assertNotSame('nouveauMotDePasse', $nouveauHash);
        $this->assertTrue(Hash::check('nouveauMotDePasse', $nouveauHash));
        $this->assertFalse(Hash::check('ancienMotDePasse', $nouveauHash));
    }
}
