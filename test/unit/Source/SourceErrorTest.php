<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
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
    public function testEveryDetailIsAvailable(): void
    {
        $error = new SourceError(
            ReasonCode::MissingEntry,
            'example: docs has no entry page.',
            'v1',
            'docs/v1',
            'Add an index.adoc.',
        );

        self::assertSame(ReasonCode::MissingEntry, $error->reasonCode());
        self::assertSame('example: docs has no entry page.', $error->message());
        self::assertSame('v1', $error->version());
        self::assertSame('docs/v1', $error->path());
        self::assertSame('Add an index.adoc.', $error->correctiveAction());
    }

    public function testTheArrayKeepsEveryKeyWhenTheDetailsAreAbsent(): void
    {
        self::assertSame(
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

    public function testTheArrayShapeMatchesTheReportSchema(): void
    {
        $error = new SourceError(ReasonCode::InvalidEncoding, 'bad', 'v2', 'docs/v2/a.adoc', 'Re-save it.');

        self::assertSame(
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

    public function testTheExceptionCarriesTheErrorItWasBuiltFrom(): void
    {
        $error     = new SourceError(ReasonCode::FetchFailed, 'example: fetch failed.');
        $exception = new SourceException($error);

        self::assertSame($error, $exception->error());
        self::assertSame('example: fetch failed.', $exception->getMessage());
    }

    public function testTheExceptionMessageIsTheErrorMessage(): void
    {
        $error = new SourceError(ReasonCode::NoAsciidocSources, 'example: nothing to render.');

        self::assertSame($error->message(), new SourceException($error)->getMessage());
    }

    public function testTheOptionalDetailsDefaultToNull(): void
    {
        $error = new SourceError(ReasonCode::Archived, 'example: archived.');

        self::assertNull($error->version());
        self::assertNull($error->path());
        self::assertNull($error->correctiveAction());
    }
}
