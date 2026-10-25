<?php

namespace Tests\Feature\Vetements;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class StyleVetementTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-test',
            'services.vision.gemini_model' => 'gemini-3.5-flash-lite',
            'services.vision.openai_key' => '',
        ]);
    }

    public function test_un_invite_ne_peut_pas_proposer_un_style(): void
    {
        $this->postJson('/mes-vetements/style', [
            'photo' => $this->photo(),
        ])->assertRedirect('/connexion');
    }

    public function test_sans_cle_le_style_est_indisponible(): void
    {
        config([
            'services.vision.gemini_key' => '',
            'services.vision.openai_key' => '',
        ]);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/style', [
                'photo' => $this->photo(),
            ])
            ->assertStatus(503)
            ->assertJsonPath('disponible', false);

        Http::assertNothingSent();
    }

    public function test_une_photo_renvoie_la_maquette_quand_le_rendu_est_sature(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => json_encode([
                                'est_vetement' => true,
                                'type' => 'Jean',
                                'titre' => 'Jean repris',
                                'style' => 'Pièce contraste',
                                'description' => 'Le genou est couvert d\'une pièce claire.',
                                'couleurs' => ['#1B3A4B', '#E8B931', '#F3EDE2'],
                                'gestes' => ['Poser la pièce'],
                            ], JSON_UNESCAPED_UNICODE),
                        ]]],
                    ]],
                ], 200)
                ->push(['error' => ['message' => 'quota']], 429)
                ->push(['error' => ['message' => 'quota']], 429),
        ]);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/style', [
                'photo' => $this->photo(),
            ])
            ->assertOk()
            ->assertJsonPath('est_vetement', true)
            ->assertJsonPath('type', 'Jean')
            ->assertJsonPath('titre', 'Jean repris')
            ->assertJsonPath('vue', null);
    }

    public function test_la_page_propose_le_style_3d(): void
    {
        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'intended_action' => Vetement::ACTION_REPARATION,
            'image_path' => 'vetements/veste.jpg',
        ]);
        $vetement->setAttribute('_id', '652f000000000000000000e9');
        $vetement->setRelation('cycleEvents', new Collection());

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listActifsForOwner')->once()->andReturn(collect([$vetement]));
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mes-vetements')
            ->assertOk()
            ->assertSee('Nouveau style 3D')
            ->assertSee('Style 3D')
            ->assertSee('/mes-vetements/style', false)
            ->assertSee('style-vetement.js', false);
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
