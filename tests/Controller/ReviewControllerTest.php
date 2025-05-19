<?php

namespace App\Tests\Controller;

use App\Controller\ReviewController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReviewControllerTest extends KernelTestCase
{
    public function testContainer(): void
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();

        $controller = $container->get(ReviewController::class);

        $this->assertNotNull($controller);
    }
}
