<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Command;

use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;

/**
 * One subcommand of the CLI. The command receives already parsed options and an already validated
 * configuration, so a configuration error is reported before any command runs.
 */
interface Command
{
    public function name(): string;

    /** One line summary used by `--help`. */
    public function summary(): string;

    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode;
}
