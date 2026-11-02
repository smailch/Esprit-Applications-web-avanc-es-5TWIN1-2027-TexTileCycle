<?php

namespace Tests\Unit\Vetements;

use App\Modules\Vetements\Exceptions\ChatVetementException;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\ChatVetementService;
use App\Modules\Vetements\Services\PolitiqueDeuxiemeChance;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatVetementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-gemini',
            'services.vision.gemini_model' => 'gemini-3.5-flash-lite',
            'services.vision.openai_key' => '',
        ]);
    }

    public function test_une_question_hors_vetement_est_refusee_sans_appel(): void
    {
        Http::fake();

        $resultat = (new ChatVetementService())->repondre('Quelle est la capitale de la France ?', [], []);

        $this->assertFalse($resultat['dans_sujet']);
        $this->assertSame(ChatVetementService::REFUS, $resultat['reponse']);
        Http::assertNothingSent();
    }

    public function test_une_question_sur_une_veste_renvoie_le_conseil(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'dans_sujet' => true,
                            'reponse' => 'Votre veste en denim peut être reprise en atelier : l\'ourlet est réparable.',
                        ], JSON_UNESCAPED_UNICODE),
                    ]]],
                ]],
            ]),
        ]);

        $resultat = (new ChatVetementService())->repondre(
            'Ma veste est-elle réparable ?',
            [],
            [['nom' => 'Veste · Taille M', 'etat' => 'À réparer']]
        );

        $this->assertTrue($resultat['dans_sujet']);
        $this->assertStringContainsString('veste', $resultat['reponse']);
        Http::assertSent(function (Request $request) {
            $corps = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($request->url(), 'gemini-3.5-flash-lite:generateContent')
                && str_contains((string) $corps, 'Veste')
                && str_contains((string) $corps, 'réparable');
        });
    }

    public function test_une_reponse_hors_sujet_du_modele_est_remplacee(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"dans_sujet":true,"reponse":"La capitale est Paris."}',
                    ]]],
                ]],
            ]),
        ]);

        $resultat = (new ChatVetementService())->repondre('Et ma veste, sinon la capitale ?', [], []);

        $this->assertFalse($resultat['dans_sujet']);
        $this->assertSame(ChatVetementService::REFUS, $resultat['reponse']);
    }

    public function test_une_salutation_peut_ouvrir_la_conversation(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"dans_sujet":true,"reponse":"Bonjour. Je peux vous aider à réparer, donner ou recycler un vêtement."}',
                    ]]],
                ]],
            ]),
        ]);

        $resultat = (new ChatVetementService())->repondre('Bonjour', [], []);

        $this->assertTrue($resultat['dans_sujet']);
        $this->assertStringContainsString('vêtement', $resultat['reponse']);
    }

    public function test_le_resume_d_une_piece_ne_contient_pas_la_photo(): void
    {
        $vetement = new Vetement([
            'type' => 'Veste',
            'size' => 'M',
            'material' => 'Denim',
            'condition_label' => 'À réparer',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'intended_action' => Vetement::ACTION_REPARATION,
            'image_path' => 'vetements/secret.jpg',
            'analyse_ia' => [
                'verdict' => PolitiqueDeuxiemeChance::VERDICT_REPARABLE,
                'verdict_libelle' => 'Deuxième chance',
                'resume' => 'Ourlet décousu.',
            ],
        ]);

        $resume = (new VetementService())->decrirePourChat($vetement);

        $this->assertSame('Veste · Taille M', $resume['nom']);
        $this->assertSame('Denim', $resume['matiere']);
        $this->assertSame('Deuxième chance', $resume['verdict']);
        $this->assertArrayNotHasKey('image_path', $resume);
    }

    public function test_un_modele_sature_bascule_vers_le_suivant(): void
    {
        config(['services.vision.gemini_model' => 'gemini-flash-latest']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'unavailable']], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'text' => '{"dans_sujet":true,"reponse":"Ce jean peut être donné s\'il reste portable."}',
                        ]]],
                    ]],
                ], 200),
        ]);

        $resultat = (new ChatVetementService())->repondre('Puis-je donner ce jean ?', [], []);

        $this->assertTrue($resultat['dans_sujet']);
        $this->assertStringContainsString('jean', $resultat['reponse']);
        Http::assertSentCount(2);
    }

    public function test_sans_cle_le_conseiller_est_indisponible(): void
    {
        config([
            'services.vision.gemini_key' => '',
            'services.vision.openai_key' => '',
        ]);

        $this->expectException(ChatVetementException::class);

        (new ChatVetementService())->repondre('Ma robe est-elle réparable ?', [], []);
    }
}
