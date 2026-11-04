<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_starts_with;

use const PREG_PATTERN_ORDER;

/**
 * T079: every guide is reachable from the README in one hop.
 *
 * The check reads the README rather than a list written here, so removing a pointer or renaming a
 * guide fails this test.
 */
#[CoversNothing]
final class ReadmeLinkCheckTest extends TestCase
{
    #[Test]
    public function theReadmeLinksOnlyToExistingLocalFiles(): void
    {
        $targets = $this->relativeLinkTargets();

        static::assertNotSame([], $targets, 'README.md must contain relative links.');

        foreach ($targets as $target) {
            static::assertFileExists(
                "{$this->root()}/{$target}",
                sprintf('README.md links to "%s", which does not exist.', $target),
            );
        }
    }

    #[Test]
    public function theReadmeLinksTheFourGuidesAndTheQuickstart(): void
    {
        $targets = $this->relativeLinkTargets();

        foreach ([
            'quickstart.md',
            'docs/v1/authoring.adoc',
            'docs/v1/action-setup.adoc',
            'docs/v1/theming.adoc',
            'docs/v1/troubleshooting.adoc',
        ] as $expected) {
            static::assertContains(
                $expected,
                $targets,
                sprintf('README.md must link %s.', $expected),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function relativeLinkTargets(): array
    {
        $readme = (string) file_get_contents("{$this->root()}/README.md");

        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $readme, $matches, PREG_PATTERN_ORDER);

        $targets = [];

        foreach ($matches[1] ?? [] as $target) {
            if (
                str_starts_with($target, 'http://')
                || str_starts_with($target, 'https://')
                || str_starts_with($target, 'mailto:')
                || str_starts_with($target, '#')
                || str_contains($target, '://')
            ) {
                continue;
            }

            $targets[] = $target;
        }

        return $targets;
    }

    private function root(): string
    {
        return dirname(
            path  : __DIR__,
            levels: 2,
        );
    }
}
