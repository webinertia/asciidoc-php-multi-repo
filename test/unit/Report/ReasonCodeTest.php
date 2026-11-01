<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;
use Webware\AsciidocPhpMultiRepo\Support\Json;
use Webware\AsciidocPhpMultiRepo\Support\Path;

use function array_map;
use function array_unique;
use function array_values;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;
use function sort;

/**
 * Pins the reason code enum to `contracts/report.schema.json`.
 *
 * Adding a case without publishing it in the schema, or the reverse, fails here. That is the point:
 * the report contract is external, so the code may not grow a reason nobody downstream knows.
 */
#[CoversClass(ReasonCode::class)]
#[CoversMethod(ReasonCode::class, 'label')]
final class ReasonCodeTest extends TestCase
{
    public function testEnumMatchesThePublishedSchemaExactly(): void
    {
        $published = $this->publishedCodes();
        $codes     = array_map(static fn(ReasonCode $code): string => $code->value, ReasonCode::cases());

        sort($published);
        sort($codes);

        self::assertSame(
            $published,
            $codes,
            'src/Report/ReasonCode.php and contracts/report.schema.json have drifted apart.',
        );
    }

    public function testEveryCaseIsReachableFromItsValue(): void
    {
        foreach (ReasonCode::cases() as $case) {
            self::assertSame($case, ReasonCode::from($case->value));
        }
    }

    public function testLabelIsTheSchemaValue(): void
    {
        foreach (ReasonCode::cases() as $case) {
            self::assertSame($case->value, $case->label());
        }
    }

    public function testTheEnumCarriesNoDuplicateValue(): void
    {
        $codes = array_map(static fn(ReasonCode $code): string => $code->value, ReasonCode::cases());

        self::assertSame($codes, array_values(array_unique($codes)));
    }

    public function testTheSchemaEnumIsNonEmpty(): void
    {
        self::assertNotSame([], $this->publishedCodes());
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string>            $codes
     */
    private function collectEnum(array $node, array &$codes): void
    {
        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if ('reasonCode' === $key) {
                $this->collectReasonCodeEnum($value, $codes);

                continue;
            }

            $this->collectEnum($value, $codes);
        }
    }

    /**
     * @param array<array-key, mixed> $definition
     * @param list<string>            $codes
     */
    private function collectReasonCodeEnum(array $definition, array &$codes): void
    {
        $enum = $definition['enum'] ?? null;

        if (! is_array($enum)) {
            return;
        }

        foreach ($enum as $value) {
            if (! is_string($value) || in_array($value, $codes, strict: true)) {
                continue;
            }

            $codes[] = $value;
        }
    }

    /**
     * @return list<string>
     */
    private function publishedCodes(): array
    {
        $path = Path::join(
            dirname(
                path  : __DIR__,
                levels: 3,
            ),
            'contracts',
            'report.schema.json',
        );
        $json = file_get_contents($path);

        self::assertIsString($json, "Unable to read {$path}");

        $schema = new Json()->decode($json);
        $codes  = [];

        self::assertIsArray($schema);

        $this->collectEnum($schema, $codes);

        self::assertNotSame([], $codes, 'No reasonCode enum was found in contracts/report.schema.json.');

        return $codes;
    }
}
