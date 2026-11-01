<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\OptionsParser;
use Webware\AsciidocPhpMultiRepo\Cli\UsageException;

#[CoversClass(OptionsParser::class)]
final class OptionsParserTest extends TestCase
{
    private OptionsParser $parser;

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function invalidArgumentLists(): array
    {
        return [
            'unknown long option'         => [['--nope'], 'unknown option "--nope"'],
            'unknown short option'        => [['-z'], 'unknown option "-z"'],
            'unknown short in cluster'    => [['-qz'], 'unknown option "-qz"'],
            'missing value'               => [['--config'], 'option "--config" requires a value'],
            'empty inline value'          => [['--config='], 'option "--config" requires a value'],
            'missing short value'         => [['-c'], 'option "-c" requires a value'],
            'flag with inline value'      => [['--full=yes'], 'option "--full" does not take a value'],
            'second positional'           => [['build', 'extra'], 'unexpected argument "extra"'],
            'bare dash'                   => [['-'], 'unexpected argument "-"'],
            'positional after terminator' => [['build', '--', 'extra'], 'unexpected argument "extra"'],
            'non numeric concurrency'     => [
                ['--concurrency', 'many'],
                'invalid value "many" for option "--concurrency": expected a whole number',
            ],
            'zero concurrency'            => [
                ['--concurrency', '0'],
                'invalid value "0" for option "--concurrency": expected a whole number of at least 1',
            ],
            'non numeric port'            => [
                ['--port', '-1'],
                'invalid value "-1" for option "--port": expected a whole number',
            ],
            'port above range'            => [
                ['--port', '70000'],
                'invalid value "70000" for option "--port": expected a whole number between 1 and 65535',
            ],
            'ref without revision'        => [
                ['--ref', 'main'],
                'invalid value "main" for option "--ref": expected name=revision',
            ],
            'ref without name'            => [
                ['--ref', '=main'],
                'invalid value "=main" for option "--ref": expected name=revision',
            ],
            'ref without value'           => [
                ['--ref', 'main='],
                'invalid value "main=" for option "--ref": expected name=revision',
            ],
        ];
    }

    public function testBooleanFlags(): void
    {
        $options = $this->parser->parse([
            'build',
            '--full',
            '--force',
            '--quiet',
            '--verbose',
            '--timings',
            '--no-color',
            '--help',
            '--version',
        ]);

        self::assertTrue($options->full);
        self::assertTrue($options->force);
        self::assertTrue($options->quiet);
        self::assertTrue($options->verbose);
        self::assertTrue($options->timings);
        self::assertTrue($options->noColor);
        self::assertTrue($options->help);
        self::assertTrue($options->version);
    }

    public function testConfigLongOptionWithInlineValue(): void
    {
        self::assertSame('docs/site.json', $this->parser->parse(['--config=docs/site.json'])->config);
    }

    public function testConfigLongOptionWithSeparateValue(): void
    {
        self::assertSame('docs/site.json', $this->parser->parse(['--config', 'docs/site.json'])->config);
    }

    public function testConfigShortOption(): void
    {
        self::assertSame('docs/site.json', $this->parser->parse(['build', '-c', 'docs/site.json'])->configPath());
    }

    public function testDoubleDashEndsOptionParsing(): void
    {
        $options = $this->parser->parse(['--', 'build']);

        self::assertSame('build', $options->command);
    }

    public function testEmptyArgumentListProducesDefaults(): void
    {
        $options = $this->parser->parse([]);

        self::assertSame('', $options->command);
        self::assertSame('site.json', $options->configPath());
        self::assertSame(Options::DEFAULT_PORT, $options->port);
    }

    public function testFirstPositionalIsTheCommand(): void
    {
        self::assertSame('build', $this->parser->parse(['build'])->command);
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('invalidArgumentLists')]
    public function testInvalidArgumentsAreRejected(array $arguments, string $expectedMessage): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->parser->parse($arguments);
    }

    public function testOutOptionAcceptsShortLongAndDocumentedAlias(): void
    {
        self::assertSame('build/site', $this->parser->parse(['-o', 'build/site'])->out);
        self::assertSame('build/site', $this->parser->parse(['--out', 'build/site'])->out);
        self::assertSame('build/site', $this->parser->parse(['--output', 'build/site'])->out);
    }

    public function testProjectIsRepeatableAndOrdered(): void
    {
        $options = $this->parser->parse(['build', '--project', 'message-bus', '--project=messagebus-event']);

        self::assertSame(['message-bus', 'messagebus-event'], $options->projects);
    }

    public function testRefAcceptsARevisionContainingAnEqualsSign(): void
    {
        $options = $this->parser->parse(['--ref', 'message-bus=feature=thing']);

        self::assertSame(['message-bus' => 'feature=thing'], $options->refs);
    }

    public function testRefIsRepeatableAndKeyedByRepository(): void
    {
        $options = $this->parser->parse(['build', '--ref', 'message-bus=main', '--ref=webware-tools=v2']);

        self::assertSame(
            ['message-bus' => 'main', 'webware-tools' => 'v2'],
            $options->refs,
        );
    }

    public function testRemainingValueOptions(): void
    {
        $options = $this->parser->parse([
            'build',
            '--cache',
            'build/cache',
            '--report',
            'build/report.json',
            '--concurrency',
            '8',
            '--manifest',
            'tests/fixtures/manifest.json',
            '--base-path',
            '/docs/',
            '--port',
            '9000',
        ]);

        self::assertSame('build/cache', $options->cache);
        self::assertSame('build/report.json', $options->report);
        self::assertSame(8, $options->concurrency);
        self::assertSame('tests/fixtures/manifest.json', $options->manifest);
        self::assertSame('/docs/', $options->basePath);
        self::assertSame(9000, $options->port);
    }

    public function testShortBooleanFlagsMayBeClustered(): void
    {
        $options = $this->parser->parse(['build', '-qvt']);

        self::assertTrue($options->quiet);
        self::assertTrue($options->verbose);
        self::assertTrue($options->timings);
    }

    public function testShortHelpFlag(): void
    {
        self::assertTrue($this->parser->parse(['-h'])->help);
    }

    protected function setUp(): void
    {
        $this->parser = new OptionsParser();
    }
}
