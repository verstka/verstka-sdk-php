<?php

declare(strict_types=1);

namespace Verstka\Sdk\Callback;

final class FontsCallbackResult
{
    /**
     * @param array<string, mixed> $fonts
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $fonts,
    ) {
    }

    /**
     * @return array{rc: int, rm: string, data: array{fonts: array<string, mixed>}}
     */
    public function toResponse(): array
    {
        return [
            'rc' => $this->success ? 1 : 0,
            'rm' => $this->message,
            'data' => ['fonts' => $this->fonts],
        ];
    }
}
