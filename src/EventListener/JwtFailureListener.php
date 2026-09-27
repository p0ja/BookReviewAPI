<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;

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
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $response = $event->getResponse();
        if (!$response instanceof JWTAuthenticationFailureResponse) {
            return;
        }

        // Keep the headers, WWW-Authenticate: Bearer among them.
        $event->setResponse(new JsonResponse(
            ['error' => $response->getMessage()],
            $response->getStatusCode(),
            $response->headers->all(),
        ));
    }
}
