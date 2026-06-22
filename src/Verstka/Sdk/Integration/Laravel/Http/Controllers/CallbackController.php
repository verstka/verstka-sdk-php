<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;
use Verstka\Sdk\Finalize\FontsPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;
use Verstka\Sdk\Integration\CallbackDispatcher;
use Verstka\Sdk\Integration\Laravel\VerstkaCallbacks;
use Verstka\Sdk\Storage\StorageAdapter;

final class CallbackController
{
    public function __construct(
        private readonly VerstkaClient $client,
        private readonly StorageAdapter $storage,
        private readonly ?VerstkaCallbacks $callbacks = null,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (!is_array($payload)) {
            $payload = [];
        }

        $signature = trim((string) $request->header('X-Verstka-Signature', ''));

        $response = CallbackDispatcher::dispatch(
            $this->client,
            $payload,
            $signature,
            $this->storage,
            $this->callbacks?->onContentFinalize ?? static fn (ContentFinalizeContext $ctx): ContentFinalizeResult => new ContentFinalizeResult(true),
            $this->callbacks?->onFontsFinalize,
            $this->callbacks?->onContentPreSave,
            $this->callbacks?->onFontsPreSave,
        );

        return response()->json($response);
    }
}
