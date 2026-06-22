<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Symfony\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;
use Verstka\Sdk\Finalize\FontsPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;
use Verstka\Sdk\Integration\CallbackDispatcher;
use Verstka\Sdk\Integration\Symfony\VerstkaCallbacks;
use Verstka\Sdk\Storage\StorageAdapter;

final class CallbackController
{
    public function __construct(
        private readonly VerstkaClient $client,
        private readonly StorageAdapter $storage,
        private readonly ?VerstkaCallbacks $callbacks = null,
    ) {
    }

    #[Route('/callback', name: 'verstka_callback', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $signature = trim((string) $request->headers->get('X-Verstka-Signature', ''));

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

        return new JsonResponse($response, Response::HTTP_OK);
    }
}
