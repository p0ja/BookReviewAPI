<?php

namespace App\Entity;

use App\Repository\BookAuthorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: BookAuthorRepository::class)]
class BookAuthor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'book_authors')]
    #[Ignore]
    private ?Book $book_id = null;

    #[ORM\ManyToOne(inversedBy: 'author_books')]
    #[Ignore]
    private ?Author $author_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book_id;
    }

    public function setBook(?Book $book_id): static
    {
        $this->book_id = $book_id;

        return $this;
    }

    public function setBookId(?Book $book_id): static
    {
        $this->book_id = $book_id;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author_id;
    }

    public function setAuthor(?Author $author_id): static
    {
        $this->author_id = $author_id;

        return $this;
    }

    public function setAuthorId(?Author $author_id): static
    {
        $this->author_id = $author_id;

        return $this;
    }
}
