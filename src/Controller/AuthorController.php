<?php

declare(strict_types=1);

namespace App\Controller;

use App\Output\AuthorData;
use App\Output\BookData;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authors')]
final class AuthorController extends AbstractController
{
    public function __construct(
        private readonly AuthorRepository $authorRepository,
        private readonly BookRepository $bookRepository,
        private readonly AuthorData $authorData,
        private readonly BookData $bookData,
    ) {
    }

    #[OA\Get(summary: 'List authors', description: 'Paginated with page and size (default 20, at most 100); orderBy=name sorts by name; name keeps authors whose name contains it, case-insensitive.')]
    #[OA\Response(
        response: 200,
        description: 'One page of authors',
        content: new OA\JsonContent(ref: '#/components/schemas/AuthorPage'),
    )]
    #[OA\Response(response: 400, description: 'Malformed page or size', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/authors', name: 'rest_authors', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $page = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $size = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $orderBy = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $name = null,
    ): Response {
        $authors = $this->authorRepository->findAuthors($page, $size, $orderBy, $name);

        return $this->json($authors->toArray($this->authorData->getOutput(...)), Response::HTTP_OK);
    }

    #[OA\Get(summary: 'Get an author')]
    #[OA\Response(
        response: 200,
        description: 'The author',
        content: new OA\JsonContent(ref: '#/components/schemas/Author'),
    )]
    #[OA\Response(response: 404, description: 'No author with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/authors/{id}', name: 'rest_author', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): Response
    {
        $author = $this->authorRepository->find($id);
        if (!$author) {
            throw $this->createNotFoundException('Author not found');
        }

        return $this->json($this->authorData->getOutput($author), Response::HTTP_OK);
    }

    #[OA\Get(summary: 'List the books of an author', description: 'Paginated and sorted like GET /books.')]
    #[OA\Response(
        response: 200,
        description: 'One page of books',
        content: new OA\JsonContent(ref: '#/components/schemas/BookPage'),
    )]
    #[OA\Response(response: 404, description: 'No author with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/authors/{id}/books', name: 'rest_author_books', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function books(
        int $id,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $page = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $size = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $orderBy = null,
    ): Response {
        if (!$this->authorRepository->find($id)) {
            throw $this->createNotFoundException('Author not found');
        }

        $books = $this->bookRepository->findBooks($page, $size, $orderBy, authorId: $id);

        return $this->json($this->bookData->getPage($books), Response::HTTP_OK);
    }
}
