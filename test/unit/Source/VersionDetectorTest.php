<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;
use Webware\AsciidocPhp\MultiRepo\Source\VersionDetector;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;

use function array_map;

/**
 * Version selection: a matching subdirectory is a version, and nothing matching means the
 * documentation directory itself is `latest`.
 */
#[CoversClass(VersionDetector::class)]
#[CoversClass(DocVersion::class)]
#[CoversMethod(VersionDetector::class, 'detect')]
#[CoversMethod(DocVersion::class, 'prefix')]
#[CoversMethod(DocVersion::class, 'number')]
final class VersionDetectorTest extends TestCase
{
    /**
     * @return array<string, array{list<string>, list<string>}>
     */
    public static function directoryLayouts(): array
    {
        return [
            'two versions'          => [
                ['docs/v1/index.adoc', 'docs/v2/index.adoc'],
                ['v1',                 'v2'],
            ],
            'numeric ordering'      => [
                ['docs/v2/index.adoc', 'docs/v10/index.adoc'],
                ['v2',                 'v10'],
            ],
            'out of order input'    => [
                ['docs/v10/index.adoc', 'docs/v2/index.adoc', 'docs/v1/index.adoc'],
                ['v1',                  'v2',                 'v10'],
            ],
            'zero is not a version' => [
                ['docs/v0/index.adoc'],
                ['latest'],
            ],
            'leading zero rejected' => [
                ['docs/v01/index.adoc'],
                ['latest'],
            ],
            'uppercase rejected'    => [
                ['docs/V2/index.adoc'],
                ['latest'],
            ],
            'suffix rejected'       => [
                ['docs/v2-rc1/index.adoc'],
                ['latest'],
            ],
            'unversioned'           => [
                ['docs/index.adoc'],
                ['latest'],
            ],
            'no documentation'      => [
                [],
                ['latest'],
            ],
        ];
    }

    #[Test]
    public function aConfiguredVersionPatternReplacesTheDefault(): void
    {
        $config = SourceFixture::config(['source' => [
            'directory'      => 'docs',
            'versionPattern' => '^release-[0-9]+$',
        ]]);

        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/release-7/index.adoc', 'docs/v1/index.adoc']),
            $config,
        );

        self::assertSame(['release-7'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
        self::assertCount(1, $selection->warnings());
    }

    #[Test]
    public function aFileOutsideEveryVersionDirectoryWarnsInsteadOfVanishing(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('mixed', ['docs/loose.adoc', 'docs/v1/index.adoc']),
            SourceFixture::config(),
        );

        self::assertSame(['v1'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
        self::assertCount(1, $selection->warnings());
        self::assertStringContainsString('mixed', $selection->warnings()[0]);
        self::assertStringContainsString('docs/loose.adoc', $selection->warnings()[0]);
    }

    #[Test]
    public function aFileOutsideTheDocumentationDirectoryDoesNotStopTheScan(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['README.adoc', 'docs/v1/index.adoc']),
            SourceFixture::config(),
        );

        self::assertSame(['v1'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
        self::assertSame([], $selection->warnings());
    }

    #[Test]
    public function aLabelWithoutThePrefixIsNumberedFromItsFirstCharacter(): void
    {
        $config = SourceFixture::config(['source' => [
            'directory'      => 'docs',
            'versionPattern' => '^[0-9]+x$',
        ]]);

        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/7x/index.adoc']),
            $config,
        );

        self::assertSame(7, $selection->versions()[0]->number());
    }

    #[Test]
    public function anEmptyDocumentationDirectoryIsASingleLatestVersion(): void
    {
        $selection = new VersionDetector()->detect(SourceFixture::fetched('empty', []), SourceFixture::config());

        self::assertCount(1, $selection->versions());
        self::assertSame(DocVersion::LATEST, $selection->versions()[0]->label());
        self::assertSame([], $selection->warnings());
    }

    #[Test]
    public function anUnversionedRepositoryProducesNoWarning(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/index.adoc', 'docs/guide.adoc']),
            SourceFixture::config(),
        );

        self::assertSame([], $selection->warnings());
    }

    #[Test]
    public function aPatternDelimiterInsideTheVersionPatternIsEscaped(): void
    {
        $config = SourceFixture::config(['source' => [
            'directory'      => 'docs',
            'versionPattern' => '^v[0-9]#[0-9]$',
        ]]);

        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/v1#2/index.adoc']),
            $config,
        );

        self::assertSame(['v1#2'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
    }

    #[Test]
    public function aSubdirectoryThatDoesNotMatchIsNotAVersion(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/nightly/index.adoc', 'docs/v1/index.adoc']),
            SourceFixture::config(),
        );

        self::assertSame(['v1'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
        self::assertCount(1, $selection->warnings());
    }

    #[Test]
    public function aVersionDefaultsToNumberZero(): void
    {
        self::assertSame(0, new DocVersion('unlabelled', 'docs/unlabelled')->number());
    }

    #[Test]
    public function aVersionDirectoryIsTheDocumentationDirectoryPlusTheLabel(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/v2/index.adoc']),
            SourceFixture::config(),
        );

        self::assertSame('docs/v2', $selection->versions()[0]->directory());
        self::assertSame('docs/v2/', $selection->versions()[0]->prefix());
        self::assertFalse($selection->versions()[0]->isLatest());
    }

    /**
     * @param list<string> $files
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('directoryLayouts')]
    public function labelsFollowTheVersionPattern(array $files, array $expected): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', $files),
            SourceFixture::config(),
        );

        self::assertSame(
            $expected,
            array_map(static fn(DocVersion $version): string => $version->label(), $selection->versions()),
        );
    }

    #[Test]
    public function pathsOutsideTheDocumentationDirectoryAreIgnored(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/v1/index.adoc', 'src/Other.php', 'README.adoc']),
            SourceFixture::config(),
        );

        self::assertSame(['v1'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
        self::assertSame([], $selection->warnings());
    }

    #[Test]
    public function theConfiguredDocumentationDirectoryIsHonoured(): void
    {
        $config = SourceFixture::config(['source' => ['directory' => 'documentation']]);

        $selection = new VersionDetector()->detect(
            SourceFixture::fetched(
                'example',
                ['documentation/v3/index.adoc', 'docs/v9/index.adoc'],
                documentationDirectory: 'documentation',
            ),
            $config,
        );

        self::assertSame(['v3'], array_map(
            static fn(DocVersion $version): string => $version->label(),
            $selection->versions(),
        ));
    }

    #[Test]
    public function theUnversionedDirectoryIsLatestAndIsTheDocumentationDirectory(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/index.adoc']),
            SourceFixture::config(),
        );

        $version = $selection->versions()[0];

        self::assertSame(DocVersion::LATEST, $version->label());
        self::assertSame('docs', $version->directory());
        self::assertSame('docs/', $version->prefix());
        self::assertTrue($version->isLatest());
        self::assertSame(0, $version->number());
    }

    #[Test]
    public function versionsSortNumericallyNotLexically(): void
    {
        $selection = new VersionDetector()->detect(
            SourceFixture::fetched('example', ['docs/v2/index.adoc', 'docs/v10/index.adoc', 'docs/v9/index.adoc']),
            SourceFixture::config(),
        );

        self::assertSame(
            ['v2', 'v9', 'v10'],
            array_map(static fn(DocVersion $version): string => $version->label(), $selection->versions()),
        );
        self::assertSame([2, 9, 10], array_map(
            static fn(DocVersion $version): int => $version->number(),
            $selection->versions(),
        ));
    }
}
