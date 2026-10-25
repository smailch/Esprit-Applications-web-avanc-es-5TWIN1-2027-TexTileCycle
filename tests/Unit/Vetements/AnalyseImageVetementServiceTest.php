<?php

namespace Tests\Unit\Vetements;

use App\Modules\Vetements\Exceptions\AnalyseImageException;
use App\Modules\Vetements\Services\AnalyseImageVetementService;
use App\Modules\Vetements\Services\PolitiqueDeuxiemeChance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyseImageVetementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-gemini',
            'services.vision.gemini_model' => 'gemini-2.5-flash',
            'services.vision.openai_key' => '',
            'services.vision.openai_model' => 'gpt-4o-mini',
        ]);
    }

    public function test_gemini_est_interroge_une_seule_fois_pour_la_meme_photo(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'est_vetement' => true,
                                'type' => 'Veste',
                                'matiere' => 'Coton',
                                'etat' => 'À réparer',
                                'defauts' => ['Poche décousue'],
                                'gravite' => 'legere',
                                'reparable' => true,
                                'confiance' => 81,
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $service = new AnalyseImageVetementService();
        $photo = $this->photo();

        $premier = $service->analyser($photo);
        $second = $service->analyser($photo);

        $this->assertTrue($premier->toArray()['deuxieme_chance']);
        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_REPARABLE, $second->toArray()['verdict']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'gemini-2.5-flash:generateContent')
                && isset($request['contents'][0]['parts'][1]['inlineData']['data']);
        });
    }

    public function test_une_reponse_illisible_est_rejetee(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'pas de json']]],
                ]],
            ]),
        ]);

        $this->expectException(AnalyseImageException::class);

        (new AnalyseImageVetementService())->analyser($this->photo());
    }

    public function test_une_cle_refusee_explique_lechec_sans_rappeler_le_secret(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad key'], 401),
        ]);

        try {
            (new AnalyseImageVetementService())->analyser($this->photo());
            $this->fail('Une exception était attendue.');
        } catch (AnalyseImageException $exception) {
            $this->assertStringContainsString('clé', $exception->getMessage());
            $this->assertStringNotContainsString('clef-gemini', $exception->getMessage());
        }
    }

    public function test_openai_est_utilise_quand_il_est_le_fournisseur_configure(): void
    {
        config([
            'services.vision.provider' => 'openai',
            'services.vision.openai_key' => 'clef-openai',
            'services.vision.gemini_key' => '',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'est_vetement' => true,
                            'type' => 'Robe',
                            'matiere' => 'Soie',
                            'etat' => 'Excellent',
                            'defauts' => [],
                            'gravite' => 'aucune',
                            'reparable' => false,
                            'confiance' => 92,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $resultat = (new AnalyseImageVetementService())->analyser($this->photo());

        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_INUTILE, $resultat->toArray()['verdict']);
        $this->assertFalse($resultat->toArray()['deuxieme_chance']);
    }

    public function test_un_modele_gemini_retire_bascule_vers_le_modele_courant(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'not found']], 404)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'est_vetement' => true,
                                    'type' => 'Veste',
                                    'matiere' => 'Coton',
                                    'etat' => 'À réparer',
                                    'defauts' => ['Poche décousue'],
                                    'gravite' => 'legere',
                                    'reparable' => true,
                                    'confiance' => 80,
                                ], JSON_UNESCAPED_UNICODE),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $resultat = (new AnalyseImageVetementService())->analyser($this->photo());

        $this->assertTrue($resultat->toArray()['deuxieme_chance']);
        $this->assertSame('gemini-3.5-flash-lite', $resultat->toArray()['modele']);
        Http::assertSentCount(2);
    }

    public function test_un_modele_gemini_sature_bascule_vers_le_modele_courant(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'unavailable']], 503)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'est_vetement' => true,
                                    'type' => 'Pull',
                                    'matiere' => 'Laine',
                                    'etat' => 'À réparer',
                                    'defauts' => ['Couture lâchée'],
                                    'gravite' => 'legere',
                                    'reparable' => true,
                                    'confiance' => 77,
                                ], JSON_UNESCAPED_UNICODE),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $resultat = (new AnalyseImageVetementService())->analyser($this->photo());

        $this->assertSame('gemini-3.5-flash-lite', $resultat->toArray()['modele']);
        Http::assertSentCount(2);
    }

    public function test_un_modele_gemini_muet_bascule_vers_le_modele_suivant(): void
    {
        config(['services.vision.gemini_model' => 'gemini-flash-latest']);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'gemini-flash-latest:generateContent')) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'est_vetement' => true,
                                'type' => 'Veste',
                                'matiere' => 'Coton',
                                'etat' => 'À réparer',
                                'defauts' => ['Poche décousue'],
                                'gravite' => 'legere',
                                'reparable' => true,
                                'confiance' => 80,
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ]);
        });

        $resultat = (new AnalyseImageVetementService())->analyser($this->photo());

        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_REPARABLE, $resultat->toArray()['verdict']);
        $this->assertSame('gemini-3.5-flash-lite', $resultat->toArray()['modele']);
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
