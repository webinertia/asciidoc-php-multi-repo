<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
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
#[CoversMethod(ThemeResolver::class, 'colorMode')]
#[CoversMethod(ThemeResolver::class, 'config')]
#[CoversMethod(ThemeResolver::class, 'darkColors')]
#[CoversMethod(ThemeResolver::class, 'defaultColorMode')]
#[CoversMethod(ThemeResolver::class, 'hasher')]
#[CoversMethod(ThemeResolver::class, 'lightColors')]
#[CoversMethod(ThemeResolver::class, 'siteCss')]
#[CoversMethod(ThemeResolver::class, 'siteName')]
#[CoversMethod(ThemeResolver::class, 'themeScript')]
#[CoversMethod(ThemeResolver::class, 'themeScriptName')]
#[CoversMethod(AssetHasher::class, 'hash')]
#[CoversMethod(AssetHasher::class, 'hashedName')]
final class ThemeResolverTest extends TestCase
{
    use TempDirectoryTrait;

    public function testAMalformedTokenBlockYieldsNoTokens(): void
    {
        self::assertSame([], $this->lightColorsFrom(':root {'));
        self::assertSame([], $this->lightColorsFrom(":root {\n    color: red;\n}\n"));
    }

    public function testAnUnterminatedTokenBlockYieldsNoTokens(): void
    {
        $css = ":root {\n    --color-link: #6a49c9;\n" . str_repeat("    --padding-token: 1;\n", 8);

        self::assertSame([], $this->lightColorsFrom($css));
    }

    public function testAssetUrlsCarryTheBasePath(): void
    {
        $resolver = new ThemeResolver();

        self::assertSame('/assets/site.css', $resolver->assetUrl('/', 'site.css'));
        self::assertSame('/docs/assets/site.css', $resolver->assetUrl('/docs/', 'site.css'));
        self::assertSame('/docs/assets/site.css', $resolver->assetUrl('/docs', 'site.css'));
    }

    public function testOneChangedByteChangesThePublishedName(): void
    {
        $directory = $this->createTempDirectory();

        file_put_contents(
            "{$directory}/site.css",
            file_get_contents(
                dirname(__DIR__, 3) . '/resources/css/site.css',
            ),
        );
        file_put_contents(
            "{$directory}/print.css",
            file_get_contents(
                dirname(__DIR__, 3) . '/resources/css/print.css',
            ),
        );

        $before = new ThemeResolver(cssDirectory: $directory)->siteName();

        $css = (string) file_get_contents("{$directory}/site.css");
        file_put_contents("{$directory}/site.css", str_replace(
            '--surface-page: #ffffff;',
            '--surface-page: #fffffe;',
            $css,
        ));

        $after = new ThemeResolver(cssDirectory: $directory)->siteName();

        self::assertNotSame($before, $after);
        self::assertStringStartsWith('site.', $before);
        self::assertStringEndsWith('.css', $before);
    }

    public function testTheAccentIsValidatedAndFallsBack(): void
    {
        $resolver = new ThemeResolver();

        self::assertSame(ThemeConfig::DEFAULT_ACCENT, $resolver->accent());
        self::assertSame('#123abc', $resolver->accent('#123abc'));
        self::assertSame(ThemeConfig::DEFAULT_ACCENT, $resolver->accent('red'));
        self::assertSame(ThemeConfig::DEFAULT_ACCENT, $resolver->accent('javascript:alert(1)'));
        self::assertStringContainsString('--color-accent: #123abc;', $resolver->accentCss('#123abc'));
        self::assertNotSame($resolver->accentName('#123abc'), $resolver->accentName('#abcdef'));
    }

    public function testTheModeAndTheInitialPaintComeFromTheThemeConfig(): void
    {
        $resolver = new ThemeResolver(theme: new ThemeConfig('webware', '#7e5ae0', 'light', 'dark'));

        self::assertSame('light', $resolver->colorMode());
        self::assertSame('dark', $resolver->defaultColorMode());
        self::assertSame('light', $resolver->config()->colorMode());
    }

    public function testThePublishedStylesheetIsSelfContained(): void
    {
        $css = new ThemeResolver()->siteCss();

        self::assertStringContainsString(':root {', $css);
        self::assertStringContainsString('[data-theme="dark"] {', $css);
        self::assertStringContainsString('@media print', $css);
        self::assertStringNotContainsString('@import', $css);
        self::assertStringNotContainsString('url(http', $css);
        self::assertStringNotContainsString('https://', $css);
        self::assertFalse(str_contains($css, 'file://'));
    }

    public function testTheThemeScriptIsHashedAndCarriesTheToggleBehaviour(): void
    {
        $resolver = new ThemeResolver();
        $script   = $resolver->themeScript();

        self::assertStringContainsString('asciidoc-php-theme', $script);
        self::assertStringContainsString('localStorage', $script);
        self::assertStringContainsString('prefers-color-scheme', $script);
        self::assertStringContainsString('aria-pressed', $script);
        self::assertStringContainsString("addEventListener('click'", $script);
        self::assertStringEndsWith('.js', $resolver->themeScriptName());
    }

    public function testTheTokenBlocksFromThePlanArePresent(): void
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
            self::assertArrayHasKey($token, $light);
            self::assertArrayHasKey($token, $dark);
        }

        self::assertSame('#ffffff', $light['--surface-page']);
        self::assertSame('#16131c', $dark['--surface-page']);
    }

    public function testTheTokenScanStopsAtTheBlockBoundary(): void
    {
        self::assertSame(['--a' => '1'], $this->lightColorsFrom(':root {--a: 1;}'));
        self::assertSame(['--a' => '1'], $this->lightColorsFrom(":root {--a:   1   ;}\n--leak: 9;"));
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
