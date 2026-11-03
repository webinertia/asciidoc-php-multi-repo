<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function absoluteOutputPathsAreKept(): void
    {
        $absolute           = Path::join(sys_get_temp_dir(), 'elsewhere', 'site');
        $document           = self::document();
        $document['output'] = ['directory' => $absolute];

        $config = SiteConfig::fromArray($document, self::configPath());

        static::assertSame(Path::normalise($absolute), $config->output()->directory());
    }

    #[Test]
    public function configPathIsNormalised(): void
    {
        static::assertSame(Path::normalise(self::configPath()), $this->config()->configPath());
        static::assertSame(Path::join(sys_get_temp_dir(), 'site-fixture'), $this->config()->configDirectory());
    }

    #[Test]
    public function organizationSectionIsRead(): void
    {
        static::assertSame('doc-target', $this->config()->organization()->propertyName());
    }

    #[Test]
    public function outputSectionIsResolvedAgainstTheConfigDirectory(): void
    {
        $directory = Path::join(sys_get_temp_dir(), 'site-fixture');
        $output    = $this->config()->output();

        static::assertSame(Path::join($directory, 'public'), $output->directory());
        static::assertSame(Path::join($directory, 'cache'), $output->cacheDirectory());
        static::assertSame(Path::join($directory, 'reports', 'report.json'), $output->reportPath());
    }

    #[Test]
    public function projectLookupReturnsNullForUnknownName(): void
    {
        static::assertNull($this->config()->project('missing'));
        static::assertSame(20, $this->config()->project('messagebus-event')?->order());
    }

    #[Test]
    public function projectsAreReadAndSortedByName(): void
    {
        $projects = $this->config()->projects();

        static::assertSame(['message-bus', 'messagebus-event'], array_keys($projects));
        static::assertInstanceOf(ProjectOverride::class, $projects['message-bus']);
        static::assertSame('Message Bus', $projects['message-bus']->title());
    }

    #[Test]
    public function renderSectionUsesDefaults(): void
    {
        static::assertSame(4, $this->config()->render()->concurrency());
    }

    #[Test]
    public function schemaVersionIsRead(): void
    {
        static::assertSame(1, $this->config()->schemaVersion());
    }

    #[Test]
    public function siteSectionIsRead(): void
    {
        static::assertSame('Webware Documentation', $this->config()->site()->name());
    }

    #[Test]
    public function sourceSectionUsesDefaults(): void
    {
        static::assertSame('docs', $this->config()->source()->directory());
    }

    #[Test]
    public function themeSectionUsesDefaults(): void
    {
        static::assertSame('webware', $this->config()->theme()->name());
    }

    #[Test]
    public function withSiteReturnsANewInstance(): void
    {
        $config  = $this->config();
        $updated = $config->withSite($config->site()->withBasePath('/docs'));

        static::assertNotSame($config, $updated);
        static::assertSame('/docs/', $updated->site()->basePath());
        static::assertSame('/', $config->site()->basePath());
        static::assertSame($config->configPath(), $updated->configPath());
    }

    private function config(): SiteConfig
    {
        return SiteConfig::fromArray(self::document(), self::configPath());
    }
}
