<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Factory\UserFakeDataFactory;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

class UserRepositoryTest extends RepositoryTestCase
{
    public function testFactoryStoresAHashedPassword(): void
    {
        $user = UserFakeDataFactory::createOne(['password' => 'secret'])->_real();

        self::assertNotSame('secret', $user->getPassword());
        self::assertTrue($this->service(UserPasswordHasherInterface::class)->isPasswordValid($user, 'secret'));
    }

    public function testUpgradePasswordStoresTheNewHash(): void
    {
        $user = UserFakeDataFactory::createOne(['email' => 'reader@example.com'])->_real();

        $this->service(UserRepository::class)->upgradePassword($user, 'new-hash');

        UserFakeDataFactory::assert()->exists(['email' => 'reader@example.com', 'password' => 'new-hash']);
    }

    public function testUpgradePasswordRejectsOtherUserClasses(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $this->service(UserRepository::class)->upgradePassword(new InMemoryUser('reader', 'hash'), 'new-hash');
    }
}
