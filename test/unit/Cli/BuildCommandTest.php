<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\StubHttpResponse;
use WebwareTest\AsciidocPhp\MultiRepo\Support\StubHttpTransport;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function file_put_contents;
use function is_file;
use function json_encode;
use function putenv;

use const JSON_THROW_ON_ERROR;

/**
 * T040: the exit code and the report lines a build produces for one project.
 *
 * The fixture organisation is built one project at a time so each assertion is about a single
 * project's outcome: a project that rendered every page exits 0, a project with a collection error
 * and a project with a render failure both degrade the run, and the report names the page, the
 * reason code, and the source path that failed.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(ContentWriter::class)]
#[CoversMethod(BuildCommand::class, 'execute')]
#[CoversMethod(BuildCommand::class, 'name')]
#[CoversMethod(BuildCommand::class, 'summary')]
final class BuildCommandTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    public function testACollectionErrorDegradesTheRunAndNamesTheReasonCode(): void
    {
        $exit = $this->build(['missing-entry']);

        self::assertSame(ExitCode::Degraded, $exit);
        self::assertStringContainsString('missing-entry: 0 page(s)', $this->captured->stdout);
        self::assertStringContainsString('docs/v1:', $this->captured->stderr);
        self::assertStringContainsString('(missing-entry)', $this->captured->stderr);
    }

    public function testACollectorWarningIsReported(): void
    {
        $exit = $this->build(['mixed']);

        self::assertSame(ExitCode::Success, $exit);
        self::assertStringContainsString('mixed: 2 page(s)', $this->captured->stdout);
        self::assertStringContainsString('outside a version directory', $this->captured->stderr);
        self::assertStringContainsString('docs/loose.adoc', $this->captured->stderr);
    }

    public function testAnArchivedProjectDoesNotStopTheProjectsAfterIt(): void
    {
        $manifest = $this->writeManifest([
            $this->entry('legacy', SourceFixture::path('org/archived'), archived: true),
            $this->entry('versioned', SourceFixture::path('org/versioned')),
        ]);

        $exit = $this->build([], manifest: $manifest);

        self::assertSame(ExitCode::Success, $exit);
        self::assertStringContainsString('legacy: archived, skipped', $this->captured->stderr);
        self::assertStringContainsString('versioned: 5 page(s)', $this->captured->stdout);
    }

    public function testAnArchivedProjectIsSkippedWithAWarning(): void
    {
        $manifest = $this->writeManifest([
            $this->entry('legacy', SourceFixture::path('org/archived'), archived: true),
        ]);

        $exit = $this->build(['legacy'], manifest: $manifest);

        self::assertSame(ExitCode::Success, $exit);
        self::assertStringContainsString('legacy: archived, skipped', $this->captured->stderr);
        self::assertStringNotContainsString('page(s)', $this->captured->stdout);
    }

    public function testAnUnauthorisedDiscoveryExitsThree(): void
    {
        $exit = $this->discover($this->unauthorisedTransport());

        self::assertSame(ExitCode::DiscoveryError, $exit);
        self::assertStringContainsString('error: ', $this->captured->stderr);
        self::assertStringContainsString('401', $this->captured->stderr);
        self::assertSame('', $this->captured->stdout);
    }

    public function testAProjectThatRenderedEveryPageExitsSuccess(): void
    {
        $exit = $this->build(['versioned']);

        self::assertSame(ExitCode::Success, $exit);
        self::assertSame(0, $exit->value);
        self::assertStringContainsString('versioned: 5 page(s)', $this->captured->stdout);
        self::assertStringContainsString('output directory: ', $this->captured->stdout);
        self::assertSame('', $this->captured->stderr);
    }

    public function testARenderFailureDegradesTheRunAndNamesThePage(): void
    {
        $exit = $this->build(['broken-include']);

        self::assertSame(ExitCode::Degraded, $exit);
        self::assertStringContainsString('broken-include: 0 page(s)', $this->captured->stdout);
        self::assertStringContainsString('docs/v1/index.adoc:', $this->captured->stderr);
        self::assertStringContainsString('(render-failed)', $this->captured->stderr);
    }

    public function testARenderFailureDoesNotStopThePagesAfterIt(): void
    {
        $repository = Path::join($this->tempDirectory, 'local');
        new Filesystem()->ensureDirectory(Path::join($repository, 'docs', 'v1'));

        file_put_contents(
            Path::join($repository, 'docs', 'v1', 'a-broken.adoc'),
            "= Broken\n\ninclude::missing.adoc[]\n",
        );
        file_put_contents(Path::join($repository, 'docs', 'v1', 'index.adoc'), "= Index\n\nText.\n");

        $manifest = $this->writeManifest([$this->entry('local', $repository)]);

        $exit = $this->build(['local'], manifest: $manifest);

        self::assertSame(ExitCode::Degraded, $exit);
        self::assertStringContainsString('a-broken.adoc:', $this->captured->stderr);
        self::assertStringContainsString('local: 1 page(s)', $this->captured->stdout);
        self::assertTrue(is_file(Path::join($this->tempDirectory, 'site', 'local', 'v1', 'index.html')));
    }

    public function testASlugCollisionExitsTwo(): void
    {
        $exit = $this->discover($this->collidingTransport());

        self::assertSame(ExitCode::ConfigError, $exit);
        self::assertStringContainsString('error: ', $this->captured->stderr);
        self::assertStringContainsString('message-bus', $this->captured->stderr);
        self::assertSame('', $this->captured->stdout);
    }

    public function testTheCommandNameAndSummaryAreSet(): void
    {
        $command = new BuildCommand();

        self::assertSame('build', $command->name());
        self::assertNotSame('', $command->summary());
    }

    public function testTheVerboseOutputNamesEveryRenderedPage(): void
    {
        $this->build(['mixed'], verbose: true);

        self::assertStringContainsString('mixed: rendered mixed/v1/index.html', $this->captured->stderr);
        self::assertStringContainsString('mixed: rendered mixed/v2/index.html', $this->captured->stderr);
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
     * @param list<string> $projects
     */
    private function build(array $projects, bool $verbose = false, ?string $manifest = null): ExitCode
    {
        return new BuildCommand()->execute(
            new Options(
                command : 'build',
                projects: $projects,
                manifest: $manifest ?? SourceFixture::path('manifest.json'),
                out     : Path::join($this->tempDirectory, 'site'),
                cache   : Path::join($this->tempDirectory, 'cache'),
                verbose : $verbose,
            ),
            $this->config(),
            $this->makeOutput($verbose),
        );
    }

    private function collidingTransport(): StubHttpTransport
    {
        return new StubHttpTransport([
            'properties/values'   => StubHttpResponse::create(
                200,
                (string) json_encode([
                    [
                        'repository_full_name' => 'fixture-org/message-bus',
                        'properties'           => [
                            ['property_name' => 'docs', 'value' => 'true'],
                        ],
                    ],
                    [
                        'repository_full_name' => 'fixture-org/message.bus',
                        'properties'           => [
                            ['property_name' => 'docs', 'value' => 'true'],
                        ],
                    ],
                ]),
            ),
            'search/repositories' => StubHttpResponse::create(200, (string) json_encode(['items' => []])),
        ]);
    }

    private function config(): SiteConfig
    {
        return SourceFixture::config();
    }

    private function discover(StubHttpTransport $transport): ExitCode
    {
        return new BuildCommand(new DiscoveryFactory(transport: $transport))->execute(
            new Options(
                command: 'build',
                out    : Path::join($this->tempDirectory, 'site'),
                cache  : Path::join($this->tempDirectory, 'cache'),
            ),
            $this->config(),
            $this->makeOutput(),
        );
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
            'path'          => $path,
        ];
    }

    private function makeOutput(bool $verbose = false): Output
    {
        return new Output(
            verbose: $verbose,
            stdout : $this->captured->stdoutWriter(),
            stderr : $this->captured->stderrWriter(),
        );
    }

    private function unauthorisedTransport(): StubHttpTransport
    {
        return new StubHttpTransport([
            'properties/values' => StubHttpResponse::create(401, '{"message":"Bad credentials"}'),
        ]);
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
            ], JSON_THROW_ON_ERROR),
        );

        return $manifest;
    }
}
