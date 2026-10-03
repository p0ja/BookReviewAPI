<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives every successful GET an ETag computed from its body, and answers 304 Not
 * Modified with no body when the client's If-None-Match still matches it.
 *
 * The responses depend on the caller's token, so only the client may cache them
 * (private), and must revalidate every time (no-cache): a change made through the
 * API is visible on the next request, with no cache to invalidate. This saves the
 * transfer, not the database work.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class ConditionalGetListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$event->isMainRequest()
            || !$request->isMethodCacheable()
            || Response::HTTP_OK !== $response->getStatusCode()
            || $response->headers->has('ETag')
        ) {
            return;
        }

        $content = $response->getContent();
        if (false === $content) {
            // A streamed or binary file response has no body to hash.
            return;
        }

        $response->setEtag(hash('xxh128', $content));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);
    }
}
