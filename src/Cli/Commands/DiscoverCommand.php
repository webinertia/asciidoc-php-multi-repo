<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

final class DiscoverCommand extends StubCommand
{
    public function name(): string
    {
        return 'discover';
    }

    public function summary(): string
    {
        return 'List onboarded repositories and resolved slugs.';
    }
}
