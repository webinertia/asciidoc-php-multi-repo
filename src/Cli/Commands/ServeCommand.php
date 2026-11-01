<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;

final class ServeCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'serve';
    }

    #[Override]
    public function summary(): string
    {
        return 'Serve an already built output directory.';
    }
}
