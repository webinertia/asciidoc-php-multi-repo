<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Discovery\GitHubLinkHeader;

#[CoversClass(GitHubLinkHeader::class)]
#[CoversMethod(GitHubLinkHeader::class, 'next')]
final class GitHubLinkHeaderTest extends TestCase
{
    public function testAHeaderWithoutANextRelationHasNoNextPage(): void
    {
        self::assertNull(GitHubLinkHeader::next('<https://api.github.com/repositories/1?page=1>; rel="prev"'));
    }

    public function testANullHeaderHasNoNextPage(): void
    {
        self::assertNull(GitHubLinkHeader::next(null));
    }

    public function testTheNextRelationIsExtracted(): void
    {
        $header =
            '<https://api.github.com/repositories/1?page=2>; rel="next", '
            . '<https://api.github.com/repositories/1?page=4>; rel="last"';

        self::assertSame('https://api.github.com/repositories/1?page=2', GitHubLinkHeader::next($header));
    }
}
