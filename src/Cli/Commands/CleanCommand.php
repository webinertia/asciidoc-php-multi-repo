<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

final class CleanCommand extends StubCommand
{
    public function name(): string
    {
        return 'clean';
    }

    public function summary(): string
    {
        return 'Remove the cache directory and, with --out, the output directory.';
    }
}
