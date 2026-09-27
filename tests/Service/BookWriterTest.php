<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateBook;
use App\Exception\IsbnTakenException;
use App\Repository\AuthorRepository;
use App\Repository\BookAuthorRepository;
use App\Repository\BookRepository;
use App\Service\BookWriter;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * A unique-key violation during the write (a request racing this one) is only an
 * ISBN conflict when the ISBN is taken; other violations must not be reported as one.
 */
class BookWriterTest extends TestCase
{
    public function testViolationBecauseTheIsbnWasTakenMeanwhileIsAnIsbnConflict(): void
    {
        // Free when checked before the write, taken when checked after it failed.
        $writer = $this->writerFailingWithAUniqueViolation(isbnTakenAfterwards: true);

        $this->expectException(IsbnTakenException::class);

        $writer->create($this->book());
    }

    public function testOtherUniqueViolationsAreNotReportedAsAnIsbnConflict(): void
    {
        $writer = $this->writerFailingWithAUniqueViolation(isbnTakenAfterwards: false);

        $this->expectException(UniqueConstraintViolationException::class);

        $writer->create($this->book());
    }

    private function writerFailingWithAUniqueViolation(bool $isbnTakenAfterwards): BookWriter
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')
            ->willThrowException(new UniqueConstraintViolationException(self::createStub(DriverException::class), null));

        $bookRepository = self::createStub(BookRepository::class);
        $bookRepository->method('isbnExists')->willReturnOnConsecutiveCalls(false, $isbnTakenAfterwards);

        return new BookWriter(
            $entityManager,
            $bookRepository,
            self::createStub(AuthorRepository::class),
            self::createStub(BookAuthorRepository::class),
        );
    }

    private function book(): CreateBook
    {
        return new CreateBook('Clean Architecture', '9780134494166', 'A guide', '29.99', 'Software', '2017-09-10');
    }
}
