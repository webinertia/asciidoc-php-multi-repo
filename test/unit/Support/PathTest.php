<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

#[CoversClass(Path::class)]
final class PathTest extends TestCase
{
    private const string DS = DIRECTORY_SEPARATOR;

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function isAbsoluteProvider(): array
    {
        return [
            'windows drive'   => ['C:\\docs', true],
            'windows forward' => ['C:/docs', true],
            'posix'           => ['/docs', true],
            'relative'        => ['docs/v1', false],
            'drive relative'  => ['C:docs', false],
            'empty'           => ['', false],
        ];
    }

    /**
     * @return array<string, array{0: list<string>, 1: string}>
     */
    public static function joinProvider(): array
    {
        return [
            'relative segments'        => [['docs', 'v1'], 'docs' . self::DS . 'v1'],
            'trailing separator'       => [['docs' . self::DS, 'v1'], 'docs' . self::DS . 'v1'],
            'leading separator on one' => [['docs', self::DS . 'v1'], 'docs' . self::DS . 'v1'],
            'mixed separators'         => [
                ['docs/v1', 'index.adoc'],
                'docs' . self::DS . 'v1' . self::DS . 'index.adoc',
            ],
            'parent segments'          => [['docs', 'v1', '..', 'v2'], 'docs' . self::DS . 'v2'],
            'current segments'         => [['docs', '.', 'v1'], 'docs' . self::DS . 'v1'],
            'posix absolute'           => [['/srv/docs', 'v1'], self::DS . 'srv' . self::DS . 'docs' . self::DS . 'v1'],
            'windows absolute'         => [
                ['C:' . self::DS . 'docs', 'v1'],
                'C:' . self::DS . 'docs' . self::DS . 'v1',
            ],
            'empty segment ignored'    => [['docs', '', 'v1'], 'docs' . self::DS . 'v1'],
            'no segments'              => [[], ''],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normaliseProvider(): array
    {
        return [
            'duplicate separators' => ['docs//v1///index.adoc', 'docs' . self::DS . 'v1' . self::DS . 'index.adoc'],
            'mixed separators'     => ['docs\\v1/index.adoc', 'docs' . self::DS . 'v1' . self::DS . 'index.adoc'],
            'dot segments'         => ['docs/./v1/./index.adoc', 'docs' . self::DS . 'v1' . self::DS . 'index.adoc'],
            'parent segments'      => ['docs/v1/../v2/index.adoc', 'docs' . self::DS . 'v2' . self::DS . 'index.adoc'],
            'trailing separator'   => ['docs' . self::DS . 'v1' . self::DS, 'docs' . self::DS . 'v1'],
            'empty'                => ['', ''],
            'posix root'           => ['/', self::DS],
            'windows root'         => ['C:\\', 'C:' . self::DS],
            'windows absolute'     => ['C:\\docs\\..\\docs\\v1', 'C:' . self::DS . 'docs' . self::DS . 'v1'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function relativeToProvider(): array
    {
        return [
            'child directory'   => ['docs', 'docs/v1', 'v1'],
            'sibling directory' => ['docs/v1', 'docs/v2', '..' . self::DS . 'v2'],
            'nested file'       => [
                'docs',
                'docs/v1/guide/index.adoc',
                'v1' . self::DS . 'guide' . self::DS . 'index.adoc',
            ],
            'same directory'    => ['docs', 'docs', '.'],
            'parent of both'    => ['docs/v1', 'docs', '..'],
            'windows paths'     => ['C:\\site', 'C:\\site\\build', 'build'],
            'posix paths'       => ['/srv/site', '/srv/site/build/html', 'build' . self::DS . 'html'],
            'different roots'   => ['C:\\site', 'D:\\site', 'D:' . self::DS . 'site'],
            'mixed separators'  => ['docs\\v1', 'docs/v2/index.adoc', '..' . self::DS . 'v2' . self::DS . 'index.adoc'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urlPathProvider(): array
    {
        return [
            'windows separators' => ['docs\\v1\\index.adoc', 'docs/v1/index.adoc'],
            'duplicate slashes'  => ['docs//v1///index.adoc', 'docs/v1/index.adoc'],
            'current directory'  => ['./docs/v1', 'docs/v1'],
            'parent segments'    => ['docs/v1/../v2', 'docs/v2'],
            'leading slash'      => ['/docs/v1', '/docs/v1'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function withExtensionProvider(): array
    {
        return [
            'replace adoc'          => ['docs/index.adoc', '.html', 'docs' . self::DS . 'index.html'],
            'replace without dot'   => ['docs/index.adoc', 'html', 'docs' . self::DS . 'index.html'],
            'add to bare name'      => ['docs/index', '.html', 'docs' . self::DS . 'index.html'],
            'dot in directory name' => ['docs.v1/index.adoc', '.html', 'docs.v1' . self::DS . 'index.html'],
            'multiple dots'         => ['docs/guide.v2.adoc', '.html', 'docs' . self::DS . 'guide.v2.html'],
            'hidden file'           => ['docs/.adoc', '.html', 'docs' . self::DS . '.adoc.html'],
            'windows separators'    => ['docs\\index.adoc', '.html', 'docs' . self::DS . 'index.html'],
            'empty extension'       => ['docs/index.adoc', '', 'docs' . self::DS . 'index'],
        ];
    }

    #[Test]
    #[DataProvider('isAbsoluteProvider')]
    public function isAbsolute(string $path, bool $expected): void
    {
        self::assertSame($expected, Path::isAbsolute($path));
    }

    /**
     * @param list<string> $parts
     */
    #[Test]
    #[DataProvider('joinProvider')]
    public function join(array $parts, string $expected): void
    {
        self::assertSame($expected, Path::join(...$parts));
    }

    #[Test]
    #[DataProvider('normaliseProvider')]
    public function normalise(string $path, string $expected): void
    {
        self::assertSame($expected, Path::normalise($path));
    }

    #[Test]
    #[DataProvider('relativeToProvider')]
    public function relativeTo(string $from, string $to, string $expected): void
    {
        self::assertSame($expected, Path::relativeTo($from, $to));
    }

    #[Test]
    public function resolveJoinsRelativePaths(): void
    {
        self::assertSame(
            'C:' . self::DS . 'base' . self::DS . 'out',
            Path::resolve('C:' . self::DS . 'base', 'out'),
        );
    }

    #[Test]
    public function resolveKeepsAbsolutePaths(): void
    {
        self::assertSame(
            Path::normalise('C:' . self::DS . 'other'),
            Path::resolve('C:' . self::DS . 'base', 'C:' . self::DS . 'other'),
        );
    }

    #[Test]
    public function resolveWithoutPathReturnsBase(): void
    {
        self::assertSame('C:' . self::DS . 'base', Path::resolve('C:' . self::DS . 'base', ''));
    }

    #[Test]
    #[DataProvider('urlPathProvider')]
    public function toUrlPath(string $path, string $expected): void
    {
        self::assertSame($expected, Path::toUrlPath($path));
    }

    #[Test]
    #[DataProvider('withExtensionProvider')]
    public function withExtension(string $path, string $extension, string $expected): void
    {
        self::assertSame($expected, Path::withExtension($path, $extension));
    }
}
