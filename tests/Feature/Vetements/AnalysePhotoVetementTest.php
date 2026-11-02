<?php

namespace Tests\Feature\Vetements;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\AnalyseImageVetementService;
use App\Modules\Vetements\Services\PolitiqueDeuxiemeChance;
use App\Modules\Vetements\Services\ResultatAnalyseVetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class AnalysePhotoVetementTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c9';

    private const ID_VETEMENT = '652f000000000000000000e9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    public function test_un_invite_ne_peut_pas_analyser_une_photo(): void
    {
        $this->postJson('/mes-vetements/analyse-photo', [
            'photo' => $this->photo(),
        ])->assertRedirect('/connexion');
    }

    public function test_sans_cle_l_analyse_repond_indisponible(): void
    {
        config([
            'services.vision.gemini_key' => '',
            'services.vision.openai_key' => '',
        ]);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/analyse-photo', [
                'photo' => $this->photo(),
            ])
            ->assertStatus(503)
            ->assertJsonPath('disponible', false);

        Http::assertNothingSent();
    }

    public function test_l_analyse_renvoie_la_recommandation_de_reparation(): void
    {
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-test',
            'services.vision.gemini_model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'est_vetement' => true,
                                'type' => 'Veste',
                                'matiere' => 'Denim',
                                'etat' => 'À réparer',
                                'defauts' => ['Couture d\'épaule ouverte'],
                                'gravite' => 'moyenne',
                                'reparable' => true,
                                'confiance' => 84,
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/analyse-photo', [
                'photo' => $this->photo(),
            ])
            ->assertOk()
            ->assertJsonPath('deuxieme_chance', true)
            ->assertJsonPath('verdict', PolitiqueDeuxiemeChance::VERDICT_REPARABLE)
            ->assertJsonPath('recommandation', Vetement::ACTION_REPARATION);
    }

    public function test_la_carte_affiche_le_verdict_et_propose_une_nouvelle_analyse(): void
    {
        config(['services.vision.gemini_key' => 'clef-test']);

        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'intended_action' => Vetement::ACTION_REPARATION,
            'image_path' => 'vetements/veste.jpg',
            'analyse_ia' => [
                'verdict' => PolitiqueDeuxiemeChance::VERDICT_REPARABLE,
                'verdict_libelle' => 'Deuxième chance',
                'deuxieme_chance' => true,
                'resume' => 'Deuxième chance : oui. Un atelier peut reprendre cette pièce. Ourlet décousu.',
            ],
        ]);
        $vetement->setAttribute('_id', self::ID_VETEMENT);
        $vetement->setRelation('cycleEvents', new Collection());

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listActifsForOwner')->once()->andReturn(collect([$vetement]));
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mes-vetements')
            ->assertOk()
            ->assertSee('Deuxième chance détectée', false)
            ->assertSee('Ourlet décousu', false)
            ->assertSee('Relancer l\'analyse')
            ->assertSee('/mes-vetements/analyse-photo', false);
    }

    public function test_une_photo_deja_enregistree_peut_etre_reanalysee(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vetements/veste.jpg', 'image-test');

        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'image_path' => 'vetements/veste.jpg',
        ]);
        $vetement->setAttribute('_id', self::ID_VETEMENT);

        $resultat = ResultatAnalyseVetement::depuisModele([
            'est_vetement' => true,
            'type' => 'Veste',
            'matiere' => 'Denim',
            'etat' => 'À réparer',
            'defauts' => ['Bouton manquant'],
            'gravite' => 'legere',
            'reparable' => true,
            'confiance' => 80,
        ], 'gemini-2.5-flash');

        $analyses = Mockery::mock(AnalyseImageVetementService::class);
        $analyses->shouldReceive('estConfigure')->andReturn(true);
        $analyses->shouldReceive('analyser')->once()->andReturn($resultat);
        $this->app->instance(AnalyseImageVetementService::class, $analyses);

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('findOwnedByUser')->once()->andReturn($vetement);
        $service->shouldReceive('attacherAnalyse')->once()->with($vetement, $resultat->toArray())->andReturn($vetement);
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->post('/mes-vetements/'.self::ID_VETEMENT.'/analyse-photo')
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_une_piece_inconnue_renvoie_404(): void
    {
        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('findOwnedByUser')->once()->andReturn(null);
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->post('/mes-vetements/'.self::ID_VETEMENT.'/analyse-photo')
            ->assertNotFound();
    }

    private function citoyen(): User
    {
        $user = new User([
            'name' => 'Yasmine Ben Ali',
            'email' => 'yasmine@example.test',
            'role' => User::ROLE_CITOYEN,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', self::ID_USER);

        return $user;
    }

    private function photo(): UploadedFile
    {
        $chemin = storage_path('framework/testing/vetement.png');
        if (! is_dir(dirname($chemin))) {
            mkdir(dirname($chemin), 0777, true);
        }

        file_put_contents($chemin, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return new UploadedFile($chemin, 'vetement.png', 'image/png', UPLOAD_ERR_OK, true);
    }
}
