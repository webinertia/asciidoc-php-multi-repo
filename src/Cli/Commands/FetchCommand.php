<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

use Override;

final class FetchCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'fetch';
    }

    #[Override]
    public function summary(): string
    {
        return 'Fetch documentation sources into the cache.';
    }
}
