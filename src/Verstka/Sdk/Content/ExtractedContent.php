<?php

declare(strict_types=1);

namespace Verstka\Sdk\Content;

final class ExtractedContent
{
    /**
     * @param array<string, string> $media
     */
    public function __construct(
        public readonly array $media,
        public readonly ?string $vmsJson,
        public readonly ?string $vmsHtml,
        public readonly string $tempDir,
    ) {
    }
}
