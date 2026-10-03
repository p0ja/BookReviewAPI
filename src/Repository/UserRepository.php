<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    public function create(User $user): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();
    }

    /**
     * Whether an account uses this email in any letter case.
     */
    public function emailExists(string $email): bool
    {
        return null !== $this->findOneByEmailIgnoringCase($email);
    }

    /**
     * Login and JWT user loading (security.yaml): emails match in any letter case, as
     * people type them differently. Registration stores them in lower case; for older
     * accounts that differ only by case, the exact spelling wins.
     */
    public function loadUserByIdentifier(string $identifier): ?UserInterface
    {
        return $this->findOneBy(['email' => $identifier]) ?? $this->findOneByEmailIgnoringCase($identifier);
    }

    /**
     * Served by the idx_users_email_lower index (migration Version20260927125000).
     *
     * Unlike author names, the parameter is lowercased by PHP, not LOWER(:email): that
     * is how registration stores emails, and the two differ for some letters ("İ"). The
     * index is not unique, so a difference cannot turn into a refused insert.
     */
    private function findOneByEmailIgnoringCase(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->orderBy('u.id')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
