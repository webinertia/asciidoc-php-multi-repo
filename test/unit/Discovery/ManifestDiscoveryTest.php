<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\Manifest;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestEntry;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestLoader;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\ManifestDiscovery;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Http\HttpException;
use Webware\AsciidocPhp\MultiRepo\Http\HttpResponse;
use Webware\AsciidocPhp\MultiRepo\Http\HttpTransportInterface;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;

use function array_map;
use function array_values;
use function sort;

#[CoversClass(ManifestDiscovery::class)]
#[CoversMethod(ManifestDiscovery::class, 'collect')]
#[CoversClass(DiscoveryFactory::class)]
#[CoversMethod(DiscoveryFactory::class, 'create')]
final class ManifestDiscoveryTest extends TestCase
{
    public function testACollisionBetweenOnboardedProjectsIsRejected(): void
    {
        $manifest = $this->manifest(
            new ManifestEntry('message-bus', null, 'main', false, true, null),
            new ManifestEntry('message_bus', null, 'main', false, true, null),
        );

        $this->expectException(SlugException::class);

        new ManifestDiscovery($manifest)->discover(SourceFixture::config());
    }

    public function testAConfiguredSlugOverrideWins(): void
    {
        $repositories = $this->discover(['projects' => ['versioned' => ['slug' => 'versioned-docs']]]);

        $slugs = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->slug(),
            $repositories,
        );

        self::assertContains('versioned-docs', $slugs);
        self::assertNotContains('versioned', $slugs);
    }

    public function testAnExcludedRepositoryIsDropped(): void
    {
        $names = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->name(),
            $this->discover(),
        );

        self::assertNotContains('archived', $names);
    }

    public function testANonOnboardedProjectIsIgnoredByTheCollisionCheck(): void
    {
        $manifest = $this->manifest(
            new ManifestEntry('message-bus', null, 'main', false, true, null),
            new ManifestEntry('message_bus', null, 'main', false, false, null),
        );

        self::assertCount(2, new ManifestDiscovery($manifest)->discover(SourceFixture::config()));
    }

    public function testAnOverrideWithoutASlugLeavesTheSlugAlone(): void
    {
        $repositories = $this->discover(['projects' => ['versioned' => ['title' => 'Versioned']]]);

        self::assertSame('versioned', $this->named($repositories, 'versioned')->slug());
    }

    public function testASlugOverrideIsNormalised(): void
    {
        $repositories = $this->discover(['projects' => ['versioned' => ['slug' => 'versioned-docs']]]);

        $versioned = $this->named($repositories, 'versioned');

        self::assertSame('versioned-docs', $versioned->slug());
    }

    public function testEveryEntryKeepsItsLocalPath(): void
    {
        foreach ($this->discover() as $repository) {
            self::assertNotNull($repository->path(), $repository->name());
            self::assertDirectoryExists($repository->path());
        }
    }

    public function testManifestDiscoveryNeverTouchesTheNetwork(): void
    {
        $transport = new class implements HttpTransportInterface {
            public function get(string $url, array $headers = []): HttpResponse
            {
                throw new HttpException('The transport must not be used by manifest discovery.');
            }
        };

        $discovery = new DiscoveryFactory(transport: $transport)
            ->create(SourceFixture::path('manifest.json'));

        self::assertInstanceOf(ManifestDiscovery::class, $discovery);
        self::assertCount(6, $discovery->discover(SourceFixture::config()));
    }

    public function testTheDefaultBranchComesFromTheManifest(): void
    {
        foreach ($this->discover() as $repository) {
            self::assertSame('main', $repository->defaultBranch(), $repository->name());
        }
    }

    public function testTheFixtureManifestProducesTheOnboardedProjectSet(): void
    {
        $repositories = $this->discover();

        self::assertSame(
            ['broken-include', 'markdown-only', 'missing-entry', 'mixed', 'unversioned', 'versioned'],
            array_map(static fn(DiscoveredRepository $repository): string => $repository->slug(), $repositories),
        );
    }

    public function testTheManifestPathIsResolvedByTheLoader(): void
    {
        $manifest = new ManifestLoader()->load(SourceFixture::path('manifest.json'));

        self::assertCount(7, $manifest->repositories());
        self::assertContains('archived', $manifest->names());
    }

    public function testTheManifestSlugWinsOverTheRepositoryName(): void
    {
        $manifest = $this->manifest(new ManifestEntry('message-bus', 'bus', 'main', false, true, null));

        $repositories = new ManifestDiscovery($manifest)->discover(SourceFixture::config());

        self::assertSame('bus', $repositories[0]->slug());
    }

    public function testTheOnboardedFlagComesFromTheManifest(): void
    {
        foreach ($this->discover() as $repository) {
            self::assertTrue($repository->onboarded(), $repository->name());
        }
    }

    public function testTheProjectsAreSortedBySlug(): void
    {
        $slugs = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->slug(),
            $this->discover(),
        );
        $sorted = $slugs;

        sort($sorted);

        self::assertSame($sorted, $slugs);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return list<DiscoveredRepository>
     */
    private function discover(array $overrides = []): array
    {
        return new ManifestDiscovery(new ManifestLoader()->load(SourceFixture::path('manifest.json')))
            ->discover(SourceFixture::config($overrides));
    }

    private function manifest(ManifestEntry ...$entries): Manifest
    {
        return new Manifest(Manifest::SUPPORTED_SCHEMA_VERSION, array_values($entries), 'manifest.json');
    }

    /**
     * @param list<DiscoveredRepository> $repositories
     */
    private function named(array $repositories, string $name): DiscoveredRepository
    {
        foreach ($repositories as $repository) {
            if ($repository->name() === $name) {
                return $repository;
            }
        }

        self::fail("Repository \"{$name}\" was not discovered.");
    }
}
