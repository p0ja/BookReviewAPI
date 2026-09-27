<?php

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

    public function findByBookId(int $id): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.book_id = :val')
            ->setParameter('val', $id)
            ->getQuery()
            ->getResult();
    }

    public function create(Book $book, CreateReview $reviewPost, ?User $user = null): Review
    {
        $submitDate = new \DateTimeImmutable('now');

        $review = new Review();
        $review->setBookId($book);
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
     * @return Page<Review>
     */
    public function findReviews(?int $page, ?int $size, ?string $orderBy, ?int $rating = null): Page
    {
        $qb = $this->createQueryBuilder('b');
        if (null !== $rating) {
            $qb->andWhere('b.rating = :rating')->setParameter('rating', $rating);
        }
        if (in_array($orderBy, ConfigData::REVIEW_SORTING_COLUMNS, true)) {
            $qb->orderBy('b.'.$orderBy, 'ASC');
        }
        // A stable order, or rows could move between pages.
        $qb->addOrderBy('b.id', 'ASC');

        return $this->paginate($qb, $page, $size);
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
            ->select('IDENTITY(r.book_id) AS book, AVG(r.rating) AS average, COUNT(r.id) AS reviews')
            ->andWhere('r.book_id IN (:books)')
            ->setParameter('books', $bookIds)
            ->groupBy('r.book_id')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['book']] = ['average' => round((float) $row['average'], 2), 'count' => (int) $row['reviews']];
        }

        return $stats;
    }

    public function removeReview(int $id): bool
    {
        $review = $this->find($id);
        if ($review) {
            $this->remove($review);

            return true;
        }

        return false;
    }

    public function remove(Review $review): void
    {
        $em = $this->getEntityManager();
        $em->remove($review);
        $em->flush();
    }
}
