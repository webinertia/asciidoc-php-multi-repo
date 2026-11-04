<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use RuntimeException;
use Webware\AsciidocPhp\Converter\Html5Converter;
use Webware\AsciidocPhp\MultiRepo\Config\ThemeConfig;
use Webware\AsciidocPhp\MultiRepo\Theme\AssetHasher;
use Webware\AsciidocPhp\MultiRepo\Theme\ThemeResolver;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function str_contains;
use function str_repeat;
use function str_replace;

/**
 * T036: the token set, the accent rule, and the published asset names.
 *
 * The resolver reads the tokens out of the stylesheet rather than restating them, so these tests
 * assert against what actually ships. The hash test edits a real copy of `site.css` by one byte and
 * requires the published name to change.
 */
#[CoversClass(ThemeResolver::class)]
#[CoversClass(AssetHasher::class)]
#[CoversMethod(ThemeResolver::class, 'accent')]
#[CoversMethod(ThemeResolver::class, 'accentCss')]
#[CoversMethod(ThemeResolver::class, 'accentName')]
#[CoversMethod(ThemeResolver::class, 'assetUrl')]
#[CoversMethod(ThemeResolver::class, 'baseCss')]
#[CoversMethod(ThemeResolver::class, 'baseName')]
#[CoversMethod(ThemeResolver::class, 'colorMode')]
#[CoversMethod(ThemeResolver::class, 'config')]
#[CoversMethod(ThemeResolver::class, 'darkColors')]
#[CoversMethod(ThemeResolver::class, 'defaultColorMode')]
#[CoversMethod(ThemeResolver::class, 'hasher')]
#[CoversMethod(ThemeResolver::class, 'lightColors')]
#[CoversMethod(ThemeResolver::class, 'logo')]
#[CoversMethod(ThemeResolver::class, 'logoName')]
#[CoversMethod(ThemeResolver::class, 'logoUrl')]
#[CoversMethod(ThemeResolver::class, 'navScript')]
#[CoversMethod(ThemeResolver::class, 'navScriptName')]
#[CoversMethod(ThemeResolver::class, 'searchCore')]
#[CoversMethod(ThemeResolver::class, 'searchCoreName')]
#[CoversMethod(ThemeResolver::class, 'searchScript')]
#[CoversMethod(ThemeResolver::class, 'searchScriptName')]
#[CoversMethod(ThemeResolver::class, 'siteCss')]
#[CoversMethod(ThemeResolver::class, 'siteName')]
#[CoversMethod(ThemeResolver::class, 'themeScript')]
#[CoversMethod(ThemeResolver::class, 'themeScriptName')]
#[CoversMethod(AssetHasher::class, 'hash')]
#[CoversMethod(AssetHasher::class, 'hashedName')]
final class ThemeResolverTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function aMalformedTokenBlockYieldsNoTokens(): void
    {
        static::assertSame([], $this->lightColorsFrom(':root {'));
        static::assertSame([], $this->lightColorsFrom(":root {\n    color: red;\n}\n"));
    }

    #[Test]
    public function aMissingMarkIsReported(): void
    {
        $directory = $this->createTempDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Asset \"logo.svg\" is missing from \"{$directory}\".");

        new ThemeResolver(imgDirectory: $directory)->logo();
    }

    #[Test]
    public function aMissingStylesheetIsReported(): void
    {
        $directory = $this->createTempDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Asset \"site.css\" is missing from \"{$directory}\".");

        new ThemeResolver(cssDirectory: $directory)->lightColors();
    }

    #[Test]
    public function anUnterminatedTokenBlockYieldsNoTokens(): void
    {
        $css = ":root {\n    --color-link: #6a49c9;\n" . str_repeat("    --padding-token: 1;\n", times: 8);

        static::assertSame([], $this->lightColorsFrom($css));
    }

    #[Test]
    public function assetUrlsCarryTheBasePath(): void
    {
        $resolver = new ThemeResolver();

        static::assertSame('/assets/site.css', $resolver->assetUrl('/', 'site.css'));
        static::assertSame('/docs/assets/site.css', $resolver->assetUrl('/docs/', 'site.css'));
        static::assertSame('/docs/assets/site.css', $resolver->assetUrl('/docs', 'site.css'));
    }

    #[Test]
    public function aStylesheetWithoutTheSelectorYieldsNoTokens(): void
    {
        static::assertSame([], $this->lightColorsFrom("body {\n    --color-link: #6a49c9;\n}\n"));
    }

    #[Test]
    public function oneChangedByteChangesThePublishedName(): void
    {
        $directory = $this->createTempDirectory();

        file_put_contents(
            "{$directory}/site.css",
            file_get_contents(
                dirname(__DIR__, levels: 3) . '/resources/css/site.css',
            ),
        );
        file_put_contents(
            "{$directory}/print.css",
            file_get_contents(
                dirname(__DIR__, levels: 3) . '/resources/css/print.css',
            ),
        );

        $before = new ThemeResolver(cssDirectory: $directory)->siteName();

        $css = (string) file_get_contents("{$directory}/site.css");
        file_put_contents("{$directory}/site.css", str_replace(
            '--surface-page: #ffffff;',
            replace: '--surface-page: #fffffe;',
            subject: $css,
        ));

        $after = new ThemeResolver(cssDirectory: $directory)->siteName();

        static::assertNotSame($before, $after);
        static::assertStringStartsWith('site.', $before);
        static::assertStringEndsWith('.css', $before);
    }

    #[Test]
    public function theAccentIsValidatedAndFallsBack(): void
    {
        $resolver = new ThemeResolver();

        static::assertSame(ThemeConfig::DEFAULT_ACCENT, $resolver->accent());
        static::assertSame('#123abc', $resolver->accent('#123abc'));
        static::assertSame(ThemeConfig::DEFAULT_ACCENT, $resolver->accent('red'));
        static::assertSame(ThemeConfig::DEFAULT_ACCENT, $resolver->accent('javascript:alert(1)'));
        static::assertStringContainsString('--color-accent: #123abc;', $resolver->accentCss('#123abc'));
        static::assertNotSame($resolver->accentName('#123abc'), $resolver->accentName('#abcdef'));
    }

    #[Test]
    public function theBaseStylesheetComesFromTheConverterPackage(): void
    {
        $resolver = new ThemeResolver();

        $css = $resolver->baseCss();

        static::assertStringContainsString('.admonitionblock', $css);
        static::assertStringContainsString('.icon-note::before', $css);
        static::assertSame(
            $resolver->hasher()->hashedName('asciidoctor', 'css', $css),
            $resolver->baseName(),
        );
        static::assertMatchesRegularExpression('/^asciidoctor\.[0-9a-f]+\.css$/', $resolver->baseName());
    }

    #[Test]
    public function theBaseStylesheetIsReadFromTheConverterPackageResources(): void
    {
        $converterFile = new ReflectionObject(new Html5Converter())->getFileName();

        $expected = dirname((string) $converterFile, levels: 3) . '/resources/css/asciidoctor.css';

        static::assertFileExists($expected);
        static::assertSame((string) file_get_contents($expected), new ThemeResolver()->baseCss());
    }

    #[Test]
    public function theConfiguredLogoReplacesTheShippedMark(): void
    {
        $resolver = new ThemeResolver(theme: ThemeConfig::fromArray(['logo' => '/brand/mark.svg']));

        static::assertSame('/brand/mark.svg', $resolver->logoUrl('/docs/'));
    }

    #[Test]
    public function theHasherIsExposed(): void
    {
        $resolver = new ThemeResolver();

        static::assertSame($resolver->siteName(), $resolver->hasher()->hashedName('site', 'css', $resolver->siteCss()));
        static::assertSame($resolver->themeScriptName(), $resolver->hasher()->hashedName(
            'theme',
            'js',
            $resolver->themeScript(),
        ));
    }

    #[Test]
    public function theModeAndTheInitialPaintComeFromTheThemeConfig(): void
    {
        $resolver = new ThemeResolver(theme: ThemeConfig::fromArray([
            'colorMode'        => 'light',
            'defaultColorMode' => 'dark',
        ]));

        static::assertSame('light', $resolver->colorMode());
        static::assertSame('dark', $resolver->defaultColorMode());
        static::assertSame('light', $resolver->config()->colorMode());
    }

    #[Test]
    public function thePublishedStylesheetIsSelfContained(): void
    {
        $css = new ThemeResolver()->siteCss();

        static::assertStringContainsString(':root {', $css);
        static::assertStringContainsString('[data-theme="dark"] {', $css);
        static::assertStringContainsString('@media print', $css);
        static::assertStringNotContainsString('@import', $css);
        static::assertStringNotContainsString('url(http', $css);
        static::assertStringNotContainsString('https://', $css);
        static::assertFalse(str_contains($css, 'file://'));
    }

    #[Test]
    public function theScriptsArePublishedUnderHashedNames(): void
    {
        $resolver = new ThemeResolver();
        $hasher   = $resolver->hasher();

        static::assertNotSame('', $resolver->navScript());
        static::assertSame(
            $hasher->hashedName('nav', 'js', $resolver->navScript()),
            $resolver->navScriptName(),
        );
        static::assertMatchesRegularExpression('/^nav\.[0-9a-f]+\.js$/', $resolver->navScriptName());

        static::assertNotSame('', $resolver->searchCore());
        static::assertSame(
            $hasher->hashedName('search-core', 'js', $resolver->searchCore()),
            $resolver->searchCoreName(),
        );
        static::assertMatchesRegularExpression(
            '/^search-core\.[0-9a-f]+\.js$/',
            $resolver->searchCoreName(),
        );

        static::assertNotSame('', $resolver->searchScript());
        static::assertSame(
            $hasher->hashedName('search', 'js', $resolver->searchScript()),
            $resolver->searchScriptName(),
        );
        static::assertMatchesRegularExpression('/^search\.[0-9a-f]+\.js$/', $resolver->searchScriptName());
    }

    #[Test]
    public function theShippedMarkIsPublishedUnderTheBasePath(): void
    {
        $resolver = new ThemeResolver();

        static::assertStringContainsString('<svg', $resolver->logo());
        static::assertSame(
            $resolver->hasher()->hashedName('logo', 'svg', $resolver->logo()),
            $resolver->logoName(),
        );
        static::assertMatchesRegularExpression('/^logo\.[0-9a-f]+\.svg$/', $resolver->logoName());
        static::assertSame("/assets/{$resolver->logoName()}", $resolver->logoUrl('/'));
        static::assertSame("/docs/assets/{$resolver->logoName()}", $resolver->logoUrl('/docs/'));
    }

    #[Test]
    public function theThemeScriptIsHashedAndCarriesTheToggleBehaviour(): void
    {
        $resolver = new ThemeResolver();
        $script   = $resolver->themeScript();

        static::assertStringContainsString('asciidoc-php-theme', $script);
        static::assertStringContainsString('localStorage', $script);
        static::assertStringContainsString('prefers-color-scheme', $script);
        static::assertStringContainsString('aria-pressed', $script);
        static::assertStringContainsString("addEventListener('click'", $script);
        static::assertStringEndsWith('.js', $resolver->themeScriptName());
    }

    #[Test]
    public function theTokenBlocksFromThePlanArePresent(): void
    {
        $resolver = new ThemeResolver();

        $light = $resolver->lightColors();
        $dark  = $resolver->darkColors();

        foreach ([
            '--color-primary',
            '--color-link',
            '--surface-page',
            '--text-body',
            '--text-muted',
            '--border-subtle',
        ] as $token) {
            static::assertArrayHasKey($token, $light);
            static::assertArrayHasKey($token, $dark);
        }

        static::assertSame('#ffffff', $light['--surface-page']);
        static::assertSame('#16131c', $dark['--surface-page']);
    }

    #[Test]
    public function theTokenScanStopsAtTheBlockBoundary(): void
    {
        static::assertSame(['--a' => '1'], $this->lightColorsFrom(':root {--a: 1;}'));
        static::assertSame(['--a' => '1'], $this->lightColorsFrom(":root {--a:   1   ;}\n--leak: 9;"));
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * Reads the light token block out of a crafted stylesheet, so the block scan can be driven past
     * the shapes the shipped stylesheet happens to have.
     *
     * @return array<string, string>
     */
    private function lightColorsFrom(string $css): array
    {
        $directory = '' === $this->tempDirectory ? $this->createTempDirectory() : $this->tempDirectory;

        file_put_contents("{$directory}/site.css", $css);

        return new ThemeResolver(cssDirectory: $directory)->lightColors();
    }
}
