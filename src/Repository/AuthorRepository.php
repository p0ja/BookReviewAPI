<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Author;
use App\Logger\LoggerInterface;
use App\Logger\NamespaceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(
        protected ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Author::class);
    }

    public function createAuthor(array $authorData): Author
    {
        if ($this->authorExists($authorData['name'])) {
            $author = $this->findOneBy(['name' => $authorData['name']]);
        } else {
            $author = new Author();
        }

        $author->setName($authorData['name']);
        $author->setInfo($authorData['info']);

        try {
            $em = $this->getEntityManager();
            $em->persist($author);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->log(
                NamespaceEnum::REST_AUTHOR->value,
                $e->getMessage(),
                [
                    'exception' => $e,
                    'book' => $author,
                ]
            );
        }

        return $author;
    }

    private function authorExists(string $name): bool
    {
        $authorCheck = $this->createQueryBuilder('b')
            ->andWhere('b.name = :val')
            ->setParameter('val', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return (bool)$authorCheck;
    }
}
