<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use Webware\AsciidocPhpMultiRepo\Support\Filesystem;
use Webware\AsciidocPhpMultiRepo\Support\Json;
use Webware\AsciidocPhpMultiRepo\Support\JsonDecodeException;
use Webware\AsciidocPhpMultiRepo\Support\Path;

use function getcwd;
use function sprintf;

/**
 * Reads a JSON configuration document, decodes it, and validates it against a schema.
 */
final class DocumentReader
{
    public function __construct(
        private readonly SchemaValidator $validator = new SchemaValidator(),
        private readonly Json $json = new Json(),
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {}

    public function absolute(string $path): string
    {
        return Path::isAbsolute($path) ? Path::normalise($path) : Path::resolve($this->cwd(), $path);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigException when the file is missing, unreadable, malformed, or invalid
     */
    public function read(string $path, string $schemaPath): array
    {
        $absolute = Path::isAbsolute($path) ? Path::normalise($path) : Path::resolve($this->cwd(), $path);

        if (! $this->filesystem->exists($absolute)) {
            throw new ConfigException(sprintf('Configuration file %s does not exist.', $absolute), '');
        }

        try {
            $document = $this->json->decodeObject($this->filesystem->read($absolute), $absolute);
        } catch (JsonDecodeException $exception) {
            throw new ConfigException($exception->getMessage(), $exception->pointer(), $exception);
        }

        $result = $this->validator->validate($document, $this->schema($schemaPath));

        if ($result->isValid()) {
            return $document;
        }

        $first   = $result->errors()[0];
        $pointer = $first->pointer() === '' ? '/' : $first->pointer();

        throw new ConfigException(
            sprintf('%s: %s: %s', $absolute, $pointer, $first->message()),
            $first->pointer(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(string $schemaPath): array
    {
        return $this->json->decodeObject($this->filesystem->read($schemaPath), $schemaPath);
    }

    private function cwd(): string
    {
        $cwd = getcwd();

        return false === $cwd ? '.' : $cwd;
    }
}
