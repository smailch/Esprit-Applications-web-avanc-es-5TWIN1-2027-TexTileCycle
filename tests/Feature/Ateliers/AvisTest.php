<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Avis;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\AvisService;
use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Avis clients de la fiche atelier : droits (rendez-vous terminé), saisie contrôlée en PHP, rendu échappé.
 * AtelierService et AvisService sont simulés : aucune lecture ni écriture sur Atlas.
 */
class AvisTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_ATELIER = '652f000000000000000000a1';

    private const ID_CLIENT = '652f000000000000000000c1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function client(): User
    {
        $user = new User(['name' => 'amira ben salah', 'email' => 'amira@example.test', 'role' => User::ROLE_CITOYEN, 'is_active' => true]);
        $user->setAttribute('_id', self::ID_CLIENT);

        return $user;
    }

    private function atelier(): Atelier
    {
        $atelier = new Atelier([
            'nom' => 'Couture Medina',
            'ville' => 'Tunis',
            'adresse' => '12 rue de la Kasbah',
            'statut' => Atelier::STATUT_ACTIF,
            'note_moyenne' => 4.5,
            'nb_avis' => 2,
            'horaires' => [],
        ]);
        $atelier->setAttribute('_id', self::ID_ATELIER);

        return $atelier->setRelation('services', new Collection());
    }

    private function avis(int $note, ?string $commentaire, string $nom = 'amira ben salah'): Avis
    {
        $auteur = new User(['name' => $nom]);
        $avis = new Avis(['atelier_id' => self::ID_ATELIER, 'user_id' => self::ID_CLIENT, 'note' => $note, 'commentaire' => $commentaire]);
        $avis->created_at = Carbon::now()->subDays(3);
        $avis->updated_at = $avis->created_at;

        return $avis->setRelation('auteur', $auteur);
    }

    private function mockAteliers(): void
    {
        $mock = Mockery::mock(AtelierService::class)->makePartial();
        $mock->shouldReceive('findActifOrFail')->with(self::ID_ATELIER)->andReturn($this->atelier());
        $this->app->instance(AtelierService::class, $mock);
    }

    /**
     * @return AvisService&MockInterface
     */
    private function mockAvis(bool $eligible = false, ?Avis $monAvis = null, array $recents = []): AvisService
    {
        $mock = Mockery::mock(AvisService::class);
        $notes = collect($recents)->map(fn (Avis $a) => $a->note);
        $mock->shouldReceive('resume')->andReturn(AvisService::resumeDe($notes));
        $mock->shouldReceive('recents')->andReturn(new Collection($recents));
        $mock->shouldReceive('avisDe')->andReturn($monAvis);
        $mock->shouldReceive('peutDonnerAvis')->andReturn($eligible);
        $this->app->instance(AvisService::class, $mock);

        return $mock;
    }

    private function urlFiche(): string
    {
        return '/ateliers/'.self::ID_ATELIER;
    }

    /* --------------------------------------------------------------- Affichage */

    public function test_un_visiteur_voit_les_avis_echappes_et_l_invitation_a_se_connecter(): void
    {
        $this->mockAteliers();
        $this->mockAvis(false, null, [
            $this->avis(5, "Ourlet parfait <script>alert(1)</script>\nmerci"),
            $this->avis(4, null, 'Karim'),
        ]);

        $this->get($this->urlFiche())
            ->assertOk()
            ->assertSee('id="avis"', false)
            ->assertSee('Avis clients')
            ->assertSee('4,5')
            ->assertSee('Amira B.')
            ->assertSee('Karim')
            ->assertDontSee('amira ben salah')
            ->assertSee('Réparation vérifiée')
            ->assertSee('Excellent')
            ->assertSee('il y a 3 jours')
            ->assertSee('Ourlet parfait &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<progress max="2" value="1"', false)
            ->assertSee('Connectez-vous')
            ->assertDontSee('data-avis-form', false)
            ->assertSee('href="#avis"', false);
    }

    public function test_sans_avis_un_etat_vide_est_affiche(): void
    {
        $this->mockAteliers();
        $this->mockAvis();

        $this->get($this->urlFiche())
            ->assertOk()
            ->assertSee("Pas encore d'avis vérifié pour cet atelier.", false)
            ->assertDontSee('class="avis-resume"', false);
    }

    public function test_un_client_sans_reparation_terminee_ne_voit_pas_le_formulaire(): void
    {
        $this->mockAteliers();
        $this->mockAvis(false);

        $this->actingAs($this->client())
            ->get($this->urlFiche())
            ->assertOk()
            ->assertSee('Vous pourrez donner votre avis après une réparation terminée chez cet atelier.')
            ->assertDontSee('data-avis-form', false);
    }

    public function test_un_client_eligible_voit_le_formulaire_sans_controle_html(): void
    {
        $this->mockAteliers();
        $this->mockAvis(true);

        $response = $this->actingAs($this->client())->get($this->urlFiche());

        $response->assertOk()
            ->assertSee('data-avis-form', false)
            ->assertSee('<details class="avis-formulaire"  open', false)
            ->assertSee('Publier mon avis')
            ->assertSee('name="note" value="5"', false)
            ->assertSee('name="commentaire"', false)
            ->assertSee(route('front.ateliers.avis.store', ['id' => self::ID_ATELIER]), false);

        preg_match('/<form[^>]*data-avis-form.*?<\/form>/s', $response->getContent(), $formulaire);
        $this->assertNotEmpty($formulaire);
        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|minlength|min|max|pattern)(=|\s|>)/', $formulaire[0]);
    }

    public function test_un_client_qui_a_deja_note_voit_son_avis_replie_et_peut_le_supprimer(): void
    {
        $this->mockAteliers();
        $monAvis = $this->avis(4, 'Rapide et soigné');
        $this->mockAvis(true, $monAvis, [$monAvis]);

        $this->actingAs($this->client())
            ->get($this->urlFiche())
            ->assertOk()
            ->assertSee('Votre avis')
            ->assertSee('Rapide et soigné')
            ->assertSee('Modifier mon avis')
            ->assertSee('<details class="avis-formulaire" >', false)
            ->assertSee('id="avis-note-4" name="note" value="4" data-libelle="Très bien" checked', false)
            ->assertSee('Mettre à jour')
            ->assertSee(route('front.ateliers.avis.destroy', ['id' => self::ID_ATELIER]), false);
    }

    /* --------------------------------------------------------------- Publication */

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->mockAteliers();
        $this->mockAvis()->shouldNotReceive('enregistrer');

        $this->post($this->urlFiche().'/avis', ['note' => 5])
            ->assertRedirect(route('front.login'));
    }

    public function test_la_saisie_invalide_est_refusee_en_php_avec_des_messages_en_francais(): void
    {
        $this->mockAteliers();
        $this->mockAvis(true)->shouldNotReceive('enregistrer');

        $this->actingAs($this->client())
            ->from($this->urlFiche())
            ->post($this->urlFiche().'/avis', ['note' => '7', 'commentaire' => str_repeat('a', 501)])
            ->assertRedirect($this->urlFiche().'#avis')
            ->assertSessionHasErrors([
                'note' => 'La note doit être comprise entre 1 et 5 étoiles.',
                'commentaire' => 'Le commentaire ne doit pas dépasser 500 caractères.',
            ], null, AvisService::BAG);

        $this->actingAs($this->client())
            ->from($this->urlFiche())
            ->post($this->urlFiche().'/avis', [])
            ->assertSessionHasErrors(['note' => 'Choisissez une note de 1 à 5 étoiles.'], null, AvisService::BAG);
    }

    public function test_un_client_sans_reparation_terminee_ne_peut_pas_publier(): void
    {
        $this->mockAteliers();
        $this->mockAvis(false)->shouldNotReceive('enregistrer');

        $this->actingAs($this->client())
            ->post($this->urlFiche().'/avis', ['note' => 5])
            ->assertRedirect($this->urlFiche().'#avis')
            ->assertSessionHasErrors(
                ['note' => 'Vous pourrez donner votre avis après une réparation terminée chez cet atelier.'],
                null,
                AvisService::BAG
            );
    }

    public function test_publier_un_avis(): void
    {
        $this->mockAteliers();
        $avis = $this->mockAvis(true);
        $avis->shouldReceive('enregistrer')
            ->once()
            ->withArgs(fn (User $user, Atelier $atelier, array $data) => (string) $user->getKey() === self::ID_CLIENT
                && (string) $atelier->getKey() === self::ID_ATELIER
                && $data === ['note' => '4', 'commentaire' => 'Très bon travail'])
            ->andReturn(new Avis());

        $this->actingAs($this->client())
            ->post($this->urlFiche().'/avis', ['note' => '4', 'commentaire' => '  Très bon travail  ', 'user_id' => 'pirate', 'atelier_id' => 'x'])
            ->assertRedirect($this->urlFiche().'#avis')
            ->assertSessionHas('avis_succes', 'Merci ! Votre avis est publié.');
    }

    public function test_modifier_son_avis(): void
    {
        $this->mockAteliers();
        $avis = $this->mockAvis(true, $this->avis(3, null));
        $avis->shouldReceive('enregistrer')->once()->andReturn(new Avis());

        $this->actingAs($this->client())
            ->post($this->urlFiche().'/avis', ['note' => '5'])
            ->assertSessionHas('avis_succes', 'Votre avis a été mis à jour.');
    }

    public function test_supprimer_son_avis(): void
    {
        $this->mockAteliers();
        $avis = $this->mockAvis(true);
        $avis->shouldReceive('supprimer')->once()->andReturn(true);

        $this->actingAs($this->client())
            ->delete($this->urlFiche().'/avis')
            ->assertRedirect($this->urlFiche().'#avis')
            ->assertSessionHas('avis_succes', 'Votre avis a été supprimé.');
    }

    public function test_un_identifiant_qui_n_est_pas_un_object_id_donne_une_404(): void
    {
        $this->mockAvis()->shouldNotReceive('enregistrer', 'supprimer');

        $this->actingAs($this->client())->post('/ateliers/pas-un-id/avis', ['note' => 5])->assertNotFound();
        $this->actingAs($this->client())->delete('/ateliers/pas-un-id/avis')->assertNotFound();
    }
}
