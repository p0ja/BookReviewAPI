<?php

declare(strict_types=1);

namespace App\Repository;

use App\Config\ConfigData;
use Doctrine\ORM\QueryBuilder;

/**
 * Shared page/size handling for the list endpoints.
 *
 * Both values are always applied: an unbounded list query is never what a REST
 * client wants, and leaving $size null used to make the offset arithmetic
 * collapse to 0, so ?page=2 silently returned page 1.
 */
trait PaginatesResults
{
    private function applyPagination(QueryBuilder $qb, ?int $page, ?int $size): QueryBuilder
    {
        $page = max(1, $page ?? 1);
        $size = min(
            max(1, $size ?? ConfigData::DEFAULT_PAGE_SIZE),
            ConfigData::MAX_PAGE_SIZE
        );

        return $qb
            ->setFirstResult(($page - 1) * $size)
            ->setMaxResults($size);
    }
}
