<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Build;

use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function basename;
use function explode;
use function file_get_contents;
use function gethostname;
use function in_array;
use function is_readable;
use function is_string;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_replace;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function substr_count;

use const PREG_OFFSET_CAPTURE;

/**
 * Rejects build output that would change between two identical builds.
 *
 * Two builds of the same sources must produce the same bytes, so that a published site can be
 * compared, cached and served without surprise diffs. Anything that depends on the clock, on the
 * machine, or on where the checkout happens to live breaks that, and this guard is what turns such a
 * leak into a failed build instead of a diff nobody can explain.
 *
 * The scan covers the text files the build writes. A violation names the file, what was found, and
 * the line it was found on, so the offending template or source is easy to locate.
 *
 * @api
 */
final class DeterminismGuard
{
    /**
     * Extensions the build writes as text. Anything else is copied verbatim and is not scanned.
     */
    private const array TEXT_EXTENSIONS = [
        'css',
        'html',
        'js',
        'json',
        'map',
        'svg',
        'txt',
        'xml',
    ];

    private readonly Filesystem $filesystem;

    public function __construct(Filesystem $filesystem = new Filesystem())
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Every violation found in the output directory, in a stable order.
     *
     * @return list<string>
     */
    public function inspect(string $outputDirectory): array
    {
        $violations = [];
        $hostname   = gethostname();
        $patterns   = $this->patterns($outputDirectory, is_string($hostname) ? $hostname : '');

        foreach ($this->filesystem->scan($outputDirectory) as $relative) {
            if (! $this->isText($relative)) {
                continue;
            }

            $path = Path::join($outputDirectory, ...explode('/', $relative));

            if (! is_readable($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if (! is_string($contents)) {
                continue;
            }

            foreach ($patterns as $description => $pattern) {
                $matches = [];
                if (1 !== preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                $offset = $matches[0][1] ?? 0;
                $line   = substr_count(substr($contents, offset: 0, length: (int) $offset), needle: "\n") + 1;

                $violations[] = sprintf('%s line %d: %s', $relative, $line, $description);
            }
        }

        return $violations;
    }

    private function isText(string $relative): bool
    {
        $name = strtolower(basename($relative));

        if ('.nojekyll' === $name || 'cname' === $name) {
            return true;
        }

        $position = strrpos($name, needle: '.');

        if (false === $position) {
            return false;
        }

        return in_array(substr($name, $position + 1), self::TEXT_EXTENSIONS, strict: true);
    }

    /**
     * @return array<string, string>
     */
    private function patterns(string $outputDirectory, string $hostname): array
    {
        $patterns = [
            'a build timestamp (an ISO 8601 instant)' => '/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})\b/',
            'a generated-at stamp' => '/generated\s+(?:at|on)\b[^<>]{0,40}?\d{4}-\d{2}-\d{2}/i',
            'an absolute Windows path' => '/\b[A-Za-z]:[\\\\\/][A-Za-z0-9_.\-\\\\\/]+/',
            'an absolute POSIX home path' => '/(?<![\w.\/])\/(?:home|Users|root)\/[A-Za-z0-9._\-]+\//',
            'a leftover temporary file name' => '/\.tmp-[A-Za-z0-9]+/',
        ];

        $normalised = str_replace('\\', replace: '/', subject: $outputDirectory);

        if ('' !== $normalised && Path::isAbsolute($outputDirectory)) {
            $patterns['the output directory path'] = '/' . preg_quote($normalised, delimiter: '/') . '/';
        }

        if (strlen($hostname) > 2) {
            $patterns['this machine\'s name'] = '/\b' . preg_quote($hostname, delimiter: '/') . '\b/i';
        }

        return $patterns;
    }
}
