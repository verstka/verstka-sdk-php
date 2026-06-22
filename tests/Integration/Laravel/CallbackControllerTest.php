<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests\Integration\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Config\VerstkaConfig;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Integration\Laravel\Http\Controllers\CallbackController;
use Verstka\Sdk\Integration\Laravel\VerstkaCallbacks;
use Verstka\Sdk\Integration\Laravel\VerstkaServiceProvider;
use Verstka\Sdk\Signature\SignatureService;
use Verstka\Sdk\Storage\StorageAdapter;
use Verstka\Sdk\Tests\Support\TestConfig;
use Verstka\Sdk\Tests\Support\ZipFactory;

final class CallbackControllerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [VerstkaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('verstka.api_key', TestConfig::API_KEY);
        $app['config']->set('verstka.api_secret', TestConfig::API_SECRET);
        $app['config']->set('verstka.callback_url', TestConfig::CALLBACK_URL);
        $app['config']->set('verstka.api_url', 'https://verstka.test/api/v2');
    }

    public function testMaterialCallbackReturnsVerstkaResponse(): void
    {
        $contentUrl = 'https://verstka.test/download/abc';
        $zipPath = sys_get_temp_dir() . '/verstka_test_' . uniqid('', true) . '.zip';
        ZipFactory::buildContentZip($zipPath, media: ['hero.png' => 'img'], vmsJson: ['assets' => []]);
        $zipBytes = file_get_contents($zipPath);
        @unlink($zipPath);

        $mock = new MockHandler([new Response(200, [], $zipBytes)]);
        $this->app->instance(VerstkaClient::class, new VerstkaClient(
            $this->app->make(VerstkaConfig::class),
            new Client(['handler' => HandlerStack::create($mock)]),
        ));
        $this->app->instance(StorageAdapter::class, new LaravelRecordingMediaStorage());
        $this->app->instance(VerstkaCallbacks::class, new VerstkaCallbacks(
            onContentFinalize: static fn (ContentFinalizeContext $ctx): ContentFinalizeResult => new ContentFinalizeResult(true),
        ));
        $this->app->instance(CallbackController::class, new CallbackController(
            $this->app->make(VerstkaClient::class),
            $this->app->make(StorageAdapter::class),
            $this->app->make(VerstkaCallbacks::class),
        ));

        $request = Request::create(
            '/verstka/callback',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_VERSTKA_SIGNATURE' => SignatureService::signMaterial('M1', $contentUrl, TestConfig::API_SECRET)],
            json_encode([
                'material_id' => 'M1',
                'content_url' => $contentUrl,
                'metadata' => [],
            ], JSON_THROW_ON_ERROR),
        );

        $response = $this->app->make(CallbackController::class)($request);
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['rc']);
    }
}

final class LaravelRecordingMediaStorage implements StorageAdapter
{
    public function saveMedia(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        return 'https://cdn.test/' . $materialId . '/' . $filename;
    }

    public function saveFontFile(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        return 'https://cdn.test/fonts/' . $filename;
    }

    public function saveFontsManifest(string $filename, string $tempPath, string $materialId, array $metadata): string
    {
        return 'https://cdn.test/fonts/' . $filename;
    }
}
