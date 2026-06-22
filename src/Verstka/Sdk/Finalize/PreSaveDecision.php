<?php

declare(strict_types=1);

namespace Verstka\Sdk\Finalize;

final class PreSaveDecision
{
    public function __construct(
        public readonly bool $allow,
        public readonly ?string $reason = null,
    ) {
    }
}
