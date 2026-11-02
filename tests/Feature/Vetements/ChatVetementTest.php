<?php

namespace Tests\Feature\Vetements;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\ChatVetementService;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class ChatVetementTest extends TestCase
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

    public function test_un_invite_ne_peut_pas_discuter(): void
    {
        $this->postJson('/mes-vetements/chat', [
            'message' => 'Ma veste est-elle réparable ?',
        ])->assertRedirect('/connexion');
    }

    public function test_sans_cle_le_chat_repond_indisponible(): void
    {
        config([
            'services.vision.gemini_key' => '',
            'services.vision.openai_key' => '',
        ]);

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/chat', [
                'message' => 'Ma veste est-elle réparable ?',
            ])
            ->assertStatus(503)
            ->assertJsonPath('disponible', false);

        Http::assertNothingSent();
    }

    public function test_une_question_hors_sujet_est_refusee(): void
    {
        Http::fake();
        $this->simulerGardeRobe();

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/chat', [
                'message' => 'Écris-moi un poème sur la lune.',
            ])
            ->assertOk()
            ->assertJsonPath('dans_sujet', false)
            ->assertJsonPath('reponse', ChatVetementService::REFUS);

        Http::assertNothingSent();
    }

    public function test_une_question_sur_la_garde_robe_utilise_le_contexte(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'dans_sujet' => true,
                            'reponse' => 'Votre veste a une deuxième chance : un atelier peut reprendre l\'ourlet.',
                        ], JSON_UNESCAPED_UNICODE),
                    ]]],
                ]],
            ]),
        ]);
        $this->simulerGardeRobe();

        $this->actingAs($this->citoyen())
            ->postJson('/mes-vetements/chat', [
                'message' => 'Que faire de ma veste ?',
                'historique' => [
                    ['role' => 'user', 'contenu' => 'Bonjour'],
                    ['role' => 'assistant', 'contenu' => 'Je peux vous aider à réparer un vêtement.'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('dans_sujet', true)
            ->assertJsonPath('reponse', 'Votre veste a une deuxième chance : un atelier peut reprendre l\'ourlet.');
    }

    public function test_la_page_affiche_le_conseiller(): void
    {
        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'intended_action' => Vetement::ACTION_REPARATION,
        ]);
        $vetement->setAttribute('_id', '652f000000000000000000e9');
        $vetement->setRelation('cycleEvents', new Collection());

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listActifsForOwner')->once()->andReturn(collect([$vetement]));
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mes-vetements')
            ->assertOk()
            ->assertSee('Conseiller textile')
            ->assertSee('/mes-vetements/chat', false)
            ->assertSee('Réparation, don et recyclage uniquement.');
    }

    private function simulerGardeRobe(): void
    {
        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('contexteChat')->once()->andReturn([
            [
                'nom' => 'Veste · Taille M',
                'matiere' => 'Denim',
                'etat' => 'À réparer',
                'statut' => 'En attente',
                'parcours' => 'Réparation',
                'verdict' => 'Deuxième chance',
                'resume' => 'Ourlet décousu.',
            ],
        ]);
        $this->app->instance(VetementService::class, $service);
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
}
