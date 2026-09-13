<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testEmailIsTheUserIdentifier(): void
    {
        self::assertSame('reader@example.com', (new User())->setEmail('reader@example.com')->getUserIdentifier());
    }

    public function testEveryUserHasTheUserRole(): void
    {
        self::assertSame(['ROLE_USER'], (new User())->getRoles());
    }

    public function testRolesAreNotDuplicated(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], array_values($user->getRoles()));
    }
}
