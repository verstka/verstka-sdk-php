<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class FontsFinalizeResult
{
    /**
     * @param array<string, mixed>|null $fonts
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?array $fonts = null,
    ) {
    }
}
