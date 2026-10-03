<?php

namespace App\Entity;

use App\Repository\BookAuthorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: BookAuthorRepository::class)]
// A book lists an author once.
#[ORM\UniqueConstraint(name: 'UNIQ_BOOK_AUTHOR', fields: ['book', 'author'])]
class BookAuthor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'book_authors')]
    #[ORM\JoinColumn(name: 'book_id')]
    #[Ignore]
    private ?Book $book = null;

    #[ORM\ManyToOne(inversedBy: 'author_books')]
    #[ORM\JoinColumn(name: 'author_id')]
    #[Ignore]
    private ?Author $author = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;

        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;

        return $this;
    }
}
