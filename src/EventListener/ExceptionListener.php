<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private const RESOURCE_NOT_FOUND_MSG = 'Resource with such id does not exists';
    private const INTERNAL_ERROR_MSG = 'Internal server error';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
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
            $statusCode = $exception->getStatusCode();

            // Never expose an internal exception message: for anything other than a
            // validation failure the client only gets the standard reason phrase.
            $message = match ($statusCode) {
                Response::HTTP_NOT_FOUND => self::RESOURCE_NOT_FOUND_MSG,
                Response::HTTP_UNPROCESSABLE_ENTITY => $exception->getPrevious()?->getMessage()
                    ?? self::statusText($statusCode),
                default => self::statusText($statusCode),
            };

            // Keep the exception's own headers so WWW-Authenticate (401) and Allow (405)
            // still reach the client.
            $response = new JsonResponse(
                ['error' => $message],
                $statusCode,
                $exception->getHeaders()
            );
        } else {
            $response = new JsonResponse(
                ['error' => self::INTERNAL_ERROR_MSG],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $event->setResponse($response);
    }

    private static function statusText(int $statusCode): string
    {
        return Response::$statusTexts[$statusCode] ?? self::INTERNAL_ERROR_MSG;
    }
}
