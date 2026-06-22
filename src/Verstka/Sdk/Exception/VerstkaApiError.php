<?php

declare(strict_types=1);

namespace Verstka\Sdk\Exception;

class VerstkaApiError extends VerstkaError
{
    public const DEFAULT_MESSAGE = 'Verstka API error';

    public function __construct(?string $message = null, public readonly ?int $statusCode = null)
    {
        parent::__construct($message);
    }
}
