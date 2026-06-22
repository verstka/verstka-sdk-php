<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class ContentFinalizeContext
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $vmsJson
     * @param array<string, string> $savedMediaUrls
     */
    public function __construct(
        public readonly string $materialId,
        public readonly array $metadata,
        public readonly ?array $vmsJson,
        public readonly ?string $vmsHtml,
        public readonly array $savedMediaUrls = [],
    ) {
    }
}
