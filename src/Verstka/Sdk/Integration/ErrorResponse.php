<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration;

final class ErrorResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $code,
        public readonly string $message,
    ) {
    }

    /**
     * @return array{error: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'error' => $this->code,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
