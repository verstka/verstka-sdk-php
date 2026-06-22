<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Symfony;

use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;
use Verstka\Sdk\Finalize\FontsPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;

final class VerstkaCallbacks
{
    /**
     * @param callable(ContentFinalizeContext): ContentFinalizeResult|null $onContentFinalize
     * @param callable(FontsFinalizeContext): FontsFinalizeResult|null $onFontsFinalize
     * @param callable(ContentPreSaveContext): PreSaveDecision|null $onContentPreSave
     * @param callable(FontsPreSaveContext): PreSaveDecision|null $onFontsPreSave
     */
    public function __construct(
        public readonly mixed $onContentFinalize = null,
        public readonly mixed $onFontsFinalize = null,
        public readonly mixed $onContentPreSave = null,
        public readonly mixed $onFontsPreSave = null,
    ) {
    }
}
