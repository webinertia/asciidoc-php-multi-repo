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

        self::assertTrue($section->excludes('docs.webinertia.net'));
        self::assertFalse($section->excludes('docs'));
    }

    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $section = OrganizationSection::fromArray([
            'name'         => 'webinertia',
            'propertyName' => 'doc-target',
        ]);

        self::assertSame('true', $section->onboardValue());
        self::assertNull($section->tokenEnvVar());
        self::assertSame([], $section->excludeRepositories());
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

        self::assertSame('webinertia', $section->name());
        self::assertSame('doc-target', $section->propertyName());
        self::assertSame('yes', $section->onboardValue());
        self::assertSame('DOC_DISCOVERY_TOKEN', $section->tokenEnvVar());
        self::assertSame(['docs.webinertia.net', '.github'], $section->excludeRepositories());
    }
}
