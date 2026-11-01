<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

use Override;
use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;

use function sprintf;

/**
 * A registered command whose behaviour is delivered by a later implementation group. It reports
 * `InternalError` because the contract has no exit code for "recognised but not built yet", and it
 * must never be mistaken for success by a caller.
 */
abstract class StubCommand implements CommandInterface
{
    #[Override]
    final public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        $output->error(sprintf('%s: not implemented', $this->name()));

        return ExitCode::InternalError;
    }
}
