<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

use function in_array;

/**
 * The `organization` section: the discovery inputs.
 */
final readonly class OrganizationSection
{
    public const string DEFAULT_ONBOARD_VALUE = 'true';

    /**
     * @param list<string> $excludeRepositories
     */
    public function __construct(
        private string $name,
        private string $propertyName,
        private string $onboardValue,
        private ?string $tokenEnvVar,
        private array $excludeRepositories,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::requiredString($data, 'name'),
            Values::requiredString($data, 'propertyName'),
            Values::string($data, 'onboardValue', self::DEFAULT_ONBOARD_VALUE),
            Values::optionalString($data, 'tokenEnvVar'),
            Values::stringList($data, 'excludeRepositories', []),
        );
    }

    /**
     * @return list<string>
     */
    public function excludeRepositories(): array
    {
        return $this->excludeRepositories;
    }

    public function excludes(string $repositoryName): bool
    {
        return in_array($repositoryName, $this->excludeRepositories, strict: true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function onboardValue(): string
    {
        return $this->onboardValue;
    }

    public function propertyName(): string
    {
        return $this->propertyName;
    }

    public function tokenEnvVar(): ?string
    {
        return $this->tokenEnvVar;
    }
}
