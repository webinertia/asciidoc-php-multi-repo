<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Webware\AsciidocPhp\MultiRepo\Config\Manifest;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestEntry;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestLoader;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\ManifestDiscovery;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Http\HttpException;
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
    #[Test]
    public function aCollisionBetweenOnboardedProjectsIsRejected(): void
    {
        $manifest = $this->manifest(
            new ManifestEntry('message-bus', null, 'main', false, true, null),
            new ManifestEntry('message_bus', null, 'main', false, true, null),
        );

        $this->expectException(SlugException::class);

        new ManifestDiscovery($manifest)->discover(SourceFixture::config());
    }

    #[Test]
    public function aConfiguredSlugOverrideWins(): void
    {
        $repositories = $this->discover(['projects' => ['versioned' => ['slug' => 'versioned-docs']]]);

        $slugs = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->slug(),
            $repositories,
        );

        static::assertContains('versioned-docs', $slugs);
        static::assertNotContains('versioned', $slugs);
    }

    #[Test]
    public function anExcludedRepositoryIsDropped(): void
    {
        $names = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->name(),
            $this->discover(),
        );

        static::assertNotContains('archived', $names);
    }

    #[Test]
    public function aNonOnboardedProjectIsIgnoredByTheCollisionCheck(): void
    {
        $manifest = $this->manifest(
            new ManifestEntry('message-bus', null, 'main', false, true, null),
            new ManifestEntry('message_bus', null, 'main', false, false, null),
        );

        static::assertCount(2, new ManifestDiscovery($manifest)->discover(SourceFixture::config()));
    }

    #[Test]
    public function anOverrideWithoutASlugLeavesTheSlugAlone(): void
    {
        $repositories = $this->discover(['projects' => ['versioned' => ['title' => 'Versioned']]]);

        static::assertSame('versioned', $this->named($repositories, 'versioned')->slug());
    }

    #[Test]
    public function aSlugOverrideIsNormalised(): void
    {
        $repositories = $this->discover(['projects' => ['versioned' => ['slug' => 'versioned-docs']]]);

        $versioned = $this->named($repositories, 'versioned');

        static::assertSame('versioned-docs', $versioned->slug());
    }

    #[Test]
    public function aSlugOverrideThatCollidesWithADiscoveredRepositoryIsRejected(): void
    {
        $this->expectException(SlugException::class);

        $this->discover(['projects' => ['versioned' => ['slug' => 'mixed']]]);
    }

    #[Test]
    public function everyEntryKeepsItsLocalPath(): void
    {
        foreach ($this->discover() as $repository) {
            static::assertNotNull($repository->path(), $repository->name());
            static::assertDirectoryExists($repository->path());
        }
    }

    #[Test]
    public function manifestDiscoveryNeverTouchesTheNetwork(): void
    {
        $transport = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new HttpException('The transport must not be used by manifest discovery.');
            }
        };

        $discovery = new DiscoveryFactory(transport: $transport)
            ->create(SourceFixture::path('manifest.json'));

        static::assertInstanceOf(ManifestDiscovery::class, $discovery);
        static::assertCount(6, $discovery->discover(SourceFixture::config()));
    }

    #[Test]
    public function theDefaultBranchComesFromTheManifest(): void
    {
        foreach ($this->discover() as $repository) {
            static::assertSame('main', $repository->defaultBranch(), $repository->name());
        }
    }

    #[Test]
    public function theFixtureManifestProducesTheOnboardedProjectSet(): void
    {
        $repositories = $this->discover();

        static::assertSame(
            ['broken-include', 'markdown-only', 'missing-entry', 'mixed', 'unversioned', 'versioned'],
            array_map(static fn(DiscoveredRepository $repository): string => $repository->slug(), $repositories),
        );
    }

    #[Test]
    public function theManifestPathIsResolvedByTheLoader(): void
    {
        $manifest = new ManifestLoader()->load(SourceFixture::path('manifest.json'));

        static::assertCount(7, $manifest->repositories());
        static::assertContains('archived', $manifest->names());
    }

    #[Test]
    public function theManifestSlugWinsOverTheRepositoryName(): void
    {
        $manifest = $this->manifest(new ManifestEntry('message-bus', 'bus', 'main', false, true, null));

        $repositories = new ManifestDiscovery($manifest)->discover(SourceFixture::config());

        static::assertSame('bus', $repositories[0]->slug());
    }

    #[Test]
    public function theOnboardedFlagComesFromTheManifest(): void
    {
        foreach ($this->discover() as $repository) {
            static::assertTrue($repository->onboarded(), $repository->name());
        }
    }

    #[Test]
    public function theProjectsAreSortedBySlug(): void
    {
        $slugs = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->slug(),
            $this->discover(),
        );
        $sorted = $slugs;

        sort($sorted);

        static::assertSame($sorted, $slugs);
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
