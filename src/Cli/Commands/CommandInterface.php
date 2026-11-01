<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;

/**
 * One subcommand of the CLI. The command receives already parsed options and an already validated
 * configuration, so a configuration error is reported before any command runs.
 */
interface CommandInterface
{
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode;

    public function name(): string;

    /** One line summary used by `--help`. */
    public function summary(): string;
}
