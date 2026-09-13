<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Config\ConfigData;
use App\Repository\PaginatesResults;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class PaginatesResultsTest extends TestCase
{
    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function paginate(?int $page, ?int $size): array
    {
        $paginator = new class {
            use PaginatesResults;

            public function apply(QueryBuilder $qb, ?int $page, ?int $size): QueryBuilder
            {
                return $this->applyPagination($qb, $page, $size);
            }
        };

        $qb = $paginator->apply(new QueryBuilder(self::createStub(EntityManagerInterface::class)), $page, $size);

        return [$qb->getFirstResult(), $qb->getMaxResults()];
    }

    public function testDefaultsAreAppliedWhenNothingIsRequested(): void
    {
        self::assertSame([0, ConfigData::DEFAULT_PAGE_SIZE], $this->paginate(null, null));
    }

    /**
     * The regression this guards: with $size null the old offset arithmetic was
     * ($page - 1) * null = 0, so every page returned the first page.
     */
    public function testPageWithoutSizeStillAdvancesTheOffset(): void
    {
        self::assertSame(
            [ConfigData::DEFAULT_PAGE_SIZE, ConfigData::DEFAULT_PAGE_SIZE],
            $this->paginate(2, null)
        );
    }

    public function testOffsetIsDerivedFromPageAndSize(): void
    {
        self::assertSame([40, 20], $this->paginate(3, 20));
    }

    public function testSizeIsClampedToTheMaximum(): void
    {
        self::assertSame([0, ConfigData::MAX_PAGE_SIZE], $this->paginate(1, 1_000_000));
    }

    public function testNonPositiveValuesFallBackToTheFirstPage(): void
    {
        self::assertSame([0, 1], $this->paginate(0, 0));
        self::assertSame([0, 1], $this->paginate(-5, -5));
    }
}
