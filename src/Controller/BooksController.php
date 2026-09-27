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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[OA\Tag(name: 'Books')]
final class BooksController extends AbstractController
{
    private const ISBN_TAKEN_MSG = 'A book with this ISBN already exists';

    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly BookAuthorRepository $bookAuthorRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly BookData $bookData,
        private readonly ReviewData $reviewData,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Get(summary: 'List books', description: 'Paginated with page and size (default 20, at most 100); orderBy names a column to sort by (an unknown one is ignored).')]
    #[OA\Response(
        response: 200,
        description: 'Books',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Book')),
    )]
    #[OA\Response(response: 400, description: 'Malformed page or size', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books', name: 'rest_books', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $page = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $size = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $orderBy = null,
    ): Response {
        $books = $this->bookRepository->findBooks($page, $size, $orderBy);
        $booksData = [];

        foreach ($books as $book) {
            $booksData[] = $this->bookData->getOutput($book);
        }

        return $this->json($booksData, Response::HTTP_OK);
    }

    #[OA\Get(summary: 'Get a book')]
    #[OA\Response(
        response: 200,
        description: 'The book',
        content: new OA\JsonContent(ref: '#/components/schemas/Book'),
    )]
    #[OA\Response(response: 404, description: 'No book with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books/{id}', name: 'rest_book', requirements: ['id' => '\d+'], methods: ['GET'])]
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

        return $this->json($booksData, Response::HTTP_OK);
    }

    #[OA\Post(summary: 'Create a book with its authors', description: 'Authors are matched by name and created when missing.')]
    #[OA\Response(
        response: 201,
        description: 'The created book',
        headers: [new OA\Header(header: 'Location', description: 'URL of the new book', schema: new OA\Schema(type: 'string'))],
        content: new OA\JsonContent(ref: '#/components/schemas/Book'),
    )]
    #[OA\Response(response: 409, description: 'A book with this ISBN already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books', name: 'book_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateBook $bookPost): Response
    {
        if ($this->bookRepository->isbnExists($bookPost->isbn)) {
            throw new ConflictHttpException(self::ISBN_TAKEN_MSG);
        }

        try {
            // One transaction for the book and all its authors: a failure part way through
            // must not leave a book with only some of them.
            $book = $this->entityManager->wrapInTransaction(function () use ($bookPost) {
                $book = $this->bookRepository->createBook($bookPost);

                foreach ($bookPost->authors ?? [] as $authorData) {
                    $author = $this->authorRepository->createAuthor($authorData);
                    $bookAuthor = $this->bookAuthorRepository->createBookAuthor($book, $author);
                    $book->addBookAuthor($bookAuthor);
                }

                return $book;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Another request created the same ISBN between the check and the insert.
            throw new ConflictHttpException(self::ISBN_TAKEN_MSG, $e);
        }

        $booksData = $this->bookData->getOutput($book);

        return $this->json($booksData, Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('rest_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[OA\Get(summary: 'List the reviews of a book', description: 'An unknown book gives an empty list.')]
    #[OA\Response(
        response: 200,
        description: 'Reviews',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Review')),
    )]
    #[Route('/books/{id}/reviews', name: 'rest_book_reviews', requirements: ['id' => '\d+'], methods: ['GET'])]
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

        return $this->json($reviewsData, Response::HTTP_OK);
    }

    #[OA\Post(summary: 'Add a review to a book')]
    #[OA\Response(
        response: 201,
        description: 'The created review',
        content: new OA\JsonContent(ref: '#/components/schemas/Review'),
    )]
    #[OA\Response(response: 404, description: 'No book with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books/{id}/reviews', name: 'review_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createReview(int $id, #[MapRequestPayload] CreateReview $reviewPost): Response
    {
        $book = $this->bookRepository->find($id);
        if (!$book) {
            throw $this->createNotFoundException('Book not found for new review');
        }
        $review = $this->reviewRepository->create($book, $reviewPost);
        $reviewData = $this->reviewData->getOutput($review);

        return $this->json($reviewData, Response::HTTP_CREATED);
    }

    #[OA\Delete(summary: 'Delete a book and its reviews', description: 'Its authors are kept.')]
    #[OA\Response(
        response: 200,
        description: 'Deleted',
        content: new OA\JsonContent(ref: '#/components/schemas/DeleteResult'),
    )]
    #[OA\Response(response: 404, description: 'No book with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books/{id}', name: 'rest_book_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteBook(int $id): Response
    {
        $book = $this->bookRepository->removeBook($id);
        if (!$book) {
            throw $this->createNotFoundException('No book to be deleted');
        }

        return $this->json(
            ['result' => true],
            Response::HTTP_OK
        );
    }
}
