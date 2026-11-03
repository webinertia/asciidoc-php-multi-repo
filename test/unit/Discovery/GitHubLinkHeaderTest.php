<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Discovery\GitHubLinkHeader;

#[CoversClass(GitHubLinkHeader::class)]
#[CoversMethod(GitHubLinkHeader::class, 'next')]
final class GitHubLinkHeaderTest extends TestCase
{
    #[Test]
    public function aHeaderWithoutANextRelationHasNoNextPage(): void
    {
        self::assertNull(GitHubLinkHeader::next('<https://api.github.com/repositories/1?page=1>; rel="prev"'));
    }

    #[Test]
    public function aNullHeaderHasNoNextPage(): void
    {
        self::assertNull(GitHubLinkHeader::next(null));
    }

    #[Test]
    public function theNextRelationIsExtracted(): void
    {
        $header =
            '<https://api.github.com/repositories/1?page=2>; rel="next", '
            . '<https://api.github.com/repositories/1?page=4>; rel="last"';

        self::assertSame('https://api.github.com/repositories/1?page=2', GitHubLinkHeader::next($header));
    }
}
