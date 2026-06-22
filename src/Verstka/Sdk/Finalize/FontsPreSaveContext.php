<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class FontsPreSaveContext
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $fonts
     */
    public function __construct(
        public readonly string $materialId,
        public readonly array $metadata,
        public readonly string $contentUrl,
        public readonly array $fonts,
    ) {
    }
}
