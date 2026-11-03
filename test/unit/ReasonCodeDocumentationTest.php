<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function json_decode;
use function preg_match_all;
use function sort;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const PREG_PATTERN_ORDER;

/**
 * T078: the troubleshooting guide documents every reason code the report can emit.
 *
 * The expected set is read from the report contract, never from a list written here, so adding a
 * code to the schema without documenting it fails this test.
 */
#[CoversNothing]
final class ReasonCodeDocumentationTest extends TestCase
{
    #[Test]
    public function theGuideDocumentsExactlyTheReasonCodesTheContractAllows(): void
    {
        $expected   = $this->contractReasonCodes();
        $documented = $this->documentedReasonCodes();

        sort($expected);
        sort($documented);

        static::assertSame(
            $expected,
            $documented,
            sprintf(
                'docs/v1/troubleshooting.adoc must document exactly the reason codes in %s.',
                'contracts/report.schema.json',
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function contractReasonCodes(): array
    {
        $schema = json_decode(
            (string) file_get_contents("{$this->root()}/contracts/report.schema.json"),
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );

        $codes = $schema['$defs']['buildError']['properties']['reasonCode']['enum'] ?? null;

        static::assertIsArray($codes, 'The report contract must declare a reasonCode enum.');

        $result = [];

        foreach ($codes as $code) {
            static::assertIsString($code);
            $result[] = $code;
        }

        static::assertNotSame([], $result);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function documentedReasonCodes(): array
    {
        $guide = (string) file_get_contents("{$this->root()}/docs/v1/troubleshooting.adoc");

        preg_match_all('/^\| `([a-z][a-z-]+)`[ \t]*\r?$/m', $guide, $matches, PREG_PATTERN_ORDER);

        $codes = $matches[1] ?? [];

        static::assertIsArray($codes);
        static::assertNotSame([], $codes, 'The guide must document the reason codes in a table.');

        return $codes;
    }

    private function root(): string
    {
        return dirname(
            path  : __DIR__,
            levels: 2,
        );
    }
}
