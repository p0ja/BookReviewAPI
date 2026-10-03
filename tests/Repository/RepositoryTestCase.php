<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Base for repository tests that run real queries against the test database.
 */
#[ResetDatabase]
abstract class RepositoryTestCase extends KernelTestCase
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $class): object
    {
        return static::getContainer()->get($class);
    }
}
