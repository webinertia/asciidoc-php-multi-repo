<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Report;

use InvalidArgumentException;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;

use function implode;
use function in_array;
use function preg_match;
use function strcmp;
use function usort;

/**
 * One project's outcome in a build report.
 *
 * The status vocabulary is the closed set of `contracts/report.schema.json`; anything else is
 * rejected here rather than written to a report the schema would refuse.
 *
 * @api
 */
final readonly class ProjectResult
{
    public const string OK = 'ok';

    public const string PARTIAL = 'partial';

    public const string FAILED = 'failed';

    public const string SKIPPED = 'skipped';

    /** @var list<string> */
    public const array STATUSES = [self::OK, self::PARTIAL, self::FAILED, self::SKIPPED];

    /**
     * @param list<string>       $versions version labels, newest first
     * @param list<SourceError>  $errors
     * @param list<SourceError>  $warnings
     *
     * @throws InvalidArgumentException when the status is outside the schema vocabulary
     */
    public function __construct(
        private string $repository,
        private string $slug,
        private string $status,
        private ?string $title = null,
        private ?string $revision = null,
        private array $versions = [],
        private int $pageCount = 0,
        private int $renderedPageCount = 0,
        private array $errors = [],
        private array $warnings = [],
        private int $fetchAttempts = 1,
    ) {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(
                'Unknown project status "' . $status . '"; expected one of ' . implode(', ', self::STATUSES) . '.',
            );
        }
    }

    /**
     * @return list<SourceError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function fetchAttempts(): int
    {
        return $this->fetchAttempts;
    }

    public function isDegraded(): bool
    {
        return self::OK !== $this->status && self::SKIPPED !== $this->status;
    }

    public function pageCount(): int
    {
        return $this->pageCount;
    }

    public function renderedPageCount(): int
    {
        return $this->renderedPageCount;
    }

    public function repository(): string
    {
        return $this->repository;
    }

    public function revision(): ?string
    {
        return $this->revision;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function title(): string
    {
        return $this->title ?? $this->slug;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'repository'        => $this->repository,
            'slug'              => $this->slug,
            'status'            => $this->status,
            'versions'          => $this->versions,
            'pageCount'         => $this->pageCount,
            'renderedPageCount' => $this->renderedPageCount,
            'errors'            => $this->errorArrays($this->errors),
            'fetchAttempts'     => $this->fetchAttempts,
        ];

        if (null !== $this->title && '' !== $this->title) {
            $result['title'] = $this->title;
        }

        // The schema pins the revision to a 40 character git object name. A local source has no
        // commit, so its content digest is omitted rather than written in a shape that fails
        // validation.
        if (null !== $this->revision && 1 === preg_match('/^[0-9a-f]{40}$/', $this->revision)) {
            $result['revision'] = $this->revision;
        }

        if ([] !== $this->warnings) {
            $result['warnings'] = $this->errorArrays($this->warnings);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function versions(): array
    {
        return $this->versions;
    }

    /**
     * @return list<SourceError>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param list<SourceError> $errors
     *
     * @return list<array<string, mixed>>
     */
    private function errorArrays(array $errors): array
    {
        $entries = [];

        foreach ($errors as $error) {
            $entries[] = $error->toArray();
        }

        usort(
            $entries,
            static fn(array $left, array $right): int => strcmp(
                (string) $left['reasonCode'] . "\0" . (string) ($left['path'] ?? ''),
                (string) $right['reasonCode'] . "\0" . (string) ($right['path'] ?? ''),
            ),
        );

        return $entries;
    }
}
