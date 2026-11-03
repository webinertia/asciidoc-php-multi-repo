<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;

#[CoversClass(SourceError::class)]
#[CoversMethod(SourceError::class, 'correctiveAction')]
#[CoversMethod(SourceError::class, 'message')]
#[CoversMethod(SourceError::class, 'path')]
#[CoversMethod(SourceError::class, 'reasonCode')]
#[CoversMethod(SourceError::class, 'toArray')]
#[CoversMethod(SourceError::class, 'version')]
#[CoversClass(SourceException::class)]
#[CoversMethod(SourceException::class, 'error')]
final class SourceErrorTest extends TestCase
{
    #[Test]
    public function everyDetailIsAvailable(): void
    {
        $error = new SourceError(
            ReasonCode::MissingEntry,
            'example: docs has no entry page.',
            'v1',
            'docs/v1',
            'Add an index.adoc.',
        );

        static::assertSame(ReasonCode::MissingEntry, $error->reasonCode());
        static::assertSame('example: docs has no entry page.', $error->message());
        static::assertSame('v1', $error->version());
        static::assertSame('docs/v1', $error->path());
        static::assertSame('Add an index.adoc.', $error->correctiveAction());
    }

    #[Test]
    public function theArrayKeepsEveryKeyWhenTheDetailsAreAbsent(): void
    {
        static::assertSame(
            [
                'reasonCode'       => 'archived',
                'message'          => 'example: archived.',
                'version'          => null,
                'path'             => null,
                'correctiveAction' => null,
            ],
            new SourceError(ReasonCode::Archived, 'example: archived.')->toArray(),
        );
    }

    #[Test]
    public function theArrayShapeMatchesTheReportSchema(): void
    {
        $error = new SourceError(ReasonCode::InvalidEncoding, 'bad', 'v2', 'docs/v2/a.adoc', 'Re-save it.');

        static::assertSame(
            [
                'reasonCode'       => 'invalid-encoding',
                'message'          => 'bad',
                'version'          => 'v2',
                'path'             => 'docs/v2/a.adoc',
                'correctiveAction' => 'Re-save it.',
            ],
            $error->toArray(),
        );
    }

    #[Test]
    public function theExceptionCarriesTheErrorItWasBuiltFrom(): void
    {
        $error     = new SourceError(ReasonCode::FetchFailed, 'example: fetch failed.');
        $exception = new SourceException($error);

        static::assertSame($error, $exception->error());
        static::assertSame('example: fetch failed.', $exception->getMessage());
    }

    #[Test]
    public function theExceptionMessageIsTheErrorMessage(): void
    {
        $error = new SourceError(ReasonCode::NoAsciidocSources, 'example: nothing to render.');

        static::assertSame($error->message(), new SourceException($error)->getMessage());
    }

    #[Test]
    public function theOptionalDetailsDefaultToNull(): void
    {
        $error = new SourceError(ReasonCode::Archived, 'example: archived.');

        static::assertNull($error->version());
        static::assertNull($error->path());
        static::assertNull($error->correctiveAction());
    }
}
