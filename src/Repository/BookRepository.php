<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CreateBook;
use App\Entity\Book;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(
        protected ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Book::class);
    }

    public function createBook(CreateBook $bookPost): Book
    {
        $isbn = trim($bookPost->isbn);
        if ($this->bookExists($isbn)) {
            $book = $this->findOneBy(['isbn' => $isbn]);
        } else {
            $book = new Book();
        }

        $price = round((float)$bookPost->price, 2);

        $book->setTitle(trim($bookPost->title));
        $book->setIsbn($isbn);
        $book->setDescription($bookPost->description);
        $book->setPrice($price);
        $book->setGenre(trim($bookPost->genre));
        $book->setPublishDate(trim($bookPost->publish_date));

        try {
            $em = $this->getEntityManager();
            $em->persist($book);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'book' => $book,
                ]
            );
        }

        return $book;
    }

    private function bookExists(string $isbn): bool
    {
        $bookCheck = $this->createQueryBuilder('b')
            ->andWhere('b.isbn = :val')
            ->setParameter('val', $isbn)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return (bool)$bookCheck;
    }
}
