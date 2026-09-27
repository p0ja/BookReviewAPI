<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * One page of a list query, with what a client needs to page through the rest.
 *
 * @template T of object
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $size,
    ) {
    }

    /**
     * @param callable(T): array<string, mixed> $map
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, size: int}
     */
    public function toArray(callable $map): array
    {
        return [
            'items' => array_map($map, $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'size' => $this->size,
        ];
    }
}
