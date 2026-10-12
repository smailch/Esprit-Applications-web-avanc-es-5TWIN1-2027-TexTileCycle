<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Exceptions\DiagnosticIndisponibleException;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Diagnostic par photo : le microservice Python est simulé (Http::fake), le catalogue vient
 * d'un AtelierService dont seule la lecture Mongo est simulée. Aucun accès réseau ni base.
 */
class DiagnosticPhotoTest extends TestCase
{
    use BlocksRemoteMongo;

    private const JETON = 'jeton-de-test-assez-long';

    private const URL = 'http://127.0.0.1:8001/diagnostic';

    /** PNG valide de 1 x 1 pixel (l'extension GD n'est pas requise). */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const ID_PLUS = '652f000000000000000000a1';

    private const ID_VERT = '652f000000000000000000a2';

    private const ID_SERVICE_PLUS = '652f000000000000000000b1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        config([
            'ateliers_diagnostic.url' => 'http://127.0.0.1:8001',
            'ateliers_diagnostic.jeton' => self::JETON,
            'ateliers_diagnostic.par_minute' => 10,
        ]);
    }

    private function atelier(string $id, string $nom, float $note, array $services, ?array $coordonnees = null): Atelier
    {
        $atelier = new Atelier([
            'nom' => $nom, 'ville' => 'La Marsa', 'adresse' => '45 Avenue Habib Bourguiba', 'telephone' => '+216 71 774 210',
            'note_moyenne' => $note, 'nb_avis' => 12, 'statut' => Atelier::STATUT_ACTIF, 'horaires' => [],
            'latitude' => $coordonnees[0] ?? null, 'longitude' => $coordonnees[1] ?? null,
        ]);
        $atelier->setAttribute('_id', $id);

        $modeles = [];
        foreach ($services as [$idService, $nomService, $prix]) {
            $service = new Service(['nom' => $nomService, 'description' => 'Réparer un trou ou une déchirure.', 'prix_estime' => $prix, 'duree_estimee' => 30]);
            $service->setAttribute('_id', $idService);
            $service->atelier_id = $id;
            $modeles[] = $service;
        }

        return $atelier->setRelation('services', new Collection($modeles));
    }

    /** Quatre ateliers proposent la même réparation (noms écrits différemment) : elle doit être regroupée. */
    private function mockCatalogue(?Collection $ateliers = null): void
    {
        $ateliers ??= new Collection([
            $this->atelier(self::ID_PLUS, 'Couture <script>alert(1)</script> Plus', 4.9, [
                [self::ID_SERVICE_PLUS, 'Recoudre une déchirure', 15], ['652f000000000000000000b2', 'Retouche denim', 20],
            ]),
            $this->atelier(self::ID_VERT, "L'Atelier Vert", 4.6, [
                ['652f000000000000000000b3', 'recoudre une  Déchirure', 12], ['652f000000000000000000b4', 'Changer une fermeture', 18],
            ]),
            $this->atelier('652f000000000000000000a3', 'Fil & Aiguille', 4.2, [['652f000000000000000000b5', 'Recoudre une déchirure', 10]]),
            $this->atelier('652f000000000000000000a4', 'Atelier Quatrième', 3.1, [['652f000000000000000000b6', 'Recoudre une déchirure', 8]]),
        ]);

        $service = Mockery::mock(AtelierService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('fetchActifs')->andReturn($ateliers);
        $this->app->instance(AtelierService::class, $service);
    }

    private function reponseIa(array $ids, bool $certain = true): array
    {
        return [
            'vetements' => [['cle' => 'jean', 'probabilite' => 0.8]],
            'defauts' => [['cle' => 'déchirure', 'probabilite' => 0.45], ['cle' => 'trou', 'probabilite' => 0.3], ['cle' => 'tache', 'probabilite' => 0.1]],
            'services' => array_map(fn (string $id) => ['id' => $id, 'score' => 0.4], $ids),
            'certain' => $certain,
            'duree_ms' => 600,
            'version' => 'clip-vit-b-32/v5',
        ];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('jean.png', base64_decode(self::PNG));
    }

    private function partie(Request $requete, string $nom): ?string
    {
        foreach ($requete->data() as $partie) {
            if (($partie['name'] ?? null) === $nom) {
                return (string) $partie['contents'];
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------- Formulaire */

    public function test_le_formulaire_s_affiche_sans_controle_de_saisie_html(): void
    {
        $html = $this->get('/ateliers/diagnostic')
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Diagnostiquer mon vêtement')
            ->assertSee("La photo sert uniquement à l'analyse, elle n'est pas enregistrée.", false)
            ->assertSee('js/diagnostic.js', false)
            // Bouton « Utiliser ma position » et sa carte de contrôle (affichés par le script), Leaflet chargé.
            ->assertSee('data-position-bouton', false)
            ->assertSee('data-position-carte', false)
            ->assertSee('leaflet@1.9.4/dist/leaflet.js', false)
            ->getContent();

        $this->assertSame(1, preg_match('/<form[^>]*diagnostic-form[^>]*>(.*?)<\/form>/s', $html, $formulaire));
        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|accept|pattern)(=|\s|>)/', $formulaire[1]);
    }

    public function test_la_photo_et_la_precision_sont_controlees_en_php_sans_appeler_l_ia(): void
    {
        Http::fake();
        $this->mockCatalogue();

        $this->from('/ateliers/diagnostic')->post('/ateliers/diagnostic', [])
            ->assertRedirect('/ateliers/diagnostic')
            ->assertSessionHasErrors(['photo' => 'Ajoutez une photo du vêtement.']);

        $this->post('/ateliers/diagnostic', ['photo' => UploadedFile::fake()->createWithContent('notes.pdf', '%PDF-1.4 pas une image')])
            ->assertSessionHasErrors(['photo' => 'La photo doit être une image JPG, PNG ou WebP.']);

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'texte' => str_repeat('a', 301)])
            ->assertSessionHasErrors(['texte' => 'La précision ne doit pas dépasser 300 caractères.']);

        Http::assertNothingSent();
    }

    /* ---------------------------------------------------------------- Diagnostic réussi */

    public function test_le_diagnostic_envoie_le_catalogue_regroupe_et_affiche_les_ateliers(): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response($this->reponseIa(['s0', 's2', 's1']))]);

        $reponse = $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'texte' => 'déchiré au genou']);

        $reponse->assertOk()
            // Sans JavaScript : l'écran de saisie est masqué et le résultat rendu à sa place.
            ->assertSee('data-ecran-saisie  hidden', false)
            ->assertSeeInOrder(['Ce que l&#039;IA voit', 'Déchirure', 'Trou', 'Jean'], false)
            // La photo et « Ce n'est pas ça ? » ne sont affichés que par le script.
            ->assertSee('data-resultat-photo hidden', false)
            ->assertSee('data-affiner hidden', false)
            ->assertSeeInOrder(['Recoudre une déchirure', 'Changer une fermeture', 'Retouche denim'])
            // Les 3 ateliers les mieux notés pour la réparation regroupée, puis le lien vers les 4.
            ->assertSeeInOrder(['Couture &lt;script&gt;alert(1)&lt;/script&gt; Plus', "L&#039;Atelier Vert", 'Fil &amp; Aiguille'], false)
            ->assertSee('Voir les 4 ateliers qui proposent ce service')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(e(route('front.rdv.create', ['atelier_id' => self::ID_PLUS, 'service_id' => self::ID_SERVICE_PLUS])), false)
            ->assertSee('dès <strong>8 TND</strong>', false)
            ->assertSee('diag-reparation est-principale', false)
            ->assertSee('déchiré au genou')
            ->assertDontSee("L'IA hésite", false);

        Http::assertSent(function (Request $requete) {
            $services = json_decode($this->partie($requete, 'services'), true);

            return $requete->url() === self::URL
                && $requete->hasHeader('Authorization', 'Bearer '.self::JETON)
                && $requete->isMultipart()
                && $this->partie($requete, 'photo') === base64_decode(self::PNG)
                && $this->partie($requete, 'texte') === 'déchiré au genou'
                // 4 services « Recoudre une déchirure » regroupés en un seul ; 3 réparations au total.
                && array_column($services, 'id') === ['s0', 's1', 's2']
                && $services[0]['nom'] === 'Recoudre une déchirure'
                // Aucune donnée d'atelier ni personnelle dans le catalogue envoyé.
                && array_keys($services[0]) === ['id', 'nom', 'description'];
        });
    }

    public function test_un_diagnostic_incertain_est_signale_et_un_id_inconnu_ignore(): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response($this->reponseIa(['s9', 's1'], false))]);

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])
            ->assertOk()
            ->assertSee("L'IA hésite", false)
            ->assertSee('Retouche denim');

        Http::assertSent(fn (Request $requete) => $this->partie($requete, 'texte') === null);
    }

    /* ---------------------------------------------------------------- Envoi sans rechargement (JavaScript) */

    private function enAjax(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_sans_rechargement_le_resultat_est_renvoye_en_json(): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response($this->reponseIa(['s0', 's2', 's1']))]);

        $html = $this->post('/ateliers/diagnostic', ['photo' => $this->photo()], $this->enAjax())
            ->assertOk()
            ->assertJsonStructure(['html'])
            ->json('html');

        $this->assertStringContainsString('data-resultat', $html);
        $this->assertStringContainsString('Recoudre une déchirure', $html);
        $this->assertStringContainsString('data-defaut="fermeture cassée"', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<html', $html);
    }

    public function test_sans_rechargement_les_erreurs_php_sont_renvoyees_en_json(): void
    {
        $this->mockCatalogue();
        Http::fake();

        $this->post('/ateliers/diagnostic', ['texte' => str_repeat('a', 301)], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'photo' => 'Ajoutez une photo du vêtement.',
                'texte' => 'La précision ne doit pas dépasser 300 caractères.',
            ]);

        Http::assertNothingSent();
    }

    public function test_sans_rechargement_service_indisponible_message_en_json(): void
    {
        $this->mockCatalogue();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()], $this->enAjax())
            ->assertStatus(503)
            ->assertExactJson(['message' => DiagnosticIndisponibleException::MESSAGE_INDISPONIBLE]);
    }

    /* ---------------------------------------------------------------- « Ce n'est pas ça ? » */

    public function test_le_defaut_choisi_par_le_citoyen_est_transmis_et_affiche(): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response($this->reponseIa(['s2', 's0']))]);

        $html = $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'defaut' => 'fermeture cassée'], $this->enAjax())
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Défaut que vous avez indiqué', $html);
        $this->assertMatchesRegularExpression('/data-defaut="fermeture cassée" aria-pressed="true"/', $html);
        Http::assertSent(fn (Request $requete) => $this->partie($requete, 'defaut') === 'fermeture cassée');
    }

    public function test_un_defaut_hors_liste_est_refuse_en_php(): void
    {
        $this->mockCatalogue();
        Http::fake();

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'defaut' => 'explosion'], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['defaut' => 'Le défaut choisi est invalide.']);

        Http::assertNothingSent();
    }

    /* ---------------------------------------------------------------- Recommandation et position */

    public function test_la_position_est_controlee_en_php(): void
    {
        $this->mockCatalogue();
        Http::fake();

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'lat' => '36.8'], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lng' => 'La position transmise est incomplète.']);

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'lat' => '100', 'lng' => '10'], $this->enAjax())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat' => 'La position transmise est invalide.']);

        Http::assertNothingSent();
    }

    public function test_avec_la_position_l_atelier_le_plus_proche_passe_en_tete_avec_sa_raison(): void
    {
        $this->mockCatalogue(new Collection([
            $this->atelier(self::ID_PLUS, 'Couture Plus', 4.9, [[self::ID_SERVICE_PLUS, 'Recoudre une déchirure', 15]], [36.8782, 10.3247]),
            $this->atelier(self::ID_VERT, 'Fil Tunis Centre', 4.4, [['652f000000000000000000b3', 'Recoudre une déchirure', 12]], [36.8066, 10.1816]),
        ]));
        Http::fake([self::URL => Http::response($this->reponseIa(['s0']))]);

        $html = $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'lat' => '36.8065', 'lng' => '10.1815'], $this->enAjax())
            ->assertOk()
            ->json('html');

        $this->assertMatchesRegularExpression('/Fil Tunis Centre.*Le plus proche.*à 0,0 km.*Couture Plus.*Le mieux noté.*à 1[0-9],[0-9] km/s', $html);
    }

    public function test_sans_position_le_mieux_note_passe_en_tete_sans_distance(): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response($this->reponseIa(['s0']))]);

        $html = $this->post('/ateliers/diagnostic', ['photo' => $this->photo()], $this->enAjax())->assertOk()->json('html');

        $this->assertStringNotContainsString(' km', $html);
        $this->assertMatchesRegularExpression('/Couture &lt;script&gt;alert\(1\)&lt;\/script&gt; Plus.*Le mieux noté/s', $html);
    }

    /* ---------------------------------------------------------------- Pannes */

    public function test_service_injoignable_message_clair_et_saisie_conservee(): void
    {
        $this->mockCatalogue();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo(), 'texte' => 'trou au genou'])
            ->assertRedirect(route('front.ateliers.diagnostic'))
            ->assertSessionHas('diagnostic_erreur', DiagnosticIndisponibleException::MESSAGE_INDISPONIBLE);

        $this->get('/ateliers/diagnostic')
            ->assertSee('Le diagnostic par photo est indisponible pour le moment.')
            ->assertSee('trou au genou');
    }

    public function test_photo_refusee_par_le_service_sans_afficher_son_texte(): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response(['detail' => '<b>texte interne du service</b>'], 422)]);

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])
            ->assertSessionHas('diagnostic_erreur', "La photo n'a pas pu être analysée : le fichier n'est pas une image lisible.");
    }

    /**
     * @dataProvider reponsesInattendues
     */
    public function test_reponse_inattendue_du_service(int $statut, mixed $corps): void
    {
        $this->mockCatalogue();
        Http::fake([self::URL => Http::response($corps, $statut)]);

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])
            ->assertSessionHas('diagnostic_erreur', DiagnosticIndisponibleException::MESSAGE_INDISPONIBLE);
    }

    public static function reponsesInattendues(): array
    {
        return [
            'jeton refusé' => [401, ['detail' => 'Jeton manquant ou invalide.']],
            'modèle en chargement' => [503, ['detail' => 'Le modèle n\'est pas encore chargé.']],
            'erreur serveur' => [500, 'Internal Server Error'],
            'JSON incomplet' => [200, ['vetements' => []]],
        ];
    }

    public function test_sans_jeton_configure_aucun_appel(): void
    {
        $this->mockCatalogue();
        config(['ateliers_diagnostic.jeton' => null]);
        Http::fake();

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])
            ->assertSessionHas('diagnostic_erreur', DiagnosticIndisponibleException::MESSAGE_INDISPONIBLE);

        Http::assertNothingSent();
    }

    public function test_catalogue_vide_aucun_appel(): void
    {
        $this->mockCatalogue(new Collection());
        Http::fake();

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])
            ->assertSessionHas('diagnostic_erreur', DiagnosticIndisponibleException::catalogueVide()->getMessage());

        Http::assertNothingSent();
    }

    public function test_les_analyses_sont_limitees_par_minute(): void
    {
        $this->mockCatalogue();
        config(['ateliers_diagnostic.par_minute' => 2]);
        Http::fake([self::URL => Http::response($this->reponseIa(['s0']))]);

        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])->assertOk();
        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])->assertOk();
        $this->post('/ateliers/diagnostic', ['photo' => $this->photo()])->assertStatus(429);

        Http::assertSentCount(2);
    }
}
