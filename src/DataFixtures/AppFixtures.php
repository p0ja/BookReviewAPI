<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\AuthorFakeDataFactory;
use App\Factory\BookAuthorFakeDataFactory;
use App\Factory\BookFakeDataFactory;
use App\Factory\BookReviewFakeDataFactory;
use App\Factory\UserFakeDataFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        AuthorFakeDataFactory::createMany(20);
        BookFakeDataFactory::createMany(20);
        BookAuthorFakeDataFactory::createMany(20);
        BookReviewFakeDataFactory::createMany(20);

        UserFakeDataFactory::createMany(2);

        $manager->flush();
    }
}
