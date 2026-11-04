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
            Values::boolean($data, 'syntaxHighlighting', true),
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

    /**
     * Whether a page links the vendored highlighter payload.
     *
     * The converter's own highlighter is always off, so this flag is the only switch: when it is on,
     * a page whose body contains a highlighted code block links the vendored bundle, its loader and
     * its stylesheet. It defaults to on because the converter puts the `hljs` class on every listing
     * block regardless, and a page that advertises highlighting without the payload is a page with
     * no colours in it.
     */
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
