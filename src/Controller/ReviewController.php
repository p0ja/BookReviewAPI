<?php

namespace App\Controller;

use App\Dto\CreateReview;
use App\Dto\UpdateReview;
use App\Entity\Review;
use App\Output\ReviewData;
use App\Repository\ReviewRepository;
use App\Security\ReviewVoter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Reviews')]
final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewData $reviewData,
    ) {
    }

    #[OA\Get(summary: 'List reviews', description: 'Paginated with page and size (default 20, at most 100); orderBy names a column to sort by (an unknown one is ignored); rating keeps only reviews with that rating.')]
    #[OA\Response(
        response: 200,
        description: 'One page of reviews',
        content: new OA\JsonContent(ref: '#/components/schemas/ReviewPage'),
    )]
    #[OA\Response(response: 400, description: 'Malformed page or size', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/reviews', name: 'app_review', methods: ['GET'])]
    public function list(
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $page = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $size = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?string $orderBy = null,
        #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] ?int $rating = null,
    ): Response {
        $reviews = $this->reviewRepository->findReviews($page, $size, $orderBy, $rating);

        return $this->json($reviews->toArray($this->reviewData->getOutput(...)), Response::HTTP_OK);
    }

    #[OA\Get(summary: 'Get a review')]
    #[OA\Response(
        response: 200,
        description: 'The review',
        content: new OA\JsonContent(ref: '#/components/schemas/Review'),
    )]
    #[OA\Response(response: 404, description: 'No review with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/reviews/{id}', name: 'rest_review', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): Response
    {
        $review = $this->reviewRepository->find($id);
        if (!$review) {
            throw $this->createNotFoundException('Review not found');
        }

        return $this->json($this->reviewData->getOutput($review), Response::HTTP_OK);
    }

    #[OA\Put(summary: 'Replace a review', description: 'Only its author or an admin.')]
    #[OA\Response(
        response: 200,
        description: 'The updated review',
        content: new OA\JsonContent(ref: '#/components/schemas/Review'),
    )]
    #[OA\Response(response: 403, description: 'Not the author of the review nor an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'No review with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/reviews/{id}', name: 'rest_review_replace', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function replace(int $id, #[MapRequestPayload] CreateReview $reviewPut): Response
    {
        $review = $this->reviewRepository->update($this->findEditable($id), $reviewPut);

        return $this->json($this->reviewData->getOutput($review), Response::HTTP_OK);
    }

    #[OA\Patch(summary: 'Update some fields of a review', description: 'Only its author or an admin. Missing fields keep their value.')]
    #[OA\Response(
        response: 200,
        description: 'The updated review',
        content: new OA\JsonContent(ref: '#/components/schemas/Review'),
    )]
    #[OA\Response(response: 403, description: 'Not the author of the review nor an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'No review with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Invalid payload', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/reviews/{id}', name: 'rest_review_patch', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function patch(int $id, #[MapRequestPayload] UpdateReview $reviewPatch): Response
    {
        $review = $this->reviewRepository->update($this->findEditable($id), $reviewPatch);

        return $this->json($this->reviewData->getOutput($review), Response::HTTP_OK);
    }

    #[OA\Delete(summary: 'Delete a review', description: 'Only its author or an admin.')]
    #[OA\Response(
        response: 200,
        description: 'Deleted',
        content: new OA\JsonContent(ref: '#/components/schemas/DeleteResult'),
    )]
    #[OA\Response(response: 404, description: 'No review with this id', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 403, description: 'Not the author of the review nor an admin', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Route('/reviews/{id}', name: 'rest_review_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $this->reviewRepository->remove($this->findEditable($id));

        return $this->json(
            ['result' => true],
            Response::HTTP_OK
        );
    }

    private function findEditable(int $id): Review
    {
        $review = $this->reviewRepository->find($id);
        if (!$review) {
            throw $this->createNotFoundException('Review not found');
        }
        $this->denyAccessUnlessGranted(ReviewVoter::EDIT, $review);

        return $review;
    }
}
