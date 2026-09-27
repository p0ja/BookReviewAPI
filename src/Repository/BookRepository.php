<?php

declare(strict_types=1);

namespace App\Repository;

use App\Config\ConfigData;
use App\Dto\CreateBook;
use App\Dto\UpdateBook;
use App\Entity\Book;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    use PaginatesResults;

    public function __construct(
        protected ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Book::class);
    }

    /**
     * @param string|null $title     part of the title, case-insensitive
     * @param string|null $genre     the whole genre, case-insensitive
     * @param string|null $author    part of an author's name, case-insensitive
     * @param float|null  $minRating lowest average rating; books without reviews never match
     * @param int|null    $authorId  only the books of this author
     *
     * @return Page<Book>
     */
    public function findBooks(
        ?int $page,
        ?int $size,
        ?string $orderBy,
        ?string $title = null,
        ?string $genre = null,
        ?string $author = null,
        ?float $minRating = null,
        ?int $authorId = null,
    ): Page {
        $qb = $this->createQueryBuilder('b');

        if (null !== $title && '' !== trim($title)) {
            $qb->andWhere("LOWER(b.title) LIKE :title ESCAPE '\\'")
                ->setParameter('title', self::containsPattern($title));
        }
        if (null !== $genre && '' !== trim($genre)) {
            $qb->andWhere('LOWER(b.genre) = :genre')
                ->setParameter('genre', mb_strtolower(trim($genre)));
        }
        // Subqueries rather than joins keep one row per book, so paging stays simple.
        if (null !== $author && '' !== trim($author)) {
            $qb->andWhere("b.id IN (SELECT IDENTITY(ba.book_id) FROM App\Entity\BookAuthor ba JOIN ba.author_id a WHERE LOWER(a.name) LIKE :author ESCAPE '\\')")
                ->setParameter('author', self::containsPattern($author));
        }
        if (null !== $authorId) {
            $qb->andWhere('b.id IN (SELECT IDENTITY(bl.book_id) FROM App\Entity\BookAuthor bl WHERE bl.author_id = :authorId)')
                ->setParameter('authorId', $authorId);
        }
        if (null !== $minRating) {
            // A literal, not a parameter: PDO sends a float as text, and SQLite ranks every
            // number below any text, so AVG(...) >= '4.5' never matched there. Clamping to
            // just outside the 0-5 scale keeps the result and rules out INF/NAN in the SQL;
            // %F is locale-independent.
            $qb->andWhere(sprintf(
                'b.id IN (SELECT IDENTITY(r.book_id) FROM App\Entity\Review r GROUP BY r.book_id HAVING AVG(r.rating) >= %F)',
                max(-1.0, min(6.0, $minRating)),
            ));
        }

        if (in_array($orderBy, ConfigData::BOOK_SORTING_COLUMNS, true)) {
            $qb->orderBy('b.'.$orderBy, 'ASC');
        }
        // A stable order, or rows could move between pages.
        $qb->addOrderBy('b.id', 'ASC');

        /** @var Page<Book> $result */
        $result = $this->paginate($qb, $page, $size);
        $this->loadAuthors($result->items);

        return $result;
    }

    /**
     * Fills the author list of every given book with one query, instead of one query per
     * book for its links and one per author (the N+1 of a list page).
     *
     * The books are already loaded; fetch-joining them again initialises their author
     * collections in place, and the result itself is not needed.
     *
     * @param list<Book> $books
     */
    public function loadAuthors(array $books): void
    {
        if ([] === $books) {
            return;
        }

        $this->createQueryBuilder('b')
            ->select('b', 'ba', 'a')
            ->leftJoin('b.book_authors', 'ba')
            ->leftJoin('ba.author_id', 'a')
            ->andWhere('b IN (:books)')
            ->setParameter('books', $books)
            // Authors in the order they were added, as a lazy load returned them.
            ->orderBy('ba.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Creates a new book; an ISBN that is already taken fails on the unique index,
     * so check isbnExists() first.
     */
    public function createBook(CreateBook $bookPost): Book
    {
        return $this->updateBook(new Book(), $bookPost);
    }

    /**
     * Saves the given fields; with UpdateBook a null field keeps its current value.
     * Authors are left alone, see BookWriter.
     */
    public function updateBook(Book $book, CreateBook|UpdateBook $bookPost): Book
    {
        if (null !== $bookPost->title) {
            $book->setTitle(trim($bookPost->title));
        }
        if (null !== $bookPost->isbn) {
            $book->setIsbn(trim($bookPost->isbn));
        }
        if (null !== $bookPost->description) {
            $book->setDescription($bookPost->description);
        }
        if (null !== $bookPost->price) {
            // Rounded to the column's two decimals; the DTO caps it at 99999999.99.
            $book->setPrice(number_format(round((float) $bookPost->price, 2), 2, '.', ''));
        }
        if (null !== $bookPost->genre) {
            $book->setGenre(trim($bookPost->genre));
        }
        if (null !== $bookPost->publish_date) {
            // Validated as Y-m-d by the DTO; an empty string clears the date.
            $date = trim($bookPost->publish_date);
            $book->setPublishDate('' === $date ? null : new \DateTimeImmutable($date));
        }

        try {
            $em = $this->getEntityManager();
            $em->persist($book);
            $em->flush();
        } catch (\Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'book' => $book,
                ],
                LogLevel::ERROR,
            );

            throw $e;
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

    /**
     * @param int|null $exceptBookId a book whose own ISBN does not count, for updates
     */
    public function isbnExists(string $isbn, ?int $exceptBookId = null): bool
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.isbn = :val')
            ->setParameter('val', trim($isbn))
            ->setMaxResults(1);
        if (null !== $exceptBookId) {
            $qb->andWhere('b.id <> :except')->setParameter('except', $exceptBookId);
        }

        return (bool) $qb->getQuery()->getResult();
    }
}
