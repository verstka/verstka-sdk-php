<?php

declare(strict_types=1);

namespace Verstka\Sdk\Content;

final class ExtractedFonts
{
    /**
     * @param array<string, string> $fontFiles
     */
    public function __construct(
        public readonly array $fontFiles,
        public readonly ?string $vmsFontsJsonPath,
        public readonly ?string $vmsFontsCssPath,
        public readonly string $tempDir,
    ) {
    }
}
