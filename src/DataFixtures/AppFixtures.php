<?php

namespace App\DataFixtures;

use App\Factory\AuthorFactory;
use App\Factory\BookAuthorFactory;
use App\Factory\BookFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        AuthorFactory::createMany(20);
        BookFactory::createMany(20);
        BookAuthorFactory::createMany(20);

        $manager->flush();
    }
}
