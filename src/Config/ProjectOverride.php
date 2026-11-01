<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

/**
 * One entry of the `projects` map. Every field is optional: an override refines a discovered
 * repository and never causes one to exist.
 */
final readonly class ProjectOverride
{
    public function __construct(
        private ?string $slug,
        private ?string $title,
        private ?string $description,
        private ?string $accent,
        private ?int $order,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::optionalString($data, 'slug'),
            Values::optionalString($data, 'title'),
            Values::optionalString($data, 'description'),
            Values::optionalString($data, 'accent'),
            Values::optionalInteger($data, 'order'),
        );
    }

    public static function empty(): self
    {
        return new self(null, null, null, null, null);
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function accent(): ?string
    {
        return $this->accent;
    }

    public function order(): ?int
    {
        return $this->order;
    }

    public function isEmpty(): bool
    {
        return $this->slug === null
            && $this->title === null
            && $this->description === null
            && $this->accent === null
            && $this->order === null;
    }
}
