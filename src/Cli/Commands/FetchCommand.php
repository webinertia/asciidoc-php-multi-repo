<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

final class FetchCommand extends StubCommand
{
    public function name(): string
    {
        return 'fetch';
    }

    public function summary(): string
    {
        return 'Fetch documentation sources into the cache.';
    }
}
