<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class FontsFinalizeContext
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $fonts
     * @param array<string, string> $savedFontUrls
     */
    public function __construct(
        public readonly string $materialId,
        public readonly array $metadata,
        public readonly array $fonts,
        public readonly ?string $cssUrl,
        public readonly ?string $jsonUrl,
        public readonly array $savedFontUrls = [],
    ) {
    }
}
