<?php

declare(strict_types=1);

namespace App\Repository;

use App\Config\ConfigData;
use App\Dto\CreateAuthor;
use App\Entity\Author;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LogLevel;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    use PaginatesResults;

    public function __construct(
        protected ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Author::class);
    }

    /**
     * The author with this name, matched trimmed and in any letter case; created when
     * there is none.
     *
     * Authors are shared by every book, and anyone may create a book, so an existing
     * author's info changes only when $updateInfo allows it (admins editing a book) and
     * a new info is given: leaving it out never wipes it.
     */
    public function findOrCreate(CreateAuthor $authorData, bool $updateInfo = false): Author
    {
        $name = trim($authorData->name);
        $author = $this->findOneByNameIgnoringCase($name);
        if (null !== $author && (!$updateInfo || null === $authorData->info)) {
            return $author;
        }

        $author ??= (new Author())->setName($name);
        $author->setInfo($authorData->info);

        try {
            $em = $this->getEntityManager();
            $em->persist($author);
            $em->flush();
        } catch (\Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_AUTHOR->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'author' => $author,
                ],
                LogLevel::ERROR,
            );

            throw $e;
        }

        return $author;
    }

    /**
     * @param string|null $name part of the name, case-insensitive
     *
     * @return Page<Author>
     */
    public function findAuthors(?int $page, ?int $size, ?string $orderBy, ?string $name = null): Page
    {
        $qb = $this->createQueryBuilder('a');
        if (null !== $name && '' !== trim($name)) {
            $qb->andWhere("LOWER(a.name) LIKE :name ESCAPE '\\'")
                ->setParameter('name', self::containsPattern($name));
        }
        if (in_array($orderBy, ConfigData::AUTHOR_SORTING_COLUMNS, true)) {
            $qb->orderBy('a.'.$orderBy);
        }
        // A stable order, or rows could move between pages.
        $qb->addOrderBy('a.id');

        /** @var Page<Author> $result */
        $result = $this->paginate($qb, $page, $size);

        return $result;
    }

    /**
     * Served by the uniq_author_name_lower index (migration Version20261003110000),
     * which also keeps two requests from creating the same author twice.
     */
    private function findOneByNameIgnoringCase(string $name): ?Author
    {
        return $this->createQueryBuilder('a')
            ->andWhere('LOWER(a.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->orderBy('a.id')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
