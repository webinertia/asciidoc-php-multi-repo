<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\DiscoverCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;
use WebwareTest\AsciidocPhp\MultiRepo\Support\StubHttpResponse;
use WebwareTest\AsciidocPhp\MultiRepo\Support\StubHttpTransport;

use function explode;
use function json_encode;
use function putenv;
use function rtrim;

#[CoversClass(DiscoverCommand::class)]
#[CoversMethod(DiscoverCommand::class, 'execute')]
#[CoversMethod(DiscoverCommand::class, 'name')]
#[CoversMethod(DiscoverCommand::class, 'summary')]
#[CoversClass(ProjectFilter::class)]
#[CoversMethod(ProjectFilter::class, 'apply')]
final class DiscoverCommandTest extends TestCase
{
    private CapturedOutput $captured;

    #[Test]
    public function aDiscoveryFailureExitsThree(): void
    {
        $exit = $this->executeCommand(
            config   : SourceFixture::config(),
            transport: $this->unauthorisedTransport(),
        );

        static::assertSame(ExitCode::DiscoveryError, $exit);
        static::assertSame('', $this->captured->stdout);
    }

    #[Test]
    public function aDiscoveryFailureNamesTheOrganizationAndProperty(): void
    {
        $this->executeCommand(
            config   : SourceFixture::config(),
            transport: $this->unauthorisedTransport(),
        );

        static::assertStringContainsString('fixture-org', $this->captured->stderr);
        static::assertStringContainsString('docs', $this->captured->stderr);
        static::assertStringContainsString('401', $this->captured->stderr);
        static::assertStringStartsWith('error: ', $this->captured->stderr);
    }

    #[Test]
    public function aMissingTokenExitsThree(): void
    {
        putenv('FIXTURE_DISCOVERY_TOKEN');

        $exit = $this->executeCommand(
            config   : SourceFixture::config(),
            transport: $this->collidingTransport(),
        );

        static::assertSame(ExitCode::DiscoveryError, $exit);
        static::assertStringContainsString('FIXTURE_DISCOVERY_TOKEN', $this->captured->stderr);
    }

    #[Test]
    public function aSlugCollisionExitsTwo(): void
    {
        $exit = $this->executeCommand(
            config   : SourceFixture::config(),
            transport: $this->collidingTransport(),
        );

        static::assertSame(ExitCode::ConfigError, $exit);
        static::assertStringContainsString('message-bus', $this->captured->stderr);
        static::assertStringContainsString('message.bus', $this->captured->stderr);
        static::assertSame('', $this->captured->stdout);
    }

    #[Test]
    public function nothingElseIsWrittenToStandardOutput(): void
    {
        $this->executeCommand(manifest: SourceFixture::path('manifest.json'));

        static::assertSame('', $this->captured->stderr);
        static::assertCount(6, $this->stdoutLines());
    }

    #[Test]
    public function theCommandNameAndSummaryAreSet(): void
    {
        $command = new DiscoverCommand();

        static::assertSame('discover', $command->name());
        static::assertNotSame('', $command->summary());
    }

    #[Test]
    public function theExcludedRepositoryIsNotPrinted(): void
    {
        $this->executeCommand(manifest: SourceFixture::path('manifest.json'));

        static::assertStringNotContainsString('archived', $this->captured->stdout);
    }

    #[Test]
    public function theFixtureManifestIsPrintedInSlugOrder(): void
    {
        $exit = $this->executeCommand(manifest: SourceFixture::path('manifest.json'));

        static::assertSame(ExitCode::Success, $exit);
        static::assertSame(
            [
                'name=broken-include slug=broken-include onboarded=yes',
                'name=markdown-only slug=markdown-only onboarded=yes',
                'name=missing-entry slug=missing-entry onboarded=yes',
                'name=mixed slug=mixed onboarded=yes',
                'name=unversioned slug=unversioned onboarded=yes',
                'name=versioned slug=versioned onboarded=yes',
            ],
            $this->stdoutLines(),
        );
    }

    #[Test]
    public function theProjectFilterAcceptsTheSlug(): void
    {
        $this->executeCommand(
            manifest: SourceFixture::path('manifest.json'),
            projects: ['markdown-only'],
        );

        static::assertSame(['name=markdown-only slug=markdown-only onboarded=yes'], $this->stdoutLines());
    }

    #[Test]
    public function theProjectFilterCanSelectNothing(): void
    {
        $exit = $this->executeCommand(
            manifest: SourceFixture::path('manifest.json'),
            projects: ['absent'],
        );

        static::assertSame(ExitCode::Success, $exit);
        static::assertSame('', $this->captured->stdout);
    }

    #[Test]
    public function theProjectFilterSelectsOneRepository(): void
    {
        $this->executeCommand(
            manifest: SourceFixture::path('manifest.json'),
            projects: ['versioned'],
        );

        static::assertSame(['name=versioned slug=versioned onboarded=yes'], $this->stdoutLines());
    }

    protected function setUp(): void
    {
        $this->captured = new CapturedOutput();

        putenv('FIXTURE_DISCOVERY_TOKEN=ghp_fixture_token_value');
    }

    protected function tearDown(): void
    {
        putenv('FIXTURE_DISCOVERY_TOKEN');
    }

    private function collidingTransport(): StubHttpTransport
    {
        return new StubHttpTransport([
            'properties/values'   => StubHttpResponse::create(
                200,
                (string) json_encode([
                    [
                        'repository_full_name' => 'fixture-org/message-bus',
                        'properties'           => [
                            ['property_name' => 'docs', 'value' => 'true'],
                        ],
                    ],
                    [
                        'repository_full_name' => 'fixture-org/message.bus',
                        'properties'           => [
                            ['property_name' => 'docs', 'value' => 'true'],
                        ],
                    ],
                ]),
            ),
            'search/repositories' => StubHttpResponse::create(200, (string) json_encode(['items' => []])),
        ]);
    }

    /**
     * @param list<string> $projects
     */
    private function executeCommand(
        ?string $manifest = null,
        ?SiteConfig $config = null,
        ?StubHttpTransport $transport = null,
        array $projects = [],
    ): ExitCode {
        $command = new DiscoverCommand(
            new DiscoveryFactory(transport: $transport ?? new StubHttpTransport([])),
        );

        return $command->execute(
            new Options(
                command : 'discover',
                projects: $projects,
                manifest: $manifest,
            ),
            $config ?? SourceFixture::config(),
            $this->makeOutput(),
        );
    }

    private function makeOutput(): Output
    {
        return new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );
    }

    /**
     * @return list<string>
     */
    private function stdoutLines(): array
    {
        $trimmed = rtrim(
            string    : $this->captured->stdout,
            characters: "\n",
        );

        return (
            '' === $trimmed
                ? []
                : explode(
                    separator: "\n",
                    string   : $trimmed,
                )
        );
    }

    private function unauthorisedTransport(): StubHttpTransport
    {
        return new StubHttpTransport([
            'properties/values' => StubHttpResponse::create(401, '{"message":"Bad credentials"}'),
        ]);
    }
}
