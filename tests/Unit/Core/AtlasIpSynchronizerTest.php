<?php

namespace Tests\Unit\Core;

use App\Modules\Core\Support\AtlasIpSynchronizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AtlasIpSynchronizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.atlas.project_id' => '64b000000000000000000001',
            'services.atlas.public_key' => 'pub',
            'services.atlas.private_key' => 'priv',
            'services.atlas.sync' => true,
        ]);
    }

    public function test_remplace_l_ip_automatique_precedente(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.ipify.org')) {
                return Http::response('203.0.113.10', 200);
            }

            if ($request->method() === 'GET') {
                return Http::response([
                    'results' => [
                        [
                            'ipAddress' => '198.51.100.8',
                            'cidrBlock' => '198.51.100.8/32',
                            'comment' => AtlasIpSynchronizer::COMMENT,
                        ],
                        [
                            'ipAddress' => '192.0.2.5',
                            'cidrBlock' => '192.0.2.5/32',
                            'comment' => 'bureau',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], $request->method() === 'POST' ? 201 : 204);
        });

        $ip = app(AtlasIpSynchronizer::class)->sync();

        $this->assertSame('203.0.113.10', $ip);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && ($request->data()[0]['ipAddress'] ?? null) === '203.0.113.10'
                && ($request->data()[0]['comment'] ?? null) === AtlasIpSynchronizer::COMMENT;
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '198.51.100.8');
        });

        Http::assertNotSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '192.0.2.5');
        });
    }

    public function test_ne_recrée_pas_une_ip_deja_autorisee(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.ipify.org')) {
                return Http::response('203.0.113.10', 200);
            }

            return Http::response([
                'results' => [
                    [
                        'ipAddress' => '203.0.113.10',
                        'cidrBlock' => '203.0.113.10/32',
                        'comment' => AtlasIpSynchronizer::COMMENT,
                    ],
                ],
            ], 200);
        });

        $this->assertSame('203.0.113.10', app(AtlasIpSynchronizer::class)->sync());

        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_cles_absentes(): void
    {
        config([
            'services.atlas.project_id' => '',
            'services.atlas.public_key' => '',
            'services.atlas.private_key' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clés Atlas manquantes');

        app(AtlasIpSynchronizer::class)->sync();
    }
}
