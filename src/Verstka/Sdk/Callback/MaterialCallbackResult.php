<?php

declare(strict_types=1);

namespace Verstka\Sdk\Callback;

final class MaterialCallbackResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $data,
    ) {
    }

    /**
     * @return array{rc: int, rm: string, data: array<string, mixed>}
     */
    public function toResponse(): array
    {
        return [
            'rc' => $this->success ? 1 : 0,
            'rm' => $this->message,
            'data' => $this->data,
        ];
    }
}
