<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Timestampable;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $isbn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $publish_date = null;

    #[ORM\Column(nullable: true)]
    #[Timestampable(on: 'create')]
    #[Ignore]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @var Collection<int, BookAuthor>
     */
    #[ORM\OneToMany(targetEntity: BookAuthor::class, mappedBy: 'book_id')]
    #[Ignore]
    private Collection $book_authors;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book_id', orphanRemoval: true)]
    private Collection $reviews;

    public function __construct()
    {
        $this->book_authors = new ArrayCollection();
        $this->reviews = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(string $isbn): static
    {
        $this->isbn = $isbn;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): static
    {
        $this->genre = $genre;

        return $this;
    }

    public function getPublishDate(): ?string
    {
        return $this->publish_date;
    }

    public function setPublishDate(?string $publish_date): static
    {
        $this->publish_date = $publish_date;

        return $this;
    }

    /**
     * @return Collection<int, BookAuthor>
     */
    public function getBookAuthors(): Collection
    {
        return $this->book_authors;
    }

    public function addBookAuthor(BookAuthor $bookAuthor): static
    {
        if (!$this->book_authors->contains($bookAuthor)) {
            $this->book_authors->add($bookAuthor);
            $bookAuthor->setBook($this);
        }

        return $this;
    }

    public function removeBookAuthor(BookAuthor $bookAuthor): static
    {
        if ($this->book_authors->removeElement($bookAuthor)) {
            // set the owning side to null (unless already changed)
            if ($bookAuthor->getBook() === $this) {
                $bookAuthor->setBook(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setBookId($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getBookId() === $this) {
                $review->setBookId(null);
            }
        }

        return $this;
    }
}
