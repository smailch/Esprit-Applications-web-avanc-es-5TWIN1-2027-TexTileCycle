<?php

namespace Tests\Unit\Vetements;

use App\Modules\Vetements\Services\PropositionStyleVetementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PropositionStyleVetementServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.vision.provider' => 'gemini',
            'services.vision.gemini_key' => 'clef-gemini',
            'services.vision.gemini_model' => 'gemini-3.5-flash-lite',
            'services.vision.openai_key' => '',
        ]);
    }

    public function test_une_photo_produit_un_style_meme_si_le_rendu_image_est_sature(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->reponseTexte(), 200)
                ->push(['error' => ['message' => 'quota']], 429)
                ->push(['error' => ['message' => 'quota']], 429),
        ]);

        $resultat = (new PropositionStyleVetementService())->proposer($this->photo());

        $this->assertTrue($resultat['est_vetement']);
        $this->assertSame('Veste', $resultat['type']);
        $this->assertSame('Veste atelier indigo', $resultat['titre']);
        $this->assertSame('#243E73', $resultat['couleurs'][0]);
        $this->assertNull($resultat['vue']);
        Http::assertSentCount(3);
    }

    public function test_un_rendu_image_est_ajoute_a_la_maquette(): void
    {
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->reponseTexte(), 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'inlineData' => ['mimeType' => 'image/png', 'data' => $png],
                        ]]],
                    ]],
                ], 200),
        ]);

        $service = new PropositionStyleVetementService();
        $resultat = $service->proposer($this->photo());
        $service->proposer($this->photo());

        $this->assertSame('data:image/png;base64,'.$png, $resultat['vue']);
        Http::assertSentCount(2);
    }

    public function test_une_photo_sans_vetement_ne_lance_pas_de_rendu(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => '{"est_vetement":false}',
                    ]]],
                ]],
            ]),
        ]);

        $resultat = (new PropositionStyleVetementService())->proposer($this->photo());

        $this->assertFalse($resultat['est_vetement']);
        Http::assertSentCount(1);
    }

    private function reponseTexte(): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'est_vetement' => true,
                        'type' => 'Veste',
                        'titre' => 'Veste atelier indigo',
                        'style' => 'Reprise visible',
                        'description' => 'L\'ourlet est repris en fil doré. Une pièce écrue couvre l\'accroc.',
                        'couleurs' => ['#243e73', 'jaune', '#F3EDE2'],
                        'gestes' => ['Surpiquer l\'ourlet', 'Poser la pièce écrue'],
                    ], JSON_UNESCAPED_UNICODE),
                ]]],
            ]],
        ];
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
