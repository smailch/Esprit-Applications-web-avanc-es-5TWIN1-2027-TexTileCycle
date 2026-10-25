<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Services\PasseportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Passeports de réparation : page publique (ouverte par le QR code, sans connexion), image du QR,
 * et page « Mes passeports » du client connecté. Les pages ne sont pas indexées par les moteurs de recherche.
 */
class PasseportController extends Controller
{
    public function __construct(private PasseportService $passeports)
    {
    }

    public function show(string $code)
    {
        $passeport = $this->passeports->trouverParCode($code);
        abort_unless($passeport, 404);

        $historique = $passeport->estRevoque() ? collect() : $this->passeports->historique($passeport);

        $response = response()->view('front.passeport', [
            'passeport' => $passeport,
            'historique' => $historique,
            'ateliers' => $this->passeports->ateliersDe($historique),
        ]);

        return $this->nonIndexe($response);
    }

    public function qr(string $code)
    {
        $passeport = $this->passeports->trouverParCode($code);
        abort_unless($passeport, 404);

        return $this->nonIndexe(response($this->passeports->qrSvg($passeport), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]));
    }

    public function mesPasseports(Request $request)
    {
        return $this->nonIndexe(response()->view('front.mes-passeports', [
            'passeports' => $this->passeports->pourClient($request->user()),
        ]));
    }

    private function nonIndexe(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
