<?php

declare(strict_types=1);

namespace App\Repository;

use App\Config\ConfigData;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

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

    /**
     * Runs the query for one page and counts all its matches.
     *
     * The list queries select a single entity without fetch-joined collections, so the
     * paginator can count and slice with plain SQL.
     *
     * @return Page<object> callers narrow it to their entity with a @var tag
     */
    private function paginate(QueryBuilder $qb, ?int $page, ?int $size): Page
    {
        $this->applyPagination($qb, $page, $size);
        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $size = (int) $qb->getMaxResults();

        return new Page(
            iterator_to_array($paginator, false),
            count($paginator),
            intdiv($qb->getFirstResult(), $size) + 1,
            $size,
        );
    }

    /**
     * A LIKE pattern matching $term anywhere, with LIKE's own wildcards taken literally.
     */
    private static function containsPattern(string $term): string
    {
        return '%'.addcslashes(mb_strtolower(trim($term)), '%_\\').'%';
    }
}
