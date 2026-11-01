<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

use Override;

final class DiscoverCommand extends StubCommand
{
    #[Override]
    public function name(): string
    {
        return 'discover';
    }

    #[Override]
    public function summary(): string
    {
        return 'List onboarded repositories and resolved slugs.';
    }
}
