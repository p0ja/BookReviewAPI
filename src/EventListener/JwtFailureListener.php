<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Lexik answers failed logins and missing, invalid or expired tokens with
 * {"code": 401, "message": ...}; rewrite those into the {"error": ...} format that
 * every other error of the API uses (see ExceptionListener).
 */
#[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
#[AsEventListener(event: Events::JWT_NOT_FOUND)]
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class JwtFailureListener
{
    public function __construct(
        // Registered by login_throttling of the api_login firewall in config/packages/security.yaml.
        #[Autowire(service: 'security.login_throttling.api_login.limiter')]
        private readonly PeekableRequestRateLimiterInterface $loginLimiter,
    ) {
    }

    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response instanceof JWTAuthenticationFailureResponse) {
            return;
        }

        // Keep the headers, WWW-Authenticate: Bearer among them.
        $status = $response->getStatusCode();
        $headers = $response->headers->all();

        // Throttled logins get 429 rather than Lexik's 401, with Retry-After like /register.
        // The exception only knows the wait in whole minutes, rounded up, so the seconds
        // come from the limiter itself.
        $request = $event->getRequest();
        if ($event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            $status = Response::HTTP_TOO_MANY_REQUESTS;
            if (null !== $request) {
                $retryAfter = $this->loginLimiter->peek($request)->getRetryAfter()->getTimestamp() - time();
                $headers['Retry-After'] = (string) max(1, $retryAfter);
            }
        }

        $event->setResponse(new JsonResponse(
            ['error' => $response->getMessage()],
            $status,
            $headers,
        ));
    }
}
