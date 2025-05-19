<?php

namespace App\Repository;

use App\Dto\CreateReview;
use App\Entity\Book;
use App\Entity\Review;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LogLevel;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
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

    public function create(Book $book, CreateReview $reviewPost): Review
    {
        $submitDate = new DateTimeImmutable("now");

        $review = new Review();
        $review->setBookId($book);
        $review->setName(trim($reviewPost->name));
        $review->setContent(trim($reviewPost->content));
        $review->setRating(trim($reviewPost->rating));
        $review->setSubmitDate($submitDate);

        try {
            $em = $this->getEntityManager();
            $em->persist($review);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'review' => $review,
                    'book' => $book,
                ],
                LogLevel::ERROR,
            );
        }

        return $review;
    }
}
