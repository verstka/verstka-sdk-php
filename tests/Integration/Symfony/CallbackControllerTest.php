<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests\Integration\Symfony;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Integration\Symfony\Controller\CallbackController;
use Verstka\Sdk\Integration\Symfony\VerstkaCallbacks;
use Verstka\Sdk\Signature\SignatureService;
use Verstka\Sdk\Tests\RecordingMediaStorage;
use Verstka\Sdk\Tests\Support\TestConfig;
use Verstka\Sdk\Tests\Support\ZipFactory;

final class CallbackControllerTest extends TestCase
{
    public function testMaterialCallbackReturnsVerstkaResponse(): void
    {
        $contentUrl = 'https://verstka.test/download/abc';
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildContentZip($zipPath, media: ['hero.png' => 'img'], vmsJson: ['assets' => []]);
        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);

        $mock = new MockHandler([new Response(200, [], $zipBytes)]);
        $client = new VerstkaClient(TestConfig::make(), new Client(['handler' => HandlerStack::create($mock)]));
        $controller = new CallbackController(
            $client,
            new RecordingMediaStorage(),
            new VerstkaCallbacks(
                onContentFinalize: static fn (ContentFinalizeContext $ctx): ContentFinalizeResult => new ContentFinalizeResult(true),
            ),
        );

        $payload = json_encode([
            'material_id' => 'M1',
            'content_url' => $contentUrl,
            'metadata' => [],
        ], JSON_THROW_ON_ERROR);

        $request = Request::create(
            '/verstka/callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_VERSTKA_SIGNATURE' => SignatureService::signMaterial('M1', $contentUrl, TestConfig::API_SECRET)],
            $payload,
        );

        $response = $controller($request);
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['rc']);
    }
}
