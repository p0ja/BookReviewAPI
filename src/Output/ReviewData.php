<?php

declare(strict_types=1);

namespace App\Output;

use App\Entity\Review;

class ReviewData
{
    public function getOutput(Review $review): array
    {

        return [
            'review_id' => $review->getId(),
            'book_id' => $review->getBook()?->getId(),
            'book_name' => $review->getBook()?->getTitle(),
            'reviewer' => $review->getName(),
            'content' => $review->getContent(),
            'rating' => $review->getRating(),
            'submit_date' => $review->getSubmitDate(),
        ];
    }
}
