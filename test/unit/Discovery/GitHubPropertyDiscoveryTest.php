<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveryException;
use Webware\AsciidocPhpMultiRepo\Discovery\GitHubApiClient;
use Webware\AsciidocPhpMultiRepo\Discovery\GitHubLinkHeader;
use Webware\AsciidocPhpMultiRepo\Discovery\GitHubPropertyDiscovery;
use Webware\AsciidocPhpMultiRepo\Discovery\GitHubPropertyValues;
use Webware\AsciidocPhpMultiRepo\Discovery\GitHubRepositoryMetadata;
use Webware\AsciidocPhpMultiRepo\Discovery\OnboardValue;
use Webware\AsciidocPhpMultiRepo\Http\HttpException;
use Webware\AsciidocPhpMultiRepo\Http\HttpResponse;
use Webware\AsciidocPhpMultiRepo\Http\HttpTransportInterface;
use WebwareTest\AsciidocPhpMultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhpMultiRepo\Support\StubHttpTransport;

use function array_map;
use function getenv;
use function json_encode;
use function putenv;
use function sprintf;
use function str_contains;

/**
 * Discovery through the organisation custom property, exercised against a transport that serves
 * canned payloads. No socket is opened.
 */
#[CoversClass(GitHubPropertyDiscovery::class)]
#[CoversMethod(GitHubPropertyDiscovery::class, 'collect')]
#[CoversClass(GitHubApiClient::class)]
#[CoversMethod(GitHubApiClient::class, 'get')]
#[CoversMethod(GitHubApiClient::class, 'perPage')]
#[CoversMethod(GitHubApiClient::class, 'url')]
#[CoversClass(GitHubPropertyValues::class)]
#[CoversMethod(GitHubPropertyValues::class, 'fromPages')]
#[CoversMethod(GitHubPropertyValues::class, 'values')]
#[CoversClass(GitHubRepositoryMetadata::class)]
#[CoversMethod(GitHubRepositoryMetadata::class, 'for')]
#[CoversMethod(GitHubRepositoryMetadata::class, 'fromPages')]
#[CoversClass(OnboardValue::class)]
#[CoversMethod(OnboardValue::class, 'matches')]
#[CoversClass(GitHubLinkHeader::class)]
#[CoversMethod(GitHubLinkHeader::class, 'next')]
final class GitHubPropertyDiscoveryTest extends TestCase
{
    private const string ENV_VALUE = 'ghp_fixture_token_value';

    private ?string $previousToken = null;

    public function testABooleanPropertyValueIsAccepted(): void
    {
        $repositories = $this->discover($this->twoPageTransport());

        self::assertTrue($this->named($repositories, 'theme-kit')->onboarded());
    }

    public function testAMissingTokenEnvironmentVariableIsReportedByName(): void
    {
        putenv('FIXTURE_DISCOVERY_TOKEN');

        try {
            $this->discover($this->twoPageTransport());

            self::fail('Expected a DiscoveryException.');
        } catch (DiscoveryException $exception) {
            self::assertStringContainsString('FIXTURE_DISCOVERY_TOKEN', $exception->getMessage());
        }
    }

    public function testAnExcludedRepositoryIsDropped(): void
    {
        $names = array_map(
            static fn(DiscoveredRepository $repository): string => $repository->name(),
            $this->discover($this->twoPageTransport()),
        );

        self::assertNotContains('archived', $names);
    }

    public function testAnInvalidJsonBodyIsReportedAsADiscoveryFailure(): void
    {
        $transport = new class implements HttpTransportInterface {
            public function get(string $url, array $headers = []): HttpResponse
            {
                return new HttpResponse(200, 'not json');
            }
        };

        $this->expectException(DiscoveryException::class);

        $this->discover($transport);
    }

    public function testARepositoryMissingFromTheSearchFallsBackToTheDefaults(): void
    {
        $repositories = $this->discover($this->twoPageTransport([
            'search' => ['items' => []],
        ]));

        self::assertSame('main', $this->named($repositories, 'message-bus')->defaultBranch());
        self::assertFalse($this->named($repositories, 'message-bus')->archived());
    }

    public function testOnlyTheConfiguredOnboardValueIsOnboarded(): void
    {
        $repositories = $this->discover($this->twoPageTransport());

        self::assertTrue($this->named($repositories, 'message-bus')->onboarded());
        self::assertFalse($this->named($repositories, 'job-queue')->onboarded());
    }

    public function testTheApiBaseIsHonoured(): void
    {
        $transport = $this->twoPageTransport();

        new GitHubPropertyDiscovery(new GitHubApiClient(
            transport: $transport,
            apiBase  : 'https://github.example.test/api/v3/',
        ))->discover(SourceFixture::config());

        self::assertSame(
            'https://github.example.test/api/v3/orgs/fixture-org/properties/values?per_page=100',
            $transport->requests[0]['url'],
        );
    }

    public function testTheDefaultBranchAndArchivedFlagComeFromTheRepositorySearch(): void
    {
        $repositories = $this->discover($this->twoPageTransport());

        self::assertSame('develop', $this->named($repositories, 'job-queue')->defaultBranch());
        self::assertSame('main', $this->named($repositories, 'message-bus')->defaultBranch());
    }

    public function testTheOnboardedRepositoriesAreDiscoveredAcrossTwoPages(): void
    {
        $repositories = $this->discover($this->twoPageTransport());

        self::assertSame(
            ['job-queue', 'message-bus', 'theme-kit'],
            array_map(static fn(DiscoveredRepository $repository): string => $repository->slug(), $repositories),
        );
    }

    public function testTheTokenNeverAppearsInAFailureMessage(): void
    {
        $transport = new class implements HttpTransportInterface {
            public function get(string $url, array $headers = []): HttpResponse
            {
                return new HttpResponse(401, '{"message":"Bad credentials"}');
            }
        };

        try {
            $this->discover($transport);

            self::fail('Expected a DiscoveryException.');
        } catch (DiscoveryException $exception) {
            self::assertStringContainsString('fixture-org', $exception->getMessage());
            self::assertStringContainsString('docs', $exception->getMessage());
            self::assertStringContainsString('401', $exception->getMessage());
            self::assertFalse(str_contains($exception->getMessage(), self::ENV_VALUE));
        }
    }

    public function testTheTokenNeverAppearsWhenTheTransportItselfFails(): void
    {
        $transport = new class implements HttpTransportInterface {
            public function get(string $url, array $headers = []): HttpResponse
            {
                throw new HttpException('cURL error 6: Could not resolve host');
            }
        };

        try {
            $this->discover($transport);

            self::fail('Expected a DiscoveryException.');
        } catch (DiscoveryException $exception) {
            self::assertStringContainsString('Could not resolve host', $exception->getMessage());
            self::assertFalse(str_contains($exception->getMessage(), self::ENV_VALUE));
        }
    }

    public function testTheTokenTravelsAsABearerHeader(): void
    {
        $transport = $this->twoPageTransport();

        $this->discover($transport);

        self::assertNotEmpty($transport->requests);
        self::assertSame(sprintf('Bearer %s', self::ENV_VALUE), $transport->requests[0]['headers']['Authorization']);
    }

    public function testTwoPagesProduceTheSameSetAsOnePage(): void
    {
        $paged  = $this->describe($this->discover($this->twoPageTransport()));
        $single = $this->describe($this->discover($this->singlePageTransport()));

        self::assertSame($single, $paged);
    }

    protected function setUp(): void
    {
        $this->previousToken = false === getenv('FIXTURE_DISCOVERY_TOKEN')
            ? null
            : (string) getenv('FIXTURE_DISCOVERY_TOKEN');

        putenv('FIXTURE_DISCOVERY_TOKEN=' . self::ENV_VALUE);
    }

    protected function tearDown(): void
    {
        if (null === $this->previousToken) {
            putenv('FIXTURE_DISCOVERY_TOKEN');

            return;
        }

        putenv("FIXTURE_DISCOVERY_TOKEN={$this->previousToken}");
    }

    /**
     * @param list<DiscoveredRepository> $repositories
     *
     * @return list<array{name: string, slug: string, branch: string, onboarded: bool, archived: bool}>
     */
    private function describe(array $repositories): array
    {
        return array_map(
            static fn(DiscoveredRepository $repository): array => [
                'name'      => $repository->name(),
                'slug'      => $repository->slug(),
                'branch'    => $repository->defaultBranch(),
                'onboarded' => $repository->onboarded(),
                'archived'  => $repository->archived(),
            ],
            $repositories,
        );
    }

    /**
     * @return list<DiscoveredRepository>
     */
    private function discover(HttpTransportInterface $transport): array
    {
        return new GitHubPropertyDiscovery(new GitHubApiClient(transport: $transport))
            ->discover(SourceFixture::config());
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

    /**
     * @return array<string, mixed>
     */
    private function propertyValue(string $fullName, mixed $value): array
    {
        return [
            'repository_full_name' => $fullName,
            'properties'           => [
                ['property_name' => 'docs', 'value' => $value],
                ['property_name' => 'other', 'value' => 'ignored'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function propertyValuesPage1(): array
    {
        return [
            $this->propertyValue('fixture-org/message-bus', 'true'),
            $this->propertyValue('fixture-org/job-queue', 'false'),
            $this->propertyValue('fixture-org/archived', 'true'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function propertyValuesPage2(): array
    {
        return [
            $this->propertyValue('fixture-org/theme-kit', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchPage(): array
    {
        return [
            'items' => [
                ['full_name' => 'fixture-org/message-bus', 'default_branch' => 'main', 'archived' => false],
                ['full_name' => 'fixture-org/job-queue', 'default_branch' => 'develop', 'archived' => false],
                ['full_name' => 'fixture-org/theme-kit', 'default_branch' => 'trunk', 'archived' => false],
                ['full_name' => 'fixture-org/archived', 'default_branch' => 'main', 'archived' => true],
            ],
        ];
    }

    /**
     * @param null|array<string, mixed> $searchOverride
     */
    private function singlePageTransport(?array $searchOverride = null): StubHttpTransport
    {
        return new StubHttpTransport([
            'properties/values'   => new HttpResponse(
                200,
                (string) json_encode([
                    ...$this->propertyValuesPage1(),
                    ...$this->propertyValuesPage2(),
                ]),
            ),
            'search/repositories' => new HttpResponse(
                200,
                (string) json_encode($searchOverride ?? $this->searchPage()),
            ),
        ]);
    }

    /**
     * @param null|array<string, mixed> $searchOverride
     */
    private function twoPageTransport(?array $searchOverride = null): StubHttpTransport
    {
        $second = 'https://api.github.com/orgs/fixture-org/properties/values?per_page=100&page=2';

        return new StubHttpTransport([
            'page=2'              => new HttpResponse(
                200,
                (string) json_encode($this->propertyValuesPage2()),
            ),
            'properties/values'   => new HttpResponse(
                200,
                (string) json_encode($this->propertyValuesPage1()),
                ['link' => sprintf('<%s>; rel="next"', $second)],
            ),
            'search/repositories' => new HttpResponse(
                200,
                (string) json_encode($searchOverride ?? $this->searchPage()),
            ),
        ]);
    }
}
