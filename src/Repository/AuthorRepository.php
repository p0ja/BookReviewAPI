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

    public function createAuthor(CreateAuthor $authorData): Author
    {
        if ($this->authorExists($authorData->name)) {
            $author = $this->findOneBy(['name' => $authorData->name]);
        } else {
            $author = new Author();
        }

        $author->setName($authorData->name);
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
                    'book' => $author,
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
            $qb->orderBy('a.'.$orderBy, 'ASC');
        }
        // A stable order, or rows could move between pages.
        $qb->addOrderBy('a.id', 'ASC');

        /** @var Page<Author> $result */
        $result = $this->paginate($qb, $page, $size);

        return $result;
    }

    private function authorExists(string $name): bool
    {
        $authorCheck = $this->createQueryBuilder('b')
            ->andWhere('b.name = :val')
            ->setParameter('val', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return (bool) $authorCheck;
    }
}
