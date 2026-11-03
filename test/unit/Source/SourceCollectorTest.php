<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\SourceCollector;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_map;
use function bin2hex;
use function dirname;
use function random_bytes;

/**
 * Collecting one repository where a single version cannot be enumerated.
 *
 * The versions are independent, so a version without an entry page is reported as an error and the
 * versions after it are still collected.
 */
#[CoversClass(SourceCollector::class)]
#[CoversMethod(SourceCollector::class, 'collect')]
final class SourceCollectorTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    public function testAVersionWithoutAnEntryPageDoesNotStopTheLaterVersions(): void
    {
        $root = $this->repository([
            'docs/v1/index.adoc' => "= One\n",
            'docs/v2/guide.adoc' => "= Two\n",
            'docs/v3/index.adoc' => "= Three\n",
        ]);

        $sources = new SourceCollector()->collect(
            SourceFixture::repository(path: $root),
            SourceFixture::config(),
            Path::join($this->tempDirectory, 'cache'),
        );

        self::assertSame(['v1', 'v2', 'v3'], array_map(
            static fn($version): string => $version->label(),
            $sources->versions(),
        ));

        self::assertCount(1, $sources->errors());
        self::assertSame(ReasonCode::MissingEntry, $sources->errors()[0]->reasonCode());
        self::assertCount(2, $sources->pages());

        self::assertSame(
            ['docs/v1/index.adoc', 'docs/v3/index.adoc'],
            array_map(
                static fn($source): string => $source->relativePath(),
                $sources->sources(),
            ),
        );
    }

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @param array<string, string> $files
     */
    private function repository(array $files): string
    {
        $root = Path::join($this->tempDirectory, 'repository-' . bin2hex(random_bytes(6)));

        $this->filesystem->ensureDirectory($root);

        foreach ($files as $relative => $contents) {
            $absolute = Path::join($root, $relative);

            $this->filesystem->ensureDirectory(dirname($absolute));
            $this->filesystem->writeAtomic($absolute, $contents);
        }

        return $root;
    }
}
