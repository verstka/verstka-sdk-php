<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class ContentPreSaveContext
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $materialId,
        public readonly array $metadata,
        public readonly string $contentUrl,
    ) {
    }
}
