<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\CreateReview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

class CreateReviewTest extends TestCase
{
    public function testValidReviewHasNoViolations(): void
    {
        self::assertCount(0, $this->validate());
    }

    #[DataProvider('invalidFields')]
    public function testInvalidFieldIsReported(string $field, string $value): void
    {
        $violations = $this->validate([$field => $value]);

        self::assertGreaterThan(0, count($violations));
        self::assertSame($field, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'too long name' => ['name', str_repeat('a', 256)];
        yield 'too short content' => ['content', 'ok'];
        yield 'too long content' => ['content', str_repeat('a', 10001)];
        yield 'blank rating' => ['rating', ''];
        yield 'non-numeric rating' => ['rating', 'abcde'];
        yield 'rating above the scale' => ['rating', '6'];
        yield 'negative rating' => ['rating', '-1'];
        yield 'fractional rating' => ['rating', '4.5'];
        yield 'multi-digit rating' => ['rating', '05'];
    }

    #[DataProvider('ratingScaleBounds')]
    public function testRatingScaleBoundsAreAccepted(string $rating): void
    {
        self::assertCount(0, $this->validate(['rating' => $rating]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ratingScaleBounds(): iterable
    {
        yield 'lowest' => ['0'];
        yield 'highest' => ['5'];
    }

    /**
     * @param array<string, string> $overrides
     */
    private function validate(array $overrides = []): ConstraintViolationListInterface
    {
        $fields = array_merge(['name' => 'Jane', 'content' => 'Worth reading twice.', 'rating' => '4'], $overrides);

        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
            ->validate(new CreateReview(...$fields));
    }
}
