<?php

namespace App\Controller;

use App\Entity\Review;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use App\Output\ReviewData;
use App\Repository\ReviewRepository;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReviewController extends AbstractController
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewData $reviewData,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/reviews/{page?}/{size?}/{orderBy?}', name: 'app_review')]
    public function list(?int $page, ?int $size, ?string $orderBy): Response
    {
        try {
            $reviews = $this->reviewRepository->findReviews($page, $size, $orderBy);
            $reviewsData = [];

            foreach ($reviews as $review) {
                $reviewsData[] = $this->reviewData->getOutput($review);
            }

            return $this->json($reviewsData);

        } catch (Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_REVIEW->value,
                'Reviews listing exception:',
                [
                    'message' => $e->getMessage(),
                ]
            );

            throw $this->createNotFoundException('Reviews not found');
        }
    }
}
