<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo\Onboarding;

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
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function json_encode;
use function sha1;
use function trim;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * T072: onboarding is a manifest entry plus the onboard value, and nothing else.
 *
 * The site configuration used here is the fixture configuration, byte for byte. It names neither
 * repository. One repository is added to the manifest with the onboard value set and is published;
 * the other is added with the onboard value cleared and is skipped without failing the build. That
 * pair proves the switch is the onboard value rather than any per-repository configuration.
 *
 * This test lives under test/integration because it composes discovery, assembly and rendering.
 * The repository has no test/Onboarding directory and does not need one: the namespace follows the
 * directory, so the suite is WebwareTestIntegration\AsciidocPhp\MultiRepo\Onboarding.
 */
#[CoversClass(BuildCommand::class)]
#[CoversClass(ContentWriter::class)]
#[CoversClass(PageRenderer::class)]
final class OnboardingTest extends TestCase
{
    use TempDirectoryTrait;

    private const string ONBOARDED = 'onboarded-repo';

    private const string NOT_ONBOARDED = 'not-onboarded-repo';

    /** @var list<string> */
    private array $stdout = [];

    /** @var list<string> */
    private array $stderr = [];

    private string $configPath;

    private string $configBefore;

    private ExitCode $exitCode;

    private string $output;

    #[Test]
    public function anEntryWithoutTheOnboardValueIsSkippedRatherThanPublished(): void
    {
        static::assertFalse(
            is_dir(Path::join($this->output, self::NOT_ONBOARDED)),
            'A repository without the onboard value must not be published.',
        );
        static::assertStringContainsString(
            self::NOT_ONBOARDED . ': not onboarded, skipped',
            implode("\n", $this->stderr),
        );
    }

    #[Test]
    public function anOnboardedManifestEntryIsPublished(): void
    {
        $page = Path::join($this->output, self::ONBOARDED, 'v1', 'index.html');

        static::assertTrue(is_file($page), 'The onboarded repository was not published.');
        static::assertStringContainsString(
            'This page is published because the manifest entry is onboarded.',
            (string) file_get_contents($page),
        );
        static::assertStringContainsString(
            self::ONBOARDED . ': 1 page(s) ok',
            implode("\n", $this->stderr),
        );
    }

    #[Test]
    public function theBuildSucceedsBecauseSkippingIsNotAFailure(): void
    {
        static::assertSame(ExitCode::Success, $this->exitCode);
        static::assertSame(0, $this->exitCode->value);
    }

    #[Test]
    public function theSiteConfigurationIsUnchangedByOnboarding(): void
    {
        static::assertSame($this->configBefore, sha1((string) file_get_contents($this->configPath)));

        $config = (string) file_get_contents($this->configPath);

        static::assertStringNotContainsString(self::ONBOARDED, $config);
        static::assertStringNotContainsString(self::NOT_ONBOARDED, $config);
    }

    protected function setUp(): void
    {
        $this->createTempDirectory();

        $this->configPath = Path::join(
            dirname(
                path  : __DIR__,
                levels: 3,
            ),
            'test',
            'fixtures',
            'site.json',
        );
        $this->configBefore = sha1((string) file_get_contents($this->configPath));
        $this->output       = Path::join($this->tempDirectory, 'site');

        $this->writePage(self::ONBOARDED);
        $this->writePage(self::NOT_ONBOARDED);

        $this->exitCode = new BuildCommand()->execute(
            new Options(
                command : 'build',
                manifest: $this->writeManifest(),
                out     : $this->output,
                cache   : Path::join($this->tempDirectory, 'cache'),
                verbose : true,
            ),
            new SiteConfigLoader()->load($this->configPath),
            new Output(
                verbose: true,
                stdout : function (string $line): void {
                    $this->stdout[] = trim($line);
                },
                stderr : function (string $line): void {
                    $this->stderr[] = trim($line);
                },
            ),
        );
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function writeFile(string $path, string $contents): void
    {
        new Filesystem()->ensureDirectory(dirname($path));
        file_put_contents($path, $contents);
    }

    private function writeManifest(): string
    {
        $path = Path::join($this->tempDirectory, 'manifest.json');

        $this->writeFile(
            $path,
            (string) json_encode([
                'schemaVersion' => 1,
                'repositories'  => [
                    [
                        'name'          => self::ONBOARDED,
                        'slug'          => self::ONBOARDED,
                        'defaultBranch' => 'main',
                        'onboarded'     => true,
                        'path'          => 'org/' . self::ONBOARDED,
                    ],
                    [
                        'name'          => self::NOT_ONBOARDED,
                        'slug'          => self::NOT_ONBOARDED,
                        'defaultBranch' => 'main',
                        'onboarded'     => false,
                        'path'          => 'org/' . self::NOT_ONBOARDED,
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        return $path;
    }

    private function writePage(string $repository): void
    {
        $this->writeFile(
            Path::join($this->tempDirectory, 'org', $repository, 'docs', 'v1', 'index.adoc'),
            "= {$repository}\n:toclevels: 2\n\n"
                . "This page is published because the manifest entry is onboarded.\n",
        );
    }
}
