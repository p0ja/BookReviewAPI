<?php

declare(strict_types=1);

namespace App\Output;

use App\Entity\Book;

class BookData
{
    public function getOutput(Book $book): array
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
        ];
    }

    private function getBookAuthors(Book $book): array
    {
        $authors = $book->getBookAuthors();
        $authorsData = [];

        foreach ($authors as $author) {
            $authorsData[] = [
                'id' => $author->getId(),
                'name' => $author->getAuthor()?->getName(),
            ];
        }

        return $authorsData;
    }
}
