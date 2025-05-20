<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Controller\BooksController;

class BookControllerTest extends KernelTestCase
{
    public function testContainer(): void
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();

        $controller = $container->get(BooksController::class);

        $this->assertNotNull($controller);
    }
}
