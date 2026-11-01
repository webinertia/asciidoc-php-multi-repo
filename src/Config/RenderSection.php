<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

/**
 * The `render` section: converter inputs shared by every project.
 */
final readonly class RenderSection
{
    /**
     * @param list<int> $tocLevels
     */
    public function __construct(
        private array $tocLevels,
        private string $safeMode,
        private int $concurrency,
        private int $searchExtractLimit,
        private bool $syntaxHighlighting,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::integerList($data, 'tocLevels', [2, 3]),
            Values::string($data, 'safeMode', 'safe'),
            Values::integer($data, 'concurrency', 4),
            Values::integer($data, 'searchExtractLimit', 2000),
            Values::boolean($data, 'syntaxHighlighting', false),
        );
    }

    public function concurrency(): int
    {
        return $this->concurrency;
    }

    public function safeMode(): string
    {
        return $this->safeMode;
    }

    public function searchExtractLimit(): int
    {
        return $this->searchExtractLimit;
    }

    public function syntaxHighlighting(): bool
    {
        return $this->syntaxHighlighting;
    }

    /**
     * @return list<int>
     */
    public function tocLevels(): array
    {
        return $this->tocLevels;
    }

    public function withConcurrency(int $concurrency): self
    {
        return new self(
            $this->tocLevels,
            $this->safeMode,
            $concurrency,
            $this->searchExtractLimit,
            $this->syntaxHighlighting,
        );
    }
}
