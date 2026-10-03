<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\UpdateAuthor;
use App\Output\AuthorData;
use App\Output\BookData;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Authors')]
final class AuthorController extends AbstractController
{
    private const NAME_TAKEN_MSG = 'Another author already has this name';

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

    #[OA\Patch(summary: 'Update an author', description: 'Admins only. Missing fields keep their value; an empty info clears it. Names are unique, trimmed and in any letter case.')]
    #[OA\Response(
        response: 200,
        description: 'The updated author',
        content: new OA\JsonContent(ref: '#/components/schemas/Author'),
    )]
    #[OA\Response(response: 403, description: 'Not an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'No author with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'Another author already has this name', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/authors/{id}', name: 'rest_author_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function patch(int $id, #[MapRequestPayload] UpdateAuthor $authorPatch): Response
    {
        $author = $this->authorRepository->find($id);
        if (!$author) {
            throw $this->createNotFoundException('Author not found');
        }
        if (null !== $authorPatch->name && $this->authorRepository->nameTaken($authorPatch->name, $id)) {
            throw new ConflictHttpException(self::NAME_TAKEN_MSG);
        }

        try {
            $author = $this->authorRepository->update($author, $authorPatch);
        } catch (UniqueConstraintViolationException $e) {
            // Renamed by another request between the check and the update.
            throw new ConflictHttpException(self::NAME_TAKEN_MSG, $e);
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
