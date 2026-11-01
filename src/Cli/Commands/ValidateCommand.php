<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;

final class ValidateCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'validate';
    }

    #[Override]
    public function summary(): string
    {
        return 'Report per-repository buildability without writing site output.';
    }
}
