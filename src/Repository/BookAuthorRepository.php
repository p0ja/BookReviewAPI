<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\BookAuthor;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<BookAuthor>
 */
class BookAuthorRepository extends ServiceEntityRepository
{
    public function __construct(
        protected ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, BookAuthor::class);
    }

    public function createBookAuthor(Book $book, Author $author): BookAuthor
    {
        if (!$bookAuthor = $this->getBookAuthor($book->getId(), $author->getId())) {
            $bookAuthor = new BookAuthor();
        }

        $bookAuthor->setBook($book);
        $bookAuthor->setAuthor($author);

        try {
            $em = $this->getEntityManager();
            $em->persist($bookAuthor);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK_AUTHOR->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'book' => $book,
                    'author' => $author,
                ]
            );
        }

        return $bookAuthor;
    }

    private function getBookAuthor(int $bookId, int $authorId): BookAuthor|null
    {
        $bookAuthor = $this->createQueryBuilder('b')
            ->andWhere('b.book_id = :val')
            ->andWhere('b.author_id = :val2')
            ->setParameter('val', $bookId)
            ->setParameter('val2', $authorId)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $bookAuthor[0] ?? null;
    }
}
