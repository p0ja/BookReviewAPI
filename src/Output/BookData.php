<?php

declare(strict_types=1);

namespace App\Output;

use App\Entity\Book;
use App\Repository\Page;
use App\Repository\ReviewRepository;

class BookData
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOne(Book $book): array
    {
        return $this->getOutput($book, $this->reviewRepository->ratingStats([(int) $book->getId()])[$book->getId()] ?? null);
    }

    /**
     * The page as the list endpoints answer it; the ratings of all its books come
     * from a single query.
     *
     * @param Page<Book> $page
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, size: int}
     */
    public function getPage(Page $page): array
    {
        $stats = $this->reviewRepository->ratingStats(array_map(static fn (Book $book): int => (int) $book->getId(), $page->items));

        return $page->toArray(fn (Book $book): array => $this->getOutput($book, $stats[$book->getId()] ?? null));
    }

    /**
     * @param array{average: float, count: int}|null $rating from ReviewRepository::ratingStats(), null when the book has no reviews
     */
    public function getOutput(Book $book, ?array $rating = null): array
    {
        $authorsData = $this->getBookAuthors($book);

        return [
            'id' => $book->getId(),
            'title' => $book->getTitle(),
            'isbn' => $book->getIsbn(),
            'price' => $book->getPrice(),
            'description' => $book->getDescription(),
            'genre' => $book->getGenre(),
            'publish_date' => $book->getPublishDate(),
            'authors' => $authorsData,
            'average_rating' => $rating['average'] ?? null,
            'review_count' => $rating['count'] ?? 0,
        ];
    }

    private function getBookAuthors(Book $book): array
    {
        $authors = $book->getBookAuthors();
        $authorsData = [];

        foreach ($authors as $author) {
            $authorsData[] = [
                'id' => $author->getAuthor()?->getId(),
                'name' => $author->getAuthor()?->getName(),
            ];
        }

        return $authorsData;
    }
}
