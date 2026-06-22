<?php

declare(strict_types=1);

namespace Verstka\Sdk\Integration\Symfony\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Verstka\Sdk\Exception\VerstkaError;
use Verstka\Sdk\Integration\CallbackDispatcher;

final class VerstkaExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof VerstkaError) {
            return;
        }

        $mapped = CallbackDispatcher::mapException($exception);
        $event->setResponse(new JsonResponse($mapped->toArray(), $mapped->status));
    }
}
