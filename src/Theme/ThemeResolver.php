<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Theme;

use ReflectionObject;
use RuntimeException;
use Webware\AsciidocPhp\Converter\Html5Converter;
use Webware\AsciidocPhp\MultiRepo\Config\ThemeConfig;

use function dirname;
use function file_get_contents;
use function is_file;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function sprintf;
use function str_contains;
use function strpos;
use function substr;
use function trim;

use const PREG_SET_ORDER;

/**
 * Resolves the token set, the accent rule, and the published asset names for one build.
 *
 * The colour tokens live in `resources/css/site.css` and nowhere else. This class reads them rather
 * than restating them, so the contrast test and the stylesheet cannot drift apart. The print rules
 * live in `resources/css/print.css` and are appended to the site stylesheet, which is why the site
 * stylesheet needs no `@import`.
 *
 * The base stylesheet is the converter's own `resources/css/asciidoctor.css`. It is published as an
 * asset and linked before the site stylesheet, so the shell links three stylesheets in a fixed
 * order: the base layer, the site overrides, then the accent rule.
 *
 * @api
 */
final readonly class ThemeResolver
{
    private const string HEX_COLOUR = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';

    private const string TOKEN_PATTERN = '/--([a-zA-Z0-9-]+)\s*:\s*([^;]+);/';

    private string $cssDirectory;

    private string $jsDirectory;

    private ThemeConfig $theme;

    public function __construct(
        private AssetHasher $hasher = new AssetHasher(),
        ?string $cssDirectory = null,
        ?ThemeConfig $theme = null,
    ) {
        $this->cssDirectory = $cssDirectory ?? dirname(__DIR__, levels: 2) . '/resources/css';
        $this->jsDirectory  = dirname(__DIR__, levels: 2) . '/resources/js';
        $this->theme        = $theme ?? ThemeConfig::defaults();
    }

    /**
     * The validated accent colour: a project override when it is a hex colour, otherwise the default.
     */
    public function accent(?string $accent = null): string
    {
        if (null !== $accent && 1 === preg_match(self::HEX_COLOUR, $accent)) {
            return $accent;
        }

        return $this->theme->defaultAccent();
    }

    /**
     * The `--color-accent` rule for a project, falling back to the site default accent.
     */
    public function accentCss(?string $accent = null): string
    {
        return sprintf(":root {\n    --color-accent: %s;\n}\n", $this->accent($accent));
    }

    /**
     * The published accent stylesheet name.
     */
    public function accentName(?string $accent = null): string
    {
        return $this->hasher->hashedName('accent', 'css', $this->accentCss($accent));
    }

    /**
     * A URL for a published asset under the site base path.
     */
    public function assetUrl(string $basePath, string $name): string
    {
        return rtrim($basePath, characters: '/') . '/assets/' . $name;
    }

    /**
     * The converter's base stylesheet, which the site publishes and links before its overrides.
     *
     * The converter keeps the path in a private constant, so the package root is derived from the
     * converter class file rather than restated here. `Html5Converter` is a userland class in an
     * installed package, so reflection always yields a source file and the only failure left is the
     * stylesheet itself being missing or unreadable.
     *
     * @throws RuntimeException when the shipped stylesheet is missing or unreadable
     */
    public function baseCss(): string
    {
        $file = new ReflectionObject(new Html5Converter())->getFileName();

        return $this->readFile(dirname((string) $file, levels: 3) . '/resources/css', 'asciidoctor.css');
    }

    /**
     * The published base stylesheet name.
     *
     * @throws RuntimeException when the shipped stylesheet is missing or unreadable
     */
    public function baseName(): string
    {
        return $this->hasher->hashedName('asciidoctor', 'css', $this->baseCss());
    }

    /**
     * The configured mode: `light`, `dark`, or `system` when the OS preference decides.
     */
    public function colorMode(): string
    {
        return $this->theme->colorMode();
    }

    public function config(): ThemeConfig
    {
        return $this->theme;
    }

    /**
     * The dark mode colour tokens, keyed by custom property name.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when a stylesheet is missing or unreadable
     */
    public function darkColors(): array
    {
        return $this->tokens('[data-theme="dark"]');
    }

    /**
     * The mode painted before the toggle script runs, which is `dark` unless configured otherwise.
     */
    public function defaultColorMode(): string
    {
        return $this->theme->defaultColorMode();
    }

    public function hasher(): AssetHasher
    {
        return $this->hasher;
    }

    /**
     * The light mode colour tokens, keyed by custom property name.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when a stylesheet is missing or unreadable
     */
    public function lightColors(): array
    {
        return $this->tokens(':root');
    }

    /**
     * The navigation script.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function navScript(): string
    {
        return $this->readFile($this->jsDirectory, 'nav.js');
    }

    /**
     * The published navigation script name.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function navScriptName(): string
    {
        return $this->hasher->hashedName('nav', 'js', $this->navScript());
    }

    /**
     * The search scoring core, shared by the browser and the test harness.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function searchCore(): string
    {
        return $this->readFile($this->jsDirectory, 'search-core.js');
    }

    /**
     * The published search core name.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function searchCoreName(): string
    {
        return $this->hasher->hashedName('search-core', 'js', $this->searchCore());
    }

    /**
     * The search client.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function searchScript(): string
    {
        return $this->readFile($this->jsDirectory, 'search.js');
    }

    /**
     * The published search client name.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function searchScriptName(): string
    {
        return $this->hasher->hashedName('search', 'js', $this->searchScript());
    }

    /**
     * The site stylesheet plus the print rules, which is what the build publishes.
     *
     * @throws RuntimeException when a stylesheet is missing or unreadable
     */
    public function siteCss(): string
    {
        return "{$this->read('site.css')}\n{$this->read('print.css')}";
    }

    /**
     * The published site stylesheet name.
     *
     * @throws RuntimeException when a stylesheet is missing or unreadable
     */
    public function siteName(): string
    {
        return $this->hasher->hashedName('site', 'css', $this->siteCss());
    }

    /**
     * The theme toggle script.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function themeScript(): string
    {
        return $this->readFile($this->jsDirectory, 'theme.js');
    }

    /**
     * The published theme toggle script name.
     *
     * @throws RuntimeException when the script is missing or unreadable
     */
    public function themeScriptName(): string
    {
        return $this->hasher->hashedName('theme', 'js', $this->themeScript());
    }

    /**
     * @throws RuntimeException when the asset is missing or unreadable
     */
    private function read(string $file): string
    {
        return $this->readFile($this->cssDirectory, $file);
    }

    /**
     * @throws RuntimeException when the asset is missing or unreadable
     */
    private function readFile(string $directory, string $file): string
    {
        $path = "{$directory}/{$file}";

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Asset "%s" is missing from "%s".', $file, $directory));
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new RuntimeException(sprintf('Asset "%s" could not be read.', $path));
        }

        return $contents;
    }

    /**
     * @return array<string, string>
     *
     * @throws RuntimeException when a stylesheet is missing or unreadable
     */
    private function tokens(string $selector): array
    {
        $css      = $this->read('site.css');
        $needle   = "{$selector} {";
        $position = strpos($css, needle: $needle);

        if (false === $position) {
            return [];
        }

        $open = strpos($css, needle: '{', offset: $position);
        if (false === $open) {
            return [];
        }

        $close = strpos($css, needle: '}', offset: $open);
        if (false === $close) {
            return [];
        }

        $block = substr($css, $open + 1, $close - $open - 1);
        if (! str_contains($block, '--')) {
            return [];
        }

        $matches = [];
        preg_match_all(self::TOKEN_PATTERN, $block, $matches, PREG_SET_ORDER);

        /** @var array<string, string> $tokens */
        $tokens = [];
        foreach ($matches as $match) {
            $name                = $match[1] ?? '';
            $tokens["--{$name}"] = trim($match[2] ?? '');
        }

        return $tokens;
    }
}
