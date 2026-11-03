<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function getcwd;
use function implode;
use function is_dir;
use function is_file;
use function sprintf;
use function str_starts_with;

/**
 * Removes the cache directory, and the output directory when `-o`/`--out` names one.
 *
 * The removal is confined to the directory that holds `site.json`. A target outside it, or the
 * config directory itself, is refused with `ConfigError` unless `--force` is given, because `clean`
 * must never be a general purpose `rm -rf` and must never touch a tree it did not create. A path
 * that is already absent is reported as absent rather than as an error, so the command is safe to
 * run twice.
 */
final class CleanCommand implements CommandInterface
{
    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {}

    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        $targets = $this->targets($options, $config);

        foreach ($targets as $target) {
            $refusal = $this->refusal($target['path'], $config, $options);

            if (null !== $refusal) {
                $output->error($refusal);

                return ExitCode::ConfigError;
            }
        }

        /** @var list<string> $lines */
        $lines = [];

        foreach ($targets as $target) {
            if (! is_dir($target['path']) && ! is_file($target['path'])) {
                $output->detail(sprintf('%s: absent (%s)', $target['label'], $target['path']));
                $lines[] = sprintf('%s: absent (%s)', $target['label'], $target['path']);

                continue;
            }

            if (! $this->filesystem->remove($target['path'])) {
                $output->error(sprintf('unable to remove "%s"', $target['path']));

                return ExitCode::InternalError;
            }

            $output->progress(sprintf('removed %s', $target['path']));
            $lines[] = sprintf('%s: removed (%s)', $target['label'], $target['path']);
        }

        $output->summary(implode("\n", $lines));

        return ExitCode::Success;
    }

    #[Override]
    public function name(): string
    {
        return 'clean';
    }

    #[Override]
    public function summary(): string
    {
        return 'Remove the cache directory and, with --out, the output directory.';
    }

    private function cacheDirectory(Options $options, SiteConfig $config): string
    {
        if (null === $options->cache) {
            return $config->output()->cacheDirectory();
        }

        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, $options->cache);
    }

    private function outputDirectory(Options $options): string
    {
        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, (string) $options->out);
    }

    /**
     * The reason the target may not be removed, or null when it may.
     */
    private function refusal(string $path, SiteConfig $config, Options $options): ?string
    {
        $relative = Path::relativeTo($config->configDirectory(), $path);

        if ('.' === $relative) {
            return sprintf('refusing to remove "%s": it is the config directory itself', $path);
        }

        if ($options->force) {
            return null;
        }

        $outside = Path::isAbsolute($relative) || str_starts_with($relative, '..');

        if (! $outside) {
            return null;
        }

        return sprintf(
            'refusing to remove "%s": it is outside the config directory "%s". Re-run with --force to remove it.',
            $path,
            $config->configDirectory(),
        );
    }

    /**
     * @return list<array{label: string, path: string}>
     */
    private function targets(Options $options, SiteConfig $config): array
    {
        $targets = [
            ['label' => 'cache directory', 'path' => $this->cacheDirectory($options, $config)],
        ];

        if (null !== $options->out) {
            $targets[] = ['label' => 'output directory', 'path' => $this->outputDirectory($options)];
        }

        return $targets;
    }
}
