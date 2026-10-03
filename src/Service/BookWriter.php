<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateAuthor;
use App\Dto\CreateBook;
use App\Dto\UpdateBook;
use App\Entity\Author;
use App\Entity\Book;
use App\Exception\ConcurrentWriteException;
use App\Exception\IsbnTakenException;
use App\Repository\AuthorRepository;
use App\Repository\BookAuthorRepository;
use App\Repository\BookRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and updates a book together with its authors, in one transaction: a failure
 * part way through must not leave a book with only some of them.
 */
class BookWriter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookRepository $bookRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly BookAuthorRepository $bookAuthorRepository,
    ) {
    }

    /**
     * Open to every user, so existing authors are linked as they are, never changed.
     *
     * @throws IsbnTakenException
     * @throws ConcurrentWriteException
     */
    public function create(CreateBook $data): Book
    {
        $isbn = $data->isbn ?? throw new \LogicException('A new book needs an ISBN; CreateBook is validated before.');
        if ($this->bookRepository->isbnExists($isbn)) {
            throw new IsbnTakenException();
        }

        return $this->write($isbn, null, function () use ($data): Book {
            $book = $this->bookRepository->createBook($data);
            $this->replaceAuthors($book, $data->authors ?? [], updateAuthorInfo: false);

            return $book;
        });
    }

    /**
     * Admins only: an author given with an info gets that info (see
     * AuthorRepository::findOrCreate()).
     *
     * PUT passes a CreateBook (every field, the authors replaced by the given list, none
     * when it is missing); PATCH an UpdateBook (only the given fields, the authors only
     * when a list is given).
     *
     * @throws IsbnTakenException
     * @throws ConcurrentWriteException
     */
    public function update(Book $book, CreateBook|UpdateBook $data): Book
    {
        if (null !== $data->isbn && $this->bookRepository->isbnExists($data->isbn, $book->getId())) {
            throw new IsbnTakenException();
        }

        return $this->write($data->isbn, $book->getId(), function () use ($book, $data): Book {
            $book->touch();
            $this->bookRepository->updateBook($book, $data);
            if ($data instanceof CreateBook || null !== $data->authors) {
                $this->replaceAuthors($book, $data->authors ?? [], updateAuthorInfo: true);
            }

            return $book;
        });
    }

    /**
     * @param string|null      $isbn         the ISBN being written, null when it does not change
     * @param int|null         $exceptBookId the book being updated
     * @param callable(): Book $write
     */
    private function write(?string $isbn, ?int $exceptBookId, callable $write): Book
    {
        try {
            return $this->entityManager->wrapInTransaction($write);
        } catch (UniqueConstraintViolationException $e) {
            // Another request wrote at the same time. Only call it an ISBN conflict when the
            // ISBN is now taken: the violated key may be another one (an author's name, a
            // book-author link), which a retry resolves.
            if (null !== $isbn && $this->bookRepository->isbnExists($isbn, $exceptBookId)) {
                throw new IsbnTakenException($e);
            }

            throw new ConcurrentWriteException($e);
        }
    }

    /**
     * Links the book to exactly these authors: authors are matched by name and created
     * when missing, links to other authors are removed.
     *
     * @param list<CreateAuthor> $authors
     */
    private function replaceAuthors(Book $book, array $authors, bool $updateAuthorInfo): void
    {
        /** @var array<int, Author> $wanted */
        $wanted = [];
        foreach ($authors as $authorData) {
            $author = $this->authorRepository->findOrCreate($authorData, $updateAuthorInfo);
            $wanted[(int) $author->getId()] = $author;
        }

        foreach ($book->getBookAuthors()->toArray() as $link) {
            $authorId = (int) $link->getAuthor()?->getId();
            if (isset($wanted[$authorId])) {
                unset($wanted[$authorId]);
            } else {
                // Both sides, so no loaded author still points at the link;
                // orphanRemoval deletes it on flush.
                $link->getAuthor()?->removeAuthorBook($link);
                $book->removeBookAuthor($link);
            }
        }

        foreach ($wanted as $author) {
            $book->addBookAuthor($this->bookAuthorRepository->createBookAuthor($book, $author));
        }

        $this->entityManager->flush();
    }
}
