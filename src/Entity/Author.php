<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $info = null;

    /**
     * @var Collection<int, BookAuthor>
     */
    #[ORM\OneToMany(targetEntity: BookAuthor::class, mappedBy: 'author_id')]
    private Collection $author_books;

    public function __construct()
    {
        $this->author_books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getInfo(): ?string
    {
        return $this->info;
    }

    public function setInfo(?string $info): static
    {
        $this->info = $info;

        return $this;
    }

    /**
     * @return Collection<int, BookAuthor>
     */
    public function getAuthorBooks(): Collection
    {
        return $this->author_books;
    }

    public function addAuthorBook(BookAuthor $authorBook): static
    {
        if (!$this->author_books->contains($authorBook)) {
            $this->author_books->add($authorBook);
            $authorBook->setAuthorId($this);
        }

        return $this;
    }

    public function removeAuthorBook(BookAuthor $authorBook): static
    {
        if ($this->author_books->removeElement($authorBook)) {
            // set the owning side to null (unless already changed)
            if ($authorBook->getAuthorId() === $this) {
                $authorBook->setAuthorId(null);
            }
        }

        return $this;
    }
}
