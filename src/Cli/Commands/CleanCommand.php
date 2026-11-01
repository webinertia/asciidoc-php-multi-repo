<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

use Override;

final class CleanCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'clean';
    }

    #[Override]
    public function summary(): string
    {
        return 'Remove the cache directory and, with --out, the output directory.';
    }
}
