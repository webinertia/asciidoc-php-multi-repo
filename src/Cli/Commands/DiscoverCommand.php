<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli\Commands;

use Override;
use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Cli\ProjectFilter;
use Webware\AsciidocPhpMultiRepo\Config\ConfigException;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;
use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveryException;
use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhpMultiRepo\Discovery\SlugException;

use function sprintf;

/**
 * Lists the discovered repositories, one line each: the repository name, the resolved slug, and
 * whether the project is onboarded. Nothing else is written to standard output.
 *
 * A slug collision is a configuration error (exit 2) because the configuration is what is wrong. A
 * discovery failure is exit 3 because the API or the credential is what is wrong.
 */
final class DiscoverCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscoveryFactory $factory = new DiscoveryFactory(),
    ) {}

    /**
     * @throws ConfigException when the manifest cannot be loaded
     */
    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        try {
            $repositories = ProjectFilter::apply(
                $this->factory->create($options->manifest)->discover($config),
                $options,
            );
        } catch (SlugException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError;
        } catch (DiscoveryException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::DiscoveryError;
        }

        foreach ($repositories as $repository) {
            $output->write(sprintf(
                'name=%s slug=%s onboarded=%s',
                $repository->name(),
                $repository->slug(),
                $repository->onboarded() ? 'yes' : 'no',
            ));
        }

        return ExitCode::Success;
    }

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
