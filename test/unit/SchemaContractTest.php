<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SchemaValidator;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function file_get_contents;

/**
 * The published schemas in `contracts/` must stay loadable by the shipped validator and must accept
 * the documents this package produces.
 */
#[CoversNothing]
final class SchemaContractTest extends TestCase
{
    private Json $json;

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function publishedSchemas(): array
    {
        return [
            'site'         => ['site', self::siteDocument()],
            'report'       => ['report', self::reportDocument()],
            'search-index' => ['search-index', self::searchIndexDocument()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reportDocument(): array
    {
        return [
            'schemaVersion' => 1,
            'generatedBy'   => [
                'name'    => 'webware/asciidoc-php-multi-repo',
                'version' => '0.1.0',
            ],
            'projects'      => [],
            'totals'        => [
                'ok'            => 0,
                'partial'       => 0,
                'failed'        => 0,
                'skipped'       => 0,
                'pages'         => 0,
                'renderedPages' => 0,
            ],
            'exitCode'      => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchIndexDocument(): array
    {
        return [
            'schemaVersion' => 1,
            'project'       => [
                'slug'  => 'example-project',
                'title' => 'Example Project',
            ],
            'versions'      => [
                [
                    'label'  => 'v1',
                    'latest' => true,
                    'url'    => '/example-project/v1/',
                    'pages'  => [
                        [
                            'url'      => '/example-project/v1/index.html',
                            'title'    => 'Index',
                            'headings' => [],
                            'text'     => 'Body text.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function siteDocument(): array
    {
        return [
            'schemaVersion' => 1,
            'site'          => [
                'name'    => 'Example Site',
                'baseUrl' => 'https://docs.example.test',
            ],
            'organization'  => [
                'name'         => 'example-org',
                'propertyName' => 'docs',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    #[DataProvider('publishedSchemas')]
    public function testSchemaAcceptsAMinimalConformingDocument(string $name, array $document): void
    {
        $result = new SchemaValidator()->validate($document, $this->schema($name));

        self::assertTrue(
            $result->isValid(),
            "{$name} schema rejected a minimal document: {$this->json->encodeCompact($result->errors())}",
        );
    }

    /**
     * @param array<string, mixed> $document
     */
    #[DataProvider('publishedSchemas')]
    public function testSchemaRejectsADocumentMissingARequiredMember(string $name, array $document): void
    {
        $schema = $this->schema($name);
        $broken = $document;

        foreach ($schema['required'] as $required) {
            unset($broken[$required]);

            break;
        }

        $result = new SchemaValidator()->validate($broken, $schema);

        self::assertFalse($result->isValid(), "{$name} schema accepted a document missing a required member.");
    }

    public function testThePackagedSiteSchemaIsThePublishedCopy(): void
    {
        self::assertSame(
            $this->json->encode($this->schema('site')),
            $this->json->encode($this->json->decode($this->read('config', 'site.schema.json'))),
            'config/site.schema.json and contracts/site.schema.json have drifted apart.',
        );
    }

    protected function setUp(): void
    {
        $this->json = new Json();
    }

    private function read(string $directory, string $file): string
    {
        $path = Path::join($this->root(), $directory, $file);
        $json = file_get_contents($path);

        self::assertIsString($json, "Unable to read {$path}");

        return $json;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $name): array
    {
        return $this->json->decode($this->read('contracts', "{$name}.schema.json"));
    }
}
