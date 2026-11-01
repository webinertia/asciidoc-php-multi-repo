<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Command;

final class ServeCommand extends StubCommand
{
    public function name(): string
    {
        return 'serve';
    }

    public function summary(): string
    {
        return 'Serve an already built output directory.';
    }
}
