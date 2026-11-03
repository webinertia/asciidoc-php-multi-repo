<?php

declare(strict_types=1);

namespace WebwareTestIntegration\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Search\SearchIndexBuilder;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_decode;
use function json_encode;
use function trim;

/**
 * T051 to T054: the ranking behaviour the search client is built on.
 *
 * The client is plain browser JavaScript, so its behaviour is exercised by a Node harness against a
 * fixed index rather than by a PHP test. This test is the bridge: it runs the harness and fails the
 * suite when the harness reports a failure, so a change to the shared scoring core cannot land
 * without the ranking assertions holding.
 *
 * The second case proves the harness is actually sensitive: it runs the same harness against a
 * deliberately broken copy of the fixture and requires a non-zero exit. Without it a harness that
 * silently asserted nothing would pass.
 */
#[CoversClass(SearchIndexBuilder::class)]
final class SearchBehaviourTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function theSearchHarnessFailsWhenTheIndexNoLongerSupportsTheAssertions(): void
    {
        $fixture = $this->fixture();
        $version = $fixture['versions'][0];

        static::assertIsArray($version);

        $pages = $version['pages'];

        static::assertIsArray($pages);
        static::assertIsArray($pages[0]);

        $page = $pages[0];

        // Flattening the headings is exactly the regression the ranking assertions exist to catch:
        // the client can no longer name the section a match came from.
        $page['headings']       = [];
        $pages[0]               = $page;
        $version['pages']       = $pages;
        $fixture['versions'][0] = $version;

        $path = Path::join($this->createTempDirectory(), 'broken-index.json');

        static::assertNotFalse(file_put_contents($path, (string) json_encode($fixture)));

        [$status, $output] = $this->runHarness($path);

        static::assertNotSame(0, $status, $output);
        static::assertMatchesRegularExpression('/[1-9][0-9]* failures$/m', $output);
    }

    #[Test]
    public function theSearchHarnessPasses(): void
    {
        [$status, $output] = $this->runHarness(null);

        static::assertSame(0, $status, $output);
        static::assertMatchesRegularExpression('/^[0-9]+ checks, 0 failures$/m', $output);
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        $path = Path::join(dirname(__DIR__, levels: 2), 'test', 'fixtures', 'search', 'index.json');

        $decoded = json_decode((string) file_get_contents($path), associative: true);

        static::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runHarness(?string $fixture): array
    {
        $script = Path::join(dirname(__DIR__, levels: 2), 'test', 'js', 'search-core.test.mjs');

        $command = 'node ' . escapeshellarg($script);

        if (null !== $fixture) {
            $command .= ' ' . escapeshellarg($fixture);
        }

        $output = [];
        $status = 0;

        exec("{$command} 2>&1", $output, $status);

        return [$status, trim(implode("\n", $output))];
    }
}
