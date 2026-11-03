<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\OrganizationSection;

#[CoversClass(OrganizationSection::class)]
final class OrganizationSectionTest extends TestCase
{
    #[Test]
    public function excludesMatchesExactly(): void
    {
        $section = OrganizationSection::fromArray([
            'name'                => 'webinertia',
            'propertyName'        => 'doc-target',
            'excludeRepositories' => ['docs.webinertia.net'],
        ]);

        static::assertTrue($section->excludes('docs.webinertia.net'));
        static::assertFalse($section->excludes('docs'));
    }

    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $section = OrganizationSection::fromArray([
            'name'         => 'webinertia',
            'propertyName' => 'doc-target',
        ]);

        static::assertSame('true', $section->onboardValue());
        static::assertNull($section->tokenEnvVar());
        static::assertSame([], $section->excludeRepositories());
    }

    #[Test]
    public function fromArrayReadsEveryField(): void
    {
        $section = OrganizationSection::fromArray([
            'name'                => 'webinertia',
            'propertyName'        => 'doc-target',
            'onboardValue'        => 'yes',
            'tokenEnvVar'         => 'DOC_DISCOVERY_TOKEN',
            'excludeRepositories' => ['docs.webinertia.net', '.github'],
        ]);

        static::assertSame('webinertia', $section->name());
        static::assertSame('doc-target', $section->propertyName());
        static::assertSame('yes', $section->onboardValue());
        static::assertSame('DOC_DISCOVERY_TOKEN', $section->tokenEnvVar());
        static::assertSame(['docs.webinertia.net', '.github'], $section->excludeRepositories());
    }
}
