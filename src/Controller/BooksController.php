<?php

namespace App\Controller;

use App\Dto\CreateBook;
use App\Entity\Book;
use App\Entity\BookAuthor;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use App\Repository\AuthorRepository;
use App\Repository\BookAuthorRepository;
use App\Repository\BookRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class BooksController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly BookAuthorRepository $bookAuthorRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/books', name: 'rest_books', methods: ['GET'])]
    public function list(): Response
    {
        try {
            $books = $this->bookRepository->findAll();
            $booksData = [];

            foreach ($books as $book) {
                $booksData[] = $this->getBookData($book);
            }

            return $this->json($booksData);

        } catch (Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                'Books listing exception:',
                [
                    'message' => $e->getMessage(),
                ]
            );

            throw $this->createNotFoundException('Books not found');
        }
    }

    #[Route('/book/{id}', name: 'rest_book', methods: ['GET'])]
    public function get(int $id): Response
    {
        $book = $this->bookRepository->find($id);
        if (!$book) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                'Book not found',
                [
                    'id' => $id,
                ]
            );

            throw $this->createNotFoundException('Book not found');
        }

        $booksData = $this->getBookData($book);

        return $this->json($booksData);
    }

    #[Route('/books', name: 'book_create', methods:['POST'])]
    public function create(#[MapRequestPayload] CreateBook $bookPost): Response
    {
        $book = $this->bookRepository->createBook($bookPost);

        foreach ($bookPost->authors as $authorData) {
            $author = $this->authorRepository->createAuthor($authorData);
            $bookAuthor = $this->bookAuthorRepository->createBookAuthor($book, $author);
            $book->addBookAuthor($bookAuthor);
        }
dump($book);exit;
        $booksData = $this->getBookData($book);

        return $this->json($booksData);
    }

    private function getBookAuthors(Book $book): array
    {
        $authors = $book->getBookAuthors();
        $authorsData = [];

        foreach ($authors as $author) {
            $authorsData[] = [
                'id' => $author->getId(),
                'name' => $author->getAuthor()?->getName(),
            ];
        }

        return $authorsData;
    }

    private function getBookData(Book $book): array
    {
        $authorsData = $this->getBookAuthors($book);

        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'isbn' => $book->getIsbn(),
            'price' => $book->getPrice(),
            'description' => $book->getDescription(),
            'genre' => $book->getGenre(),
            'publish_date' => $book->getPublishDate(),
            'authors' => $authorsData,
        ];
    }
}
