<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private const RESOURCE_NOT_FOUND_MSG = 'Resource with such id does not exists';
    private const INTERNAL_ERROR_MSG = 'Resource could not be found';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $this->logger->log(
            NamespaceEnum::REST_KERNEL->value,
            $exception->getMessage(),
            [
                'exception' => $exception,
            ],
            LogLevel::ERROR,
        );

        if ($exception instanceof HttpExceptionInterface) {

            match ($exception->getStatusCode()) {
                Response::HTTP_NOT_FOUND => $response = new Response(
                    self::RESOURCE_NOT_FOUND_MSG,
                    Response::HTTP_NOT_FOUND
                ),
                Response::HTTP_UNPROCESSABLE_ENTITY => $response = new Response(
                    $exception->getPrevious()?->getMessage(),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                ),
                default => $response = new Response(),
            };

        } else {
            $response = new Response(
                self::INTERNAL_ERROR_MSG,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $event->setResponse($response);
    }
}
