<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ExceptionListener;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

    public function testValidationFailureWithoutDetailsFallsBackToTheReasonPhrase(): void
    {
        $response = $this->handle(new UnprocessableEntityHttpException('internal detail'));

        self::assertSame(['error' => 'Unprocessable Content'], $this->decode($response));
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

    public function testEveryExceptionIsLoggedAsAnError(): void
    {
        $exception = new \LogicException('boom');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(NamespaceEnum::REST_KERNEL->value, 'boom', ['exception' => $exception], LogLevel::ERROR);

        $this->handle($exception, $logger);
    }

    private function handle(\Throwable $exception, ?LoggerInterface $logger = null): Response
    {
        $event = new ExceptionEvent(
            self::createStub(HttpKernelInterface::class),
            Request::create('/books'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        (new ExceptionListener($logger ?? self::createStub(LoggerInterface::class)))->onKernelException($event);

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
