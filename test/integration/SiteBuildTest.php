<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_filter;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use function glob;
use function implode;
use function is_array;
use function is_file;
use function str_contains;
use function trim;

/**
 * T040: `build` renders every page of every version of the fixture organisation.
 *
 * The fixture set is built once. The exit code, the per-project page counts, the published tree, and
 * the contents of one rendered page are asserted on that single run, because the acceptance
 * condition for the fixture set is that the build reports the broken and Markdown-only projects and
 * exits 1 while still publishing everything that did render.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(ContentWriter::class)]
#[CoversClass(PageRenderer::class)]
final class SiteBuildTest extends TestCase
{
    use TempDirectoryTrait;

    /** @var list<string> */
    private array $stdout = [];

    /** @var list<string> */
    private array $stderr = [];

    private ExitCode $exitCode;

    private string $output;

    public function testAPublishedPageCarriesTheShellAndTheRewrittenLink(): void
    {
        $html = (string) file_get_contents(Path::join($this->output, 'versioned', 'v1', 'index.html'));

        self::assertStringContainsString('<article class="doc">', $html);
        self::assertStringContainsString('Welcome to version 1.', $html);
        self::assertStringContainsString('href="/versioned/v1/reference/nested.html"', $html);
        self::assertStringContainsString('data-theme-mode="system"', $html);
        self::assertStringNotContainsString('stylesheet!', $html);
        self::assertStringNotContainsString('C:\\', $html);
    }

    public function testTheBuildPrintsOnePageCountPerProject(): void
    {
        $summary = $this->summary();

        self::assertContains('versioned: 5 page(s)', $summary);
        self::assertContains('unversioned: 1 page(s)', $summary);
        self::assertContains('mixed: 2 page(s)', $summary);
        self::assertContains('broken-include: 0 page(s)', $summary);
        self::assertContains('markdown-only: 0 page(s)', $summary);
        self::assertContains('missing-entry: 0 page(s)', $summary);
    }

    public function testTheBuildPublishesEveryPageOfEveryVersion(): void
    {
        foreach ([
            'versioned/v1/index.html',
            'versioned/v1/guide.html',
            'versioned/v1/reference/nested.html',
            'versioned/v2/index.html',
            'versioned/v2/guide.html',
            'unversioned/latest/index.html',
            'mixed/v1/index.html',
            'mixed/v2/index.html',
        ] as $path) {
            self::assertTrue(
                is_file(Path::join($this->output, ...explode('/', $path))),
                "{$path} was not published.",
            );
        }
    }

    public function testTheBuildWritesTheSharedAssetsOnce(): void
    {
        self::assertCount(1, $this->assetsMatching('site.*.css'));
        self::assertCount(1, $this->assetsMatching('theme.*.js'));
        self::assertNotSame([], $this->assetsMatching('accent.*.css'));
    }

    public function testTheFixtureSetExitsDegradedBecauseTwoProjectsFail(): void
    {
        self::assertSame(ExitCode::Degraded, $this->exitCode);
        self::assertSame(1, $this->exitCode->value);

        $errors = implode("\n", $this->errors());

        self::assertStringContainsString('render-failed', $errors);
        self::assertStringContainsString('no-asciidoc-sources', $errors);
        self::assertStringContainsString('missing-entry', $errors);
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->output = Path::join($this->tempDirectory, 'site');

        $this->exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : $this->output,
                cache   : Path::join($this->tempDirectory, 'cache'),
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(
                stdout: function (string $line): void {
                    $this->stdout[] = trim($line);
                },
                stderr: function (string $line): void {
                    $this->stderr[] = trim($line);
                },
            ),
        );
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return list<string>
     */
    private function assetsMatching(string $pattern): array
    {
        $matches = glob(Path::join($this->output, 'assets', $pattern));

        return is_array($matches) ? $matches : [];
    }

    /**
     * @return list<string>
     */
    private function errors(): array
    {
        return array_values(array_filter(
            $this->stderr,
            static fn(string $line): bool => str_contains($line, '('),
        ));
    }

    private function fixtureConfig(): string
    {
        return Path::join(dirname(__DIR__, 2), 'test', 'fixtures', 'site.json');
    }

    private function fixtureManifest(): string
    {
        return Path::join(dirname(__DIR__, 2), 'test', 'fixtures', 'manifest.json');
    }

    /**
     * @return list<string>
     */
    private function summary(): array
    {
        return $this->stdout;
    }
}
