<?php

declare(strict_types=1);

namespace Verstka\Sdk\Tests\Support;

use Verstka\Sdk\Config\VerstkaConfig;

final class TestConfig
{
    public const API_SECRET = 'test-secret';
    public const API_KEY = 'test-api-key';
    public const CALLBACK_URL = 'https://app.example.com/verstka/callback';

    public static function make(bool $debug = false): VerstkaConfig
    {
        return new VerstkaConfig(
            apiKey: self::API_KEY,
            apiSecret: self::API_SECRET,
            callbackUrl: self::CALLBACK_URL,
            apiUrl: 'https://verstka.test/api/v2',
            maxContentSize: 1024 * 1024,
            requestTimeout: 5.0,
            downloadTimeout: 5.0,
            debug: $debug,
        );
    }
}
