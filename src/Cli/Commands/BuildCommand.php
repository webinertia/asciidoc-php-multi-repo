<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;

final class BuildCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'build';
    }

    #[Override]
    public function summary(): string
    {
        return 'discover, fetch, render, assemble, write, and report.';
    }
}
