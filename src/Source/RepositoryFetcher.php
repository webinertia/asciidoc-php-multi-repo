<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Source;

use Closure;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessResult;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;

use function is_dir;
use function max;
use function sprintf;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Obtains one repository's files, either from a shallow git clone or from a local directory.
 *
 * A repository named by a manifest carries a local path; that path is used as it stands, and its
 * revision is the commit when it is a git repository and a content digest otherwise. Everything
 * else is fetched with `--depth 1` into a per-repository cache directory, with a configurable
 * retry count.
 */
final class RepositoryFetcher
{
    private const float GIT_TIMEOUT_SECONDS = 120.0;

    private const int REVISION_LENGTH = 64;

    /** @var Closure(string, string): string */
    private readonly Closure $remoteUrl;

    private readonly int $maxAttempts;

    /**
     * @param null|Closure(string, string): string $remoteUrl builds the clone URL from the
     *                                                       organization and repository names
     */
    public function __construct(
        private readonly ProcessRunner $runner = new ProcessRunner(),
        int $maxAttempts = 3,
        ?Closure $remoteUrl = null,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->remoteUrl   = $remoteUrl
            ?? static fn(string $organization, string $repository): string => sprintf(
                'https://github.com/%s/%s.git',
                $organization,
                $repository,
            );
    }

    /**
     * @param null|string $ref overrides the repository default branch
     *
     * @throws SourceException when the fetch cannot be completed
     */
    public function fetch(
        DiscoveredRepository $repository,
        SiteConfig $config,
        string $cacheDirectory,
        ?string $ref = null,
    ): FetchedRepository {
        $documentation = $config->source()->directory();
        $path          = $repository->path();

        if (null !== $path) {
            $files = $this->files($path);

            return new FetchedRepository(
                $repository->name(),
                $this->localRevision($path, $files),
                $path,
                $documentation,
                $files,
            );
        }

        return $this->fetchRemote(
            $repository,
            $documentation,
            Path::join($cacheDirectory, $repository->slug()),
            $ref ?? $repository->defaultBranch(),
            $this->remoteUrlFor($repository, $config),
        );
    }

    /**
     * Returns whether the worktree was updated. An unchanged revision leaves it untouched so a
     * second fetch costs nothing.
     */
    private function checkout(string $root, string $revision): bool
    {
        $head = $this->runner->run(['git', 'rev-parse', 'HEAD'], $root, self::GIT_TIMEOUT_SECONDS);

        if ($head->successful() && trim($head->stdout()) === $revision) {
            return false;
        }

        $this->runner->mustRun(
            ['git', 'checkout', '--force', '--quiet', 'FETCH_HEAD'],
            $root,
            self::GIT_TIMEOUT_SECONDS,
        );

        return true;
    }

    /**
     * @throws SourceException when the remote cannot be read or checked out
     */
    private function fetchRemote(
        DiscoveredRepository $repository,
        string $documentation,
        string $root,
        string $ref,
        string $remote,
    ): FetchedRepository {
        $this->filesystem->ensureDirectory($root);

        if (! is_dir(Path::join($root, '.git'))) {
            $this->runner->mustRun(['git', 'init', '--quiet'], $root, self::GIT_TIMEOUT_SECONDS);
        }

        // A repeat fetch already has the remote, so adding it is allowed to fail and the set-url
        // that follows is what makes the URL authoritative.
        $this->runner->run(['git', 'remote', 'add', 'origin', $remote], $root, self::GIT_TIMEOUT_SECONDS);
        $this->runner->mustRun(
            ['git', 'remote', 'set-url', 'origin', $remote],
            $root,
            self::GIT_TIMEOUT_SECONDS,
        );

        $attempts = $this->fetchWithRetries($root, $ref, $repository->name());
        $revision = trim(
            $this->runner
                ->mustRun(
                    ['git', 'rev-parse', 'FETCH_HEAD'],
                    $root,
                    self::GIT_TIMEOUT_SECONDS,
                )
                ->stdout(),
        );

        $checkedOut = $this->checkout($root, $revision);

        return new FetchedRepository(
            $repository->name(),
            $revision,
            $root,
            $documentation,
            $this->files($root),
            $attempts,
            $checkedOut,
        );
    }

    /**
     * @throws SourceException when every attempt fails
     */
    private function fetchWithRetries(string $root, string $ref, string $repositoryName): int
    {
        $attempts = 0;
        $failure  = null;

        while ($attempts < $this->maxAttempts) {
            $attempts++;
            $result = $this->runner->run(
                ['git', 'fetch', '--depth', '1', 'origin', $ref],
                $root,
                self::GIT_TIMEOUT_SECONDS,
            );

            if ($result->successful()) {
                $failure = null;
                break;
            }

            $failure = $result;
        }

        if ($failure instanceof ProcessResult) {
            throw new SourceException(new SourceError(
                ReasonCode::FetchFailed,
                sprintf(
                    '%s: git fetch of "%s" failed after %d attempt(s): %s',
                    $repositoryName,
                    $ref,
                    $attempts,
                    trim($failure->stderr()),
                ),
                null,
                null,
                'Check that the repository exists, that the branch or tag exists, and that the credential can read it.',
            ));
        }

        return $attempts;
    }

    /**
     * Every file below the root, without the git metadata directory.
     *
     * @return list<string>
     */
    private function files(string $root): array
    {
        $files = [];

        foreach ($this->filesystem->scan($root) as $file) {
            if (str_starts_with($file, '.git/')) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @param list<string> $files
     */
    private function localRevision(string $root, array $files): string
    {
        if (is_dir(Path::join($root, '.git'))) {
            $result = $this->runner->run(
                ['git', 'rev-parse', 'HEAD'],
                $root,
                self::GIT_TIMEOUT_SECONDS,
            );

            if ($result->successful()) {
                return trim($result->stdout());
            }
        }

        $material = '';

        foreach ($files as $file) {
            $hash     = $this->filesystem->hash(Path::join($root, $file));
            $material .= "{$file}:{$hash}\n";
        }

        return substr(
            string: $this->filesystem->hashContents($material),
            offset: 0,
            length: self::REVISION_LENGTH,
        );
    }

    private function remoteUrlFor(DiscoveredRepository $repository, SiteConfig $config): string
    {
        return ($this->remoteUrl)($config->organization()->name(), $repository->name());
    }
}
