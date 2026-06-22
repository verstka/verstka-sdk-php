<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class ContentFinalizeResult
{
    /**
     * @param array<string, mixed>|null $vmsJson
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?array $vmsJson = null,
    ) {
    }
}
