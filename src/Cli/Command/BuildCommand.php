<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Command;

final class BuildCommand extends StubCommand
{
    public function name(): string
    {
        return 'build';
    }

    public function summary(): string
    {
        return 'discover, fetch, render, assemble, write, and report.';
    }
}
