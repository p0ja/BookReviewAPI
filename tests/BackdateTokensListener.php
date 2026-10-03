<?php

declare(strict_types=1);

namespace App\Tests;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

/**
 * Test env only (registered in config/services.yaml): issues every token five
 * minutes in the past.
 *
 * Under WSL2 the wall clock briefly runs about a minute ahead and then steps back
 * (measured: +63.8s, then -64.3s against the monotonic clock). A token issued in
 * that moment has an "iat" in the future of the next request, and Lexik rejects it
 * as "Invalid JWT Token". Backdating "iat" absorbs such steps without loosening
 * validation; "exp" is computed from the current time, so the token still lives for
 * the full TTL.
 */
final class BackdateTokensListener
{
    public const BACKDATE_SECONDS = 300;

    public function __invoke(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();
        $payload['iat'] = time() - self::BACKDATE_SECONDS;
        $event->setData($payload);
    }
}
