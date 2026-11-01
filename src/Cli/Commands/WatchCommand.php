<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

final class WatchCommand extends StubCommand
{
    public function name(): string
    {
        return 'watch';
    }

    public function summary(): string
    {
        return 'Rebuild affected projects on change and serve the site.';
    }
}
