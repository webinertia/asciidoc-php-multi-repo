<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;

#[CoversClass(SourceException::class)]
#[CoversMethod(SourceException::class, 'error')]
final class SourceExceptionTest extends TestCase
{
    #[Test]
    public function thePreviousExceptionIsKept(): void
    {
        $previous  = new RuntimeException('transport failed');
        $exception = new SourceException(
            new SourceError(ReasonCode::FetchFailed, 'The repository could not be fetched.'),
            $previous,
        );

        static::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function theReportableErrorIsCarried(): void
    {
        $error     = new SourceError(ReasonCode::MissingEntry, 'No entry document.', 'latest', 'index.adoc');
        $exception = new SourceException($error);

        static::assertSame($error, $exception->error());
        static::assertSame('No entry document.', $exception->getMessage());
        static::assertSame(0, $exception->getCode());
        static::assertNull($exception->getPrevious());
    }
}
