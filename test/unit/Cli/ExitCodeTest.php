<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;

#[CoversClass(ExitCode::class)]
final class ExitCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, ExitCode}>
     */
    public static function statusCombinations(): iterable
    {
        yield 'no projects' => [[], ExitCode::Success];
        yield 'single ok' => [['ok'], ExitCode::Success];
        yield 'single skipped' => [['skipped'], ExitCode::Success];
        yield 'all skipped' => [['skipped', 'skipped', 'skipped'], ExitCode::Success];
        yield 'ok and skipped' => [['ok', 'skipped', 'ok'], ExitCode::Success];
        yield 'single partial' => [['partial'], ExitCode::Degraded];
        yield 'single failed' => [['failed'], ExitCode::Degraded];
        yield 'partial with ok' => [['ok', 'partial'], ExitCode::Degraded];
        yield 'failed with skipped' => [['skipped', 'failed'], ExitCode::Degraded];
        yield 'every status' => [['ok', 'partial', 'failed', 'skipped'], ExitCode::Degraded];
        yield 'unknown status degrades' => [['unknown'], ExitCode::Degraded];
    }

    #[Test]
    public function documentedValues(): void
    {
        self::assertSame(0, ExitCode::Success->value);
        self::assertSame(1, ExitCode::Degraded->value);
        self::assertSame(2, ExitCode::ConfigError->value);
        self::assertSame(3, ExitCode::DiscoveryError->value);
        self::assertSame(4, ExitCode::InternalError->value);
    }

    #[Test]
    public function fromBuildReportDegradesOnAFailedProject(): void
    {
        $report = [
            'projects' => [
                ['repository' => 'a', 'status' => 'ok'],
                ['repository' => 'b', 'status' => 'failed'],
            ],
        ];

        self::assertSame(ExitCode::Degraded, ExitCode::fromBuildReport($report));
    }

    #[Test]
    public function fromBuildReportIgnoresEntriesWithoutAStatus(): void
    {
        $report = [
            'projects' => [
                ['repository' => 'a'],
                ['repository' => 'b', 'status' => 'ok'],
            ],
        ];

        self::assertSame(ExitCode::Success, ExitCode::fromBuildReport($report));
    }

    #[Test]
    public function fromBuildReportReadsProjectStatuses(): void
    {
        $report = [
            'schemaVersion' => 1,
            'projects'      => [
                ['repository' => 'a', 'status' => 'ok'],
                ['repository' => 'b', 'status' => 'skipped'],
            ],
        ];

        self::assertSame(ExitCode::Success, ExitCode::fromBuildReport($report));
    }

    #[Test]
    public function fromBuildReportToleratesAMissingProjectList(): void
    {
        self::assertSame(ExitCode::Success, ExitCode::fromBuildReport([]));
    }

    /**
     * @param list<string> $statuses
     */
    #[Test]
    #[DataProvider('statusCombinations')]
    public function fromProjectStatuses(array $statuses, ExitCode $expected): void
    {
        self::assertSame($expected, ExitCode::fromProjectStatuses(...$statuses));
    }

    #[Test]
    public function labelsMatchTheContractNames(): void
    {
        self::assertSame('Success', ExitCode::Success->label());
        self::assertSame('Degraded', ExitCode::Degraded->label());
        self::assertSame('ConfigError', ExitCode::ConfigError->label());
        self::assertSame('DiscoveryError', ExitCode::DiscoveryError->label());
        self::assertSame('InternalError', ExitCode::InternalError->label());
    }
}
