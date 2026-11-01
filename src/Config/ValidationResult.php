<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

use function array_map;
use function count;
use function implode;

/**
 * The outcome of validating one document against one schema.
 */
final readonly class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        private array $errors,
    ) {}

    public static function valid(): self
    {
        return new self([]);
    }

    public function describe(): string
    {
        return implode('; ', array_map(
            static fn(ValidationError $error): string => $error->describe(),
            $this->errors,
        ));
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }
}
