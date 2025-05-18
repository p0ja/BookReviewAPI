<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private const RESOURCE_NOT_FOUND_MSG = 'Resource with such id does not exists';
    private const INTERNAL_ERROR_MSG = 'Resource could not be found';

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface &&
            $exception->getStatusCode() === Response::HTTP_NOT_FOUND
        ) {
            $response = new Response(
                self::RESOURCE_NOT_FOUND_MSG,
                Response::HTTP_NOT_FOUND
            );
        } else {
            $response = new Response(
                self::INTERNAL_ERROR_MSG,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $event->setResponse($response);
    }
}
