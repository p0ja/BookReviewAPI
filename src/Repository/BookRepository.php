<?php

declare(strict_types=1);

namespace App\Repository;

use App\Config\ConfigData;
use App\Dto\CreateBook;
use App\Entity\Book;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Psr\Log\LogLevel;

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

    public function findBooks(?int $page, ?int $size, ?string $orderBy): array
    {
        $qb = $this->createQueryBuilder('b');
        if ($page) {
            $offset = ($page - 1) * $size;
            $qb->setFirstResult($offset);
        }
        if ($size) {
            $qb->setMaxResults($size);
        }
        if (in_array($orderBy, ConfigData::BOOK_SORTING_COLUMNS, true)) {
            $qb->orderBy('b.'.$orderBy, 'ASC');
        }

        return $qb->getQuery()->getResult();
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
                ],
                LogLevel::ERROR,
            );
        }

        return $book;
    }

    public function removeBook(int $id): bool
    {
        $book = $this->find($id);
        if ($book) {
            $em = $this->getEntityManager();
            $em->remove($book);
            $em->flush();

            return true;
        }

        return false;
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
