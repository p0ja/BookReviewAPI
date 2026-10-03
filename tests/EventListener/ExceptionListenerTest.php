<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ExceptionListenerTest extends TestCase
{
    public function testNotFoundUsesTheGenericResourceMessage(): void
    {
        $response = $this->handle(new NotFoundHttpException('Book 42 missing in table book'));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(['error' => 'Resource with such id does not exists'], $this->decode($response));
    }

    public function testValidationFailureExposesTheViolations(): void
    {
        $violations = new \RuntimeException('isbn: This value is too short.');

        $response = $this->handle(new UnprocessableEntityHttpException('ignored', $violations));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame(['error' => 'isbn: This value is too short.'], $this->decode($response));
    }

    /**
     * The validator's own text names the DTO class and the constraint codes; the client
     * gets the field and the message only.
     */
    public function testAnInvalidPayloadListsEachViolationByField(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This field is required.', null, [], new \stdClass(), 'isbn', null, null, 'some-code'),
            new ConstraintViolation('Price must be a decimal number, e.g. 29.99.', null, [], new \stdClass(), 'price', 'abc'),
        ]);

        $response = $this->handle(new UnprocessableEntityHttpException('ignored', new ValidationFailedException(new \stdClass(), $violations)));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame([
            'error' => "isbn: This field is required.\nprice: Price must be a decimal number, e.g. 29.99.",
            'violations' => [
                ['field' => 'isbn', 'message' => 'This field is required.'],
                ['field' => 'price', 'message' => 'Price must be a decimal number, e.g. 29.99.'],
            ],
        ], $this->decode($response));
    }

    public function testValidationFailureWithoutDetailsFallsBackToTheReasonPhrase(): void
    {
        $response = $this->handle(new UnprocessableEntityHttpException('internal detail'));

        self::assertSame(['error' => 'Unprocessable Content'], $this->decode($response));
    }

    public function testConflictExposesItsMessage(): void
    {
        $response = $this->handle(new ConflictHttpException('A book with this ISBN already exists'));

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame(['error' => 'A book with this ISBN already exists'], $this->decode($response));
    }

    public function testOtherHttpErrorsKeepTheirStatusAndHeadersButNotTheirMessage(): void
    {
        $response = $this->handle(new MethodNotAllowedHttpException(['GET', 'POST'], 'internal detail'));

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertSame('GET, POST', $response->headers->get('Allow'));
        self::assertSame(['error' => 'Method Not Allowed'], $this->decode($response));
    }

    public function testUnauthorizedKeepsTheAuthenticateChallenge(): void
    {
        $response = $this->handle(new UnauthorizedHttpException('Bearer', 'JWT Token not found'));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
    }

    public function testNonHttpExceptionIsAnOpaqueServerError(): void
    {
        $response = $this->handle(new \LogicException('SQLSTATE[23505]: duplicate key'));

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame(['error' => 'Internal server error'], $this->decode($response));
    }

    private function handle(\Throwable $exception): Response
    {
        $event = new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create('/books'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        (new ExceptionListener())->onKernelException($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
