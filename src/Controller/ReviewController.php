<?php

namespace App\Controller;

use App\Output\ReviewData;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewData $reviewData,
    ) {
    }

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
