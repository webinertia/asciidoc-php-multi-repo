<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Source;

use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;

/**
 * One reason a project or a single page could not be produced.
 *
 * The array shape matches `buildError` in `contracts/report.schema.json`.
 */
final readonly class SourceError
{
    public function __construct(
        private ReasonCode $reasonCode,
        private string $message,
        private ?string $version = null,
        private ?string $path = null,
        private ?string $correctiveAction = null,
    ) {}

    public function correctiveAction(): ?string
    {
        return $this->correctiveAction;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function reasonCode(): ReasonCode
    {
        return $this->reasonCode;
    }

    /**
     * @return array{
     *     reasonCode: string,
     *     message: string,
     *     version: ?string,
     *     path: ?string,
     *     correctiveAction: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'reasonCode'       => $this->reasonCode->value,
            'message'          => $this->message,
            'version'          => $this->version,
            'path'             => $this->path,
            'correctiveAction' => $this->correctiveAction,
        ];
    }

    public function version(): ?string
    {
        return $this->version;
    }
}
