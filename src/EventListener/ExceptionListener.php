<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns every exception into the API's JSON error. It does not log: it runs after
 * Symfony's ErrorListener (see config/services.yaml), which logs each exception once,
 * at the levels set under framework.exceptions (config/packages/framework.yaml).
 */
class ExceptionListener
{
    private const RESOURCE_NOT_FOUND_MSG = 'Resource with such id does not exists';
    private const INTERNAL_ERROR_MSG = 'Internal server error';

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();

            // A request payload that failed validation (MapRequestPayload): one line per
            // violation, and the same as a list a client can map to its form fields.
            $previous = $exception->getPrevious();
            if (Response::HTTP_UNPROCESSABLE_ENTITY === $statusCode && $previous instanceof ValidationFailedException) {
                $violations = [];
                foreach ($previous->getViolations() as $violation) {
                    $violations[] = self::violation($violation);
                }

                $event->setResponse(new JsonResponse(
                    [
                        'error' => implode("\n", array_map(static fn (array $v): string => '' === $v['field'] ? $v['message'] : $v['field'].': '.$v['message'], $violations)),
                        'violations' => $violations,
                    ],
                    $statusCode,
                    $exception->getHeaders(),
                ));

                return;
            }

            // Never expose an internal exception message: for anything other than a
            // validation failure or a conflict the client only gets the standard reason phrase.
            $message = match ($statusCode) {
                Response::HTTP_NOT_FOUND => self::RESOURCE_NOT_FOUND_MSG,
                Response::HTTP_UNPROCESSABLE_ENTITY => $exception->getPrevious()?->getMessage()
                    ?? self::statusText($statusCode),
                // Conflicts are raised by the controllers with a message meant for the client.
                Response::HTTP_CONFLICT => $exception->getMessage(),
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

    /**
     * @return array{field: string, message: string}
     */
    private static function violation(ConstraintViolationInterface $violation): array
    {
        return [
            'field' => $violation->getPropertyPath(),
            'message' => (string) $violation->getMessage(),
        ];
    }

    private static function statusText(int $statusCode): string
    {
        return Response::$statusTexts[$statusCode] ?? self::INTERNAL_ERROR_MSG;
    }
}
