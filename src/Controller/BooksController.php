<?php

namespace App\Controller;

use App\Dto\CreateBook;
use App\Dto\CreateReview;
use App\Dto\UpdateBook;
use App\Entity\User;
use App\Exception\IsbnTakenException;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use App\Output\BookData;
use App\Output\ReviewData;
use App\Repository\BookRepository;
use App\Repository\ReviewRepository;
use App\Service\BookWriter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Books')]
final class BooksController extends AbstractController
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly BookData $bookData,
        private readonly ReviewData $reviewData,
        private readonly LoggerInterface $logger,
        private readonly BookWriter $bookWriter,
    ) {
    }

    #[OA\Get(summary: 'List books', description: 'Paginated with page and size (default 20, at most 100); orderBy names a column to sort by (an unknown one is ignored). Filters combine: title and author match part of the text, genre the whole genre, all case-insensitive; minRating keeps books whose average rating is at least that.')]
    #[OA\Response(
        response: 200,
        description: 'One page of books',
        content: new OA\JsonContent(ref: '#/components/schemas/BookPage'),
    )]
    #[OA\Response(response: 400, description: 'Malformed page or size', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books', name: 'rest_books', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $page = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $size = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $orderBy = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $title = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $genre = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $author = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?float $minRating = null,
    ): Response {
        $books = $this->bookRepository->findBooks($page, $size, $orderBy, $title, $genre, $author, $minRating);

        return $this->json($this->bookData->getPage($books), Response::HTTP_OK);
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
        $this->bookRepository->loadAuthors([$book]);

        return $this->json($this->bookData->getOne($book), Response::HTTP_OK);
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
        try {
            $book = $this->bookWriter->create($bookPost);
        } catch (IsbnTakenException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return $this->json($this->bookData->getOne($book), Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('rest_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[OA\Put(summary: 'Replace a book', description: 'Admins only. Every field is set, and the authors are replaced by the given list (none when it is missing).')]
    #[OA\Response(
        response: 200,
        description: 'The updated book',
        content: new OA\JsonContent(ref: '#/components/schemas/Book'),
    )]
    #[OA\Response(response: 403, description: 'Not an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'No book with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'Another book has this ISBN', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books/{id}', name: 'rest_book_replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function replace(int $id, #[MapRequestPayload] CreateBook $bookPut): Response
    {
        return $this->update($id, $bookPut);
    }

    #[OA\Patch(summary: 'Update some fields of a book', description: 'Admins only. Missing fields keep their value; authors, when given, replace all the authors.')]
    #[OA\Response(
        response: 200,
        description: 'The updated book',
        content: new OA\JsonContent(ref: '#/components/schemas/Book'),
    )]
    #[OA\Response(response: 403, description: 'Not an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'No book with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'Another book has this ISBN', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books/{id}', name: 'rest_book_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function patch(int $id, #[MapRequestPayload] UpdateBook $bookPatch): Response
    {
        return $this->update($id, $bookPatch);
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
        $user = $this->getUser();
        $review = $this->reviewRepository->create($book, $reviewPost, $user instanceof User ? $user : null);
        $reviewData = $this->reviewData->getOutput($review);

        return $this->json($reviewData, Response::HTTP_CREATED);
    }

    #[OA\Delete(summary: 'Delete a book and its reviews', description: 'Admins only. Its authors are kept.')]
    #[OA\Response(
        response: 200,
        description: 'Deleted',
        content: new OA\JsonContent(ref: '#/components/schemas/DeleteResult'),
    )]
    #[OA\Response(response: 404, description: 'No book with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 403, description: 'Not an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/books/{id}', name: 'rest_book_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
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

    private function update(int $id, CreateBook|UpdateBook $data): Response
    {
        $book = $this->bookRepository->find($id);
        if (!$book) {
            throw $this->createNotFoundException('Book not found');
        }

        try {
            $book = $this->bookWriter->update($book, $data);
        } catch (IsbnTakenException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return $this->json($this->bookData->getOne($book), Response::HTTP_OK);
    }
}
