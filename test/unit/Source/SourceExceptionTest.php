<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;
use Webware\AsciidocPhpMultiRepo\Source\SourceError;
use Webware\AsciidocPhpMultiRepo\Source\SourceException;

#[CoversClass(SourceException::class)]
#[CoversMethod(SourceException::class, 'error')]
final class SourceExceptionTest extends TestCase
{
    public function testThePreviousExceptionIsKept(): void
    {
        $previous  = new RuntimeException('transport failed');
        $exception = new SourceException(
            new SourceError(ReasonCode::FetchFailed, 'The repository could not be fetched.'),
            $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }

    public function testTheReportableErrorIsCarried(): void
    {
        $error     = new SourceError(ReasonCode::MissingEntry, 'No entry document.', 'latest', 'index.adoc');
        $exception = new SourceException($error);

        self::assertSame($error, $exception->error());
        self::assertSame('No entry document.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }
}
