<?php

namespace App\Controller;

use App\Dto\CreateBook;
use App\Dto\CreateReview;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use App\Output\BookData;
use App\Output\ReviewData;
use App\Repository\AuthorRepository;
use App\Repository\BookAuthorRepository;
use App\Repository\BookRepository;
use App\Repository\ReviewRepository;
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
        private readonly ReviewRepository $reviewRepository,
        private readonly BookData $bookData,
        private readonly ReviewData $reviewData,
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
                $booksData[] = $this->bookData->getOutput($book);
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

            return $this->json([]);
        }
    }

    #[Route('/books/{id}', name: 'rest_book', methods: ['GET'])]
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

        $booksData = $this->bookData->getOutput($book);

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

        $booksData = $this->bookData->getOutput($book);

        return $this->json($booksData);
    }

    #[Route('/books/{id}/reviews', name: 'rest_book_reviews', methods: ['GET'])]
    public function getReviews(int $id): Response
    {
        $reviews = $this->reviewRepository->findByBookId($id);
        if (!$reviews) {
            $this->logger->log(
                NamespaceEnum::REST_BOOK->value,
                'Reviews not found',
                [
                    'book_id' => $id,
                ]
            );

            return $this->json([]);
        }

        $reviewsData = [];
        foreach ($reviews as $review) {
            $reviewsData[] = $this->reviewData->getOutput($review);
        }

        return $this->json($reviewsData);
    }

    #[Route('/books/{id}/reviews', name: 'review_create', methods:['POST'])]
    public function createReview(int $id, #[MapRequestPayload] CreateReview $reviewPost): Response
    {
        $book = $this->bookRepository->find($id);
        if (!$book) {
            throw $this->createNotFoundException('Book not found for new review');
        }
        $review = $this->reviewRepository->create($book, $reviewPost);
        $reviewData = $this->reviewData->getOutput($review);

        return $this->json($reviewData);
    }
}
