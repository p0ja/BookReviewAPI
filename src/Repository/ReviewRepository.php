<?php

declare(strict_types=1);

namespace App\Repository;

use App\Config\ConfigData;
use App\Dto\CreateReview;
use App\Dto\UpdateReview;
use App\Entity\Book;
use App\Entity\Review;
use App\Entity\User;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    use PaginatesResults;

    public function __construct(
        protected ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Review::class);
    }

    public function create(Book $book, CreateReview $reviewPost, ?User $user = null): Review
    {
        $submitDate = new \DateTimeImmutable('now');

        $review = new Review();
        $review->setBook($book);
        $review->setUser($user);
        $review->setSubmitDate($submitDate);

        return $this->update($review, $reviewPost);
    }

    /**
     * Saves the given fields; with UpdateReview a null field keeps its current value.
     */
    public function update(Review $review, CreateReview|UpdateReview $reviewPost): Review
    {
        if (null !== $reviewPost->name) {
            $review->setName(trim($reviewPost->name));
        }
        if (null !== $reviewPost->content) {
            $review->setContent(trim($reviewPost->content));
        }
        if (null !== $reviewPost->rating) {
            $review->setRating((int) trim($reviewPost->rating));
        }

        try {
            $em = $this->getEntityManager();
            $em->persist($review);
            $em->flush();
        } catch (\Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'review' => $review,
                    'book' => $review->getBook(),
                ],
                LogLevel::ERROR,
            );

            throw $e;
        }

        return $review;
    }

    /**
     * @param int|null $bookId only the reviews of this book
     *
     * @return Page<Review>
     */
    public function findReviews(?int $page, ?int $size, ?string $orderBy, ?int $rating = null, ?int $bookId = null): Page
    {
        // The book comes with each review (its title is in the output), in the same query.
        // A many-to-one join adds no rows, so paging stays exact.
        $qb = $this->createQueryBuilder('b')
            ->addSelect('book')
            ->innerJoin('b.book', 'book');
        if (null !== $rating) {
            $qb->andWhere('b.rating = :rating')->setParameter('rating', $rating);
        }
        if (null !== $bookId) {
            $qb->andWhere('b.book = :book')->setParameter('book', $bookId);
        }
        if (in_array($orderBy, ConfigData::REVIEW_SORTING_COLUMNS, true)) {
            $qb->orderBy('b.'.$orderBy);
        }
        // A stable order, or rows could move between pages.
        $qb->addOrderBy('b.id');

        /** @var Page<Review> $result */
        $result = $this->paginate($qb, $page, $size);

        return $result;
    }

    /**
     * Average rating and review count of each given book, in one query.
     *
     * @param list<int> $bookIds
     *
     * @return array<int, array{average: float, count: int}> keyed by book id; books without reviews are missing
     */
    public function ratingStats(array $bookIds): array
    {
        if ([] === $bookIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.book) AS book, AVG(r.rating) AS average, COUNT(r.id) AS reviews')
            ->andWhere('r.book IN (:books)')
            ->setParameter('books', $bookIds)
            ->groupBy('r.book')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['book']] = ['average' => round((float) $row['average'], 2), 'count' => (int) $row['reviews']];
        }

        return $stats;
    }

    public function remove(Review $review): void
    {
        $em = $this->getEntityManager();
        $em->remove($review);
        $em->flush();
    }
}
