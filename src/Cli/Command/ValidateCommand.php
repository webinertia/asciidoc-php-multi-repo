<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Command;

final class ValidateCommand extends StubCommand
{
    public function name(): string
    {
        return 'validate';
    }

    public function summary(): string
    {
        return 'Report per-repository buildability without writing site output.';
    }
}
