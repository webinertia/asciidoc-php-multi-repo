<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\OrganizationSection;

final class OrganizationSectionTest extends TestCase
{
    public function testFromArrayReadsEveryField(): void
    {
        $section = OrganizationSection::fromArray([
            'name'                => 'webinertia',
            'propertyName'        => 'doc-target',
            'onboardValue'        => 'yes',
            'tokenEnvVar'         => 'DOC_DISCOVERY_TOKEN',
            'excludeRepositories' => ['docs.webinertia.net', '.github'],
        ]);

        self::assertSame('webinertia', $section->name());
        self::assertSame('doc-target', $section->propertyName());
        self::assertSame('yes', $section->onboardValue());
        self::assertSame('DOC_DISCOVERY_TOKEN', $section->tokenEnvVar());
        self::assertSame(['docs.webinertia.net', '.github'], $section->excludeRepositories());
    }

    public function testFromArrayAppliesDefaults(): void
    {
        $section = OrganizationSection::fromArray([
            'name'         => 'webinertia',
            'propertyName' => 'doc-target',
        ]);

        self::assertSame('true', $section->onboardValue());
        self::assertNull($section->tokenEnvVar());
        self::assertSame([], $section->excludeRepositories());
    }

    public function testExcludesMatchesExactly(): void
    {
        $section = OrganizationSection::fromArray([
            'name'                => 'webinertia',
            'propertyName'        => 'doc-target',
            'excludeRepositories' => ['docs.webinertia.net'],
        ]);

        self::assertTrue($section->excludes('docs.webinertia.net'));
        self::assertFalse($section->excludes('docs'));
    }
}
