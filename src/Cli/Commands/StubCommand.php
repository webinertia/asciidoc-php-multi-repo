<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;

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
