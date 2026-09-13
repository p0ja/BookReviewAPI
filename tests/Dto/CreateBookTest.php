<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\CreateBook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

class CreateBookTest extends TestCase
{
    public function testValidBookHasNoViolations(): void
    {
        self::assertCount(0, $this->validate());
    }

    #[DataProvider('invalidFields')]
    public function testInvalidFieldIsReported(string $field, mixed $value): void
    {
        $violations = $this->validate([$field => $value]);

        self::assertCount(1, $violations);
        self::assertSame($field, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidFields(): iterable
    {
        yield 'blank title' => ['title', ''];
        yield 'too long title' => ['title', str_repeat('a', 256)];
        yield 'too short isbn' => ['isbn', '123456789'];
        yield 'too long isbn' => ['isbn', '97801344941661'];
        yield 'zero price' => ['price', '0'];
        yield 'zero decimal price' => ['price', '0.00'];
        yield 'negative price' => ['price', '-1.50'];
        yield 'non-numeric price' => ['price', 'abc'];
        yield 'price with a currency' => ['price', '29.99 PLN'];
        yield 'price in scientific notation' => ['price', '1e3'];
        yield 'price with a comma separator' => ['price', '29,99'];
        yield 'too long genre' => ['genre', str_repeat('a', 256)];
        yield 'too long publish date' => ['publish_date', str_repeat('1', 26)];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function validate(array $overrides = []): ConstraintViolationListInterface
    {
        $fields = array_merge([
            'title' => 'Clean Architecture',
            'isbn' => '9780134494166',
            'description' => 'A craftsman\'s guide',
            'price' => '29.99',
            'genre' => 'Software',
            'publish_date' => '2017-09-10',
            'authors' => [['name' => 'Robert C. Martin', 'info' => null]],
        ], $overrides);

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate(new CreateBook(...$fields));
    }
}
