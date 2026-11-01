<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ProjectOverride;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function sys_get_temp_dir;

#[CoversClass(SiteConfig::class)]
final class SiteConfigTest extends TestCase
{
    private static function configPath(): string
    {
        return Path::join(sys_get_temp_dir(), 'site-fixture', 'site.json');
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(): array
    {
        return [
            'schemaVersion' => 1,
            'site'          => [
                'name'    => 'Webware Documentation',
                'baseUrl' => 'https://docs.webinertia.net',
            ],
            'organization'  => [
                'name'         => 'webinertia',
                'propertyName' => 'doc-target',
            ],
            'output'        => [
                'directory'      => 'public',
                'cacheDirectory' => 'cache',
                'reportPath'     => 'reports/report.json',
            ],
            'projects'      => [
                'messagebus-event' => ['title' => 'Message Bus Event', 'order' => 20],
                'message-bus'      => ['title' => 'Message Bus', 'order' => 10],
            ],
        ];
    }

    public function testAbsoluteOutputPathsAreKept(): void
    {
        $absolute           = Path::join(sys_get_temp_dir(), 'elsewhere', 'site');
        $document           = self::document();
        $document['output'] = ['directory' => $absolute];

        $config = SiteConfig::fromArray($document, self::configPath());

        self::assertSame(Path::normalise($absolute), $config->output()->directory());
    }

    public function testConfigPathIsNormalised(): void
    {
        self::assertSame(Path::normalise(self::configPath()), $this->config()->configPath());
        self::assertSame(Path::join(sys_get_temp_dir(), 'site-fixture'), $this->config()->configDirectory());
    }

    public function testOrganizationSectionIsRead(): void
    {
        self::assertSame('doc-target', $this->config()->organization()->propertyName());
    }

    public function testOutputSectionIsResolvedAgainstTheConfigDirectory(): void
    {
        $directory = Path::join(sys_get_temp_dir(), 'site-fixture');
        $output    = $this->config()->output();

        self::assertSame(Path::join($directory, 'public'), $output->directory());
        self::assertSame(Path::join($directory, 'cache'), $output->cacheDirectory());
        self::assertSame(Path::join($directory, 'reports', 'report.json'), $output->reportPath());
    }

    public function testProjectLookupReturnsNullForUnknownName(): void
    {
        self::assertNull($this->config()->project('missing'));
        self::assertSame(20, $this->config()->project('messagebus-event')?->order());
    }

    public function testProjectsAreReadAndSortedByName(): void
    {
        $projects = $this->config()->projects();

        self::assertSame(['message-bus', 'messagebus-event'], array_keys($projects));
        self::assertInstanceOf(ProjectOverride::class, $projects['message-bus']);
        self::assertSame('Message Bus', $projects['message-bus']->title());
    }

    public function testRenderSectionUsesDefaults(): void
    {
        self::assertSame(4, $this->config()->render()->concurrency());
    }

    public function testSchemaVersionIsRead(): void
    {
        self::assertSame(1, $this->config()->schemaVersion());
    }

    public function testSiteSectionIsRead(): void
    {
        self::assertSame('Webware Documentation', $this->config()->site()->name());
    }

    public function testSourceSectionUsesDefaults(): void
    {
        self::assertSame('docs', $this->config()->source()->directory());
    }

    public function testThemeSectionUsesDefaults(): void
    {
        self::assertSame('webware', $this->config()->theme()->name());
    }

    public function testWithSiteReturnsANewInstance(): void
    {
        $config  = $this->config();
        $updated = $config->withSite($config->site()->withBasePath('/docs'));

        self::assertNotSame($config, $updated);
        self::assertSame('/docs/', $updated->site()->basePath());
        self::assertSame('/', $config->site()->basePath());
        self::assertSame($config->configPath(), $updated->configPath());
    }

    private function config(): SiteConfig
    {
        return SiteConfig::fromArray(self::document(), self::configPath());
    }
}
