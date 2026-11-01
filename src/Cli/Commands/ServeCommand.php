<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

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
