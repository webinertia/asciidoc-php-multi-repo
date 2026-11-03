<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Build\IncrementalCache;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Render\PageRenderer;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function file_get_contents;
use function file_put_contents;

/**
 * What a project-level setting may change without a page being converted again.
 *
 * A cached page carries its own rendered HTML, so anything the conversion bakes in has to be part
 * of the cache key: the project title appears in the page heading and the footer, and the slug
 * decides where the page is written and how it links to itself. A setting that only reaches the
 * page through the chrome the shell re-renders, or through an asset the key already names, needs no
 * entry of its own.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(BuildRunner::class)]
#[CoversClass(IncrementalCache::class)]
#[CoversClass(PageRenderer::class)]
final class ProjectCacheTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function aChangedProjectSlugIsNotServedFromTheCache(): void
    {
        $out = $this->rebuildInto('slug-azalea', ['slug' => 'azalea']);

        static::assertFileExists(Path::join($out, 'azalea', 'v1', 'index.html'));
        static::assertFileDoesNotExist(Path::join($out, 'alpha', 'v1', 'index.html'));
    }

    #[Test]
    public function aChangedProjectTitleIsNotServedFromTheCache(): void
    {
        $ledger = $this->rebuildWith('title-ledger', ['title' => 'Alpha Ledger']);

        static::assertStringContainsString('<p class="project-name">Alpha Ledger</p>', $ledger);
        static::assertStringContainsString('<p class="footer-project">Alpha Ledger</p>', $ledger);

        $billing = $this->rebuildWith('title-billing', ['title' => 'Alpha Billing']);

        static::assertStringContainsString('<p class="project-name">Alpha Billing</p>', $billing);
        static::assertStringNotContainsString('Alpha Ledger', $billing);
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();

        new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : Path::join($this->tempDirectory, 'site'),
                cache   : Path::join($this->tempDirectory, 'cache'),
                report  : Path::join($this->tempDirectory, 'report.json'),
            ),
            new SiteConfigLoader()->load($this->fixtureConfig()),
            new Output(stderr: static function (string $line): void {}),
        );
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function fixtureConfig(): string
    {
        return Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'ordering', 'site.json');
    }

    private function fixtureManifest(): string
    {
        return Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'ordering', 'manifest.json');
    }

    /**
     * Rebuilds the fixture into the cache the test already populated, with one project override
     * applied, and returns the output directory of that build.
     *
     * @param array<string, mixed> $override
     */
    private function rebuildInto(string $label, array $override): string
    {
        $config = new Json()->decodeObject((string) file_get_contents($this->fixtureConfig()), $this->fixtureConfig());
        $alpha  = $config['projects']['alpha'] ?? [];
        static::assertIsArray($alpha);

        $config['projects']['alpha'] = $override + $alpha;

        $path = Path::join($this->tempDirectory, "{$label}.json");
        file_put_contents($path, new Json()->encode($config));

        $out = Path::join($this->tempDirectory, $label);

        new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->fixtureManifest(),
                out     : $out,
                cache   : Path::join($this->tempDirectory, 'cache'),
                report  : Path::join($this->tempDirectory, "{$label}-report.json"),
            ),
            new SiteConfigLoader()->load($path),
            new Output(stderr: static function (string $line): void {}),
        );

        return $out;
    }

    /**
     * Rebuilds the fixture into the cache the test already populated, with one project override
     * applied, and returns the resulting entry page of the alpha project.
     *
     * @param array<string, mixed> $override
     */
    private function rebuildWith(string $label, array $override): string
    {
        return (string) file_get_contents(Path::join(
            $this->rebuildInto($label, $override),
            'alpha',
            'v1',
            'index.html',
        ));
    }
}
