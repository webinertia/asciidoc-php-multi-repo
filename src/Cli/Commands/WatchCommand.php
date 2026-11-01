<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

use Override;

final class WatchCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'watch';
    }

    #[Override]
    public function summary(): string
    {
        return 'Rebuild affected projects on change and serve the site.';
    }
}
