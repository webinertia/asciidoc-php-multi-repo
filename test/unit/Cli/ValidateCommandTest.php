<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\ValidateCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function json_encode;
use function putenv;

use const JSON_THROW_ON_ERROR;

/**
 * T062: `validate` answers "would this build" for every project without publishing anything.
 *
 * The healthy fixtures are reported as buildable, and each broken fixture is reported with the
 * reason code and the corrective action a real build would carry. The assertions on the output
 * directory are the point of the command: a hash of the tree before and after the run has to match,
 * and a page whose include is broken has to be caught even though nothing is written.
 */
#[CoversClass(ValidateCommand::class)]
#[CoversClass(BuildRunner::class)]
#[CoversMethod(ValidateCommand::class, 'execute')]
#[CoversMethod(ValidateCommand::class, 'name')]
#[CoversMethod(ValidateCommand::class, 'summary')]
final class ValidateCommandTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    public function testABrokenIncludeIsCaughtEvenThoughNothingIsWritten(): void
    {
        $exit = $this->validate(['broken-include']);

        self::assertSame(ExitCode::Degraded, $exit);
        self::assertStringContainsString('broken-include: partial', $this->captured->stderr);
        self::assertStringContainsString('(render-failed)', $this->captured->stderr);
        self::assertStringContainsString('Add the missing file', $this->captured->stderr);
    }

    public function testAHealthyProjectIsReportedAsBuildable(): void
    {
        $exit = $this->validate(['versioned']);

        self::assertSame(ExitCode::Success, $exit);
        self::assertStringContainsString('versioned: ok', $this->captured->stderr);
        self::assertStringContainsString('ok: 1, partial: 0, failed: 0, skipped: 0', $this->captured->stdout);
    }

    public function testAMissingEntryIsReportedWithItsReasonCodeAndCorrectiveAction(): void
    {
        $exit = $this->validate(['missing-entry']);

        self::assertSame(ExitCode::Degraded, $exit);
        self::assertStringContainsString('missing-entry: failed', $this->captured->stderr);
        self::assertStringContainsString('(missing-entry)', $this->captured->stderr);
        self::assertStringContainsString('Add one of the entry candidates', $this->captured->stderr);
    }

    public function testAnArchivedProjectIsSkippedAndDoesNotDegradeTheRun(): void
    {
        $exit = $this->validate(manifest: $this->writeManifest([
            $this->entry('legacy', SourceFixture::path('org/archived'), archived: true),
            $this->entry('versioned', SourceFixture::path('org/versioned')),
        ]));

        self::assertSame(ExitCode::Success, $exit);
        self::assertStringContainsString('legacy: skipped', $this->captured->stderr);
        self::assertStringContainsString('ok: 1, partial: 0, failed: 0, skipped: 1', $this->captured->stdout);
    }

    public function testAProjectWithoutAsciiDocSourcesIsReportedAsUnbuildable(): void
    {
        $exit = $this->validate(['markdown-only']);

        self::assertSame(ExitCode::Degraded, $exit);
        self::assertStringContainsString('(no-asciidoc-sources)', $this->captured->stderr);
    }

    public function testTheOutputDirectoryIsUntouched(): void
    {
        $out = Path::join($this->tempDirectory, 'site');
        file_put_contents(Path::join($this->tempDirectory, 'marker.txt'), 'published');

        $this->validate(['versioned'], $out);

        self::assertFalse(is_dir($out), 'validate created the output directory');
        self::assertFalse(is_file(Path::join($this->tempDirectory, 'report.json')));
        self::assertSame('published', file_get_contents(Path::join($this->tempDirectory, 'marker.txt')));
    }

    public function testTheSummarySaysNoSiteOutputWasWritten(): void
    {
        $this->validate(['versioned']);

        self::assertStringContainsString('no site output was written', $this->captured->stdout);
    }

    protected function setUp(): void
    {
        $this->captured = new CapturedOutput();

        $this->createTempDirectory();

        putenv('FIXTURE_DISCOVERY_TOKEN=ghp_fixture_token_value');
    }

    protected function tearDown(): void
    {
        putenv('FIXTURE_DISCOVERY_TOKEN');

        $this->removeTempDirectory();
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(string $name, string $path, bool $archived = false): array
    {
        return [
            'name'          => $name,
            'slug'          => $name,
            'defaultBranch' => 'main',
            'archived'      => $archived,
            'onboarded'     => true,
            'path'          => $path,
        ];
    }

    private function makeOutput(): Output
    {
        return new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );
    }

    /**
     * @param list<string> $projects
     */
    private function validate(array $projects = [], ?string $out = null, ?string $manifest = null): ExitCode
    {
        return new ValidateCommand()->execute(
            new Options(
                command : 'validate',
                projects: $projects,
                manifest: $manifest ?? SourceFixture::path('manifest.json'),
                out     : $out ?? Path::join($this->tempDirectory, 'site'),
                cache   : Path::join($this->tempDirectory, 'cache'),
                report  : Path::join($this->tempDirectory, 'report.json'),
            ),
            SourceFixture::config(),
            $this->makeOutput(),
        );
    }

    /**
     * @param list<array<string, mixed>> $repositories
     */
    private function writeManifest(array $repositories): string
    {
        $manifest = Path::join($this->tempDirectory, 'manifest.json');

        file_put_contents(
            $manifest,
            (string) json_encode([
                'schemaVersion' => 1,
                'repositories'  => $repositories,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        );

        return $manifest;
    }
}
