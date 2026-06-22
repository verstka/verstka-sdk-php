<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Laravel;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Verstka\Sdk\Exception\VerstkaError;
use Verstka\Sdk\Integration\CallbackDispatcher;

final class VerstkaExceptionRenderer
{
    public static function render(\Throwable $exception, Request $request): ?Response
    {
        if (!$exception instanceof VerstkaError) {
            return null;
        }

        $mapped = CallbackDispatcher::mapException($exception);

        return response()->json($mapped->toArray(), $mapped->status);
    }
}
