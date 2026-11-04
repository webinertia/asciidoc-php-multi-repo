<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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

use function dirname;
use function file_get_contents;
use function implode;
use function is_file;
use function trim;

/**
 * T077: the package's own documentation is AsciiDoc under docs/v1 and renders through this package.
 *
 * The repository root carries the same site.json and manifest.json a consumer would write, with the
 * manifest pointing at this checkout. The build here renders that tree into a scratch directory and
 * asserts what the acceptance criterion asks for: the package name appears, and a link to another
 * rendered page resolves to a file this build produced.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(ContentWriter::class)]
#[CoversClass(PageRenderer::class)]
final class DogfoodDocumentationTest extends TestCase
{
    use TempDirectoryTrait;

    private const string SLUG = 'asciidoc-php-multi-repo';

    private const array PAGES = [
        'index.html',
        'authoring.html',
        'action-setup.html',
        'theming.html',
        'troubleshooting.html',
    ];

    /** @var list<string> */
    private array $stdout = [];

    /** @var list<string> */
    private array $stderr = [];

    private ExitCode $exitCode;

    private string $output;

    #[Test]
    public function thePackageDocumentationRendersThroughThePackage(): void
    {
        static::assertSame(ExitCode::Success, $this->exitCode);
        static::assertStringContainsString(
            self::SLUG . ': 5 page(s) ok',
            implode("\n", $this->stderr),
        );

        foreach (self::PAGES as $page) {
            static::assertTrue(
                is_file(Path::join($this->output, self::SLUG, 'v1', $page)),
                "docs/v1 did not render {$page}.",
            );
        }
    }

    #[Test]
    public function theRenderedEntryPageLinksToAnotherRenderedPage(): void
    {
        $html = $this->entryPage();
        $link = 'href="/' . self::SLUG . '/v1/authoring.html"';

        static::assertStringContainsString($link, $html);
        static::assertTrue(
            is_file(Path::join($this->output, self::SLUG, 'v1', 'authoring.html')),
            'The linked page was not rendered.',
        );
    }

    #[Test]
    public function theRenderedEntryPageNamesThePackage(): void
    {
        static::assertStringContainsString(self::SLUG, $this->entryPage());
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();
        $this->output = Path::join($this->tempDirectory, 'site');

        $root = dirname(
            path  : __DIR__,
            levels: 2,
        );

        $this->exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: Path::join($root, 'manifest.json'),
                out     : $this->output,
                cache   : Path::join($this->tempDirectory, 'cache'),
            ),
            new SiteConfigLoader()->load(Path::join($root, 'site.json')),
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

    private function entryPage(): string
    {
        return (string) file_get_contents(Path::join($this->output, self::SLUG, 'v1', 'index.html'));
    }
}
