<?php

declare(strict_types=1);

namespace Verstka\Sdk\Config;

final class VerstkaConfig
{
    public const DEFAULT_API_URL = 'https://api.r2.verstka.org/integration';
    public const DEFAULT_MAX_CONTENT_SIZE = 104857600;

    public function __construct(
        public readonly string $apiKey,
        public readonly string $apiSecret,
        public readonly string $callbackUrl,
        public readonly string $apiUrl = self::DEFAULT_API_URL,
        public readonly ?string $basicAuthUser = null,
        public readonly ?string $basicAuthPassword = null,
        public readonly int $maxContentSize = self::DEFAULT_MAX_CONTENT_SIZE,
        public readonly float $requestTimeout = 60.0,
        public readonly float $downloadTimeout = 120.0,
        public readonly bool $debug = false,
    ) {
    }

    public function getSessionOpenUrl(): string
    {
        return rtrim($this->apiUrl, '/') . '/session/open';
    }
}
