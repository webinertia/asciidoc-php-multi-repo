<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Support;

use Webware\AsciidocPhpMultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;

/**
 * A command that records what the application handed it and returns a chosen exit code.
 */
final class RecordingCommand implements CommandInterface
{
    public ?Options $options = null;

    public ?SiteConfig $config = null;

    public ?Output $output = null;

    public int $calls = 0;

    public function __construct(
        private readonly string $name = 'build',
        private readonly ExitCode $code = ExitCode::Success,
        private readonly ?string $summary = 'Record what the application passes to a command.',
    ) {}

    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        $this->calls++;
        $this->options = $options;
        $this->config  = $config;
        $this->output  = $output;

        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function summary(): string
    {
        return $this->summary ?? '';
    }
}
