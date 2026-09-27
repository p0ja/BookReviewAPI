<?php

namespace App\Controller;

use App\Output\ReviewData;
use App\Repository\ReviewRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Reviews')]
final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewData $reviewData,
    ) {
    }

    #[OA\Get(summary: 'List reviews', description: 'Paginated with page and size (default 20, at most 100); orderBy names a column to sort by (an unknown one is ignored).')]
    #[OA\Response(
        response: 200,
        description: 'Reviews',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Review')),
    )]
    #[OA\Response(response: 400, description: 'Malformed page or size', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/reviews', name: 'app_review', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $page = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $size = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $orderBy = null,
    ): Response {
        $reviews = $this->reviewRepository->findReviews($page, $size, $orderBy);
        $reviewsData = [];

        foreach ($reviews as $review) {
            $reviewsData[] = $this->reviewData->getOutput($review);
        }

        return $this->json($reviewsData, Response::HTTP_OK);
    }

    #[OA\Delete(summary: 'Delete a review')]
    #[OA\Response(
        response: 200,
        description: 'Deleted',
        content: new OA\JsonContent(ref: '#/components/schemas/DeleteResult'),
    )]
    #[OA\Response(response: 404, description: 'No review with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/review/delete/{id}', name: 'rest_review_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $result = $this->reviewRepository->removeReview($id);
        if (!$result) {
            throw $this->createNotFoundException('No review to be deleted');
        }

        return $this->json(
            ['result' => true],
            Response::HTTP_OK
        );
    }
}
