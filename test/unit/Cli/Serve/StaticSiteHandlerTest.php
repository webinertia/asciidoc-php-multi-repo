<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli\Serve;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticResponse;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticSiteHandler;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;

#[CoversClass(StaticSiteHandler::class)]
#[CoversMethod(StaticSiteHandler::class, 'handle')]
#[CoversMethod(StaticSiteHandler::class, 'resolve')]
#[CoversClass(StaticResponse::class)]
#[CoversMethod(StaticResponse::class, 'body')]
#[CoversMethod(StaticResponse::class, 'contentType')]
#[CoversMethod(StaticResponse::class, 'headerBlock')]
#[CoversMethod(StaticResponse::class, 'methodNotAllowed')]
#[CoversMethod(StaticResponse::class, 'notFound')]
#[CoversMethod(StaticResponse::class, 'ok')]
#[CoversMethod(StaticResponse::class, 'reasonPhrase')]
#[CoversMethod(StaticResponse::class, 'status')]
final class StaticSiteHandlerTest extends TestCase
{
    use TempDirectoryTrait;

    private string $root = '';

    private Filesystem $filesystem;

    #[Test]
    public function aBackslashSegmentIsTreatedAsUnknown(): void
    {
        static::assertSame(404, $this->handler()->resolve('/versioned%5c..%5csecret')->status());
    }

    #[Test]
    public function aBasePathIsStrippedBeforeResolution(): void
    {
        $handler = $this->handler('/docs/');

        static::assertSame('version one entry', $handler->resolve('/docs/versioned/v1/')->body());
        static::assertSame(404, $handler->resolve('/versioned/v1/')->status());
    }

    #[Test]
    public function aBasePathRootServesTheIndexDocument(): void
    {
        $response = $this->handler('/docs/')->resolve('/docs');

        static::assertSame(200, $response->status());
        static::assertSame('root index', $response->body());
    }

    #[Test]
    public function aCleanPathServesTheHtmlDocument(): void
    {
        $response = $this->handler()->resolve('/versioned/v1/guide');

        static::assertSame(200, $response->status());
        static::assertSame('version one guide', $response->body());
    }

    #[Test]
    public function aControlCharacterInASegmentIsTreatedAsUnknown(): void
    {
        static::assertSame(404, $this->handler()->resolve('/%01index.html')->status());
    }

    #[Test]
    public function aFragmentWithoutAQueryStringIsIgnored(): void
    {
        static::assertSame('version one guide', $this->handler()->resolve('/versioned/v1/guide.html#top')->body());
    }

    #[Test]
    public function anEncodedTraversalIsTreatedAsUnknown(): void
    {
        static::assertSame(404, $this->handler()->resolve('/%2e%2e/secret')->status());
    }

    #[Test]
    public function aNulByteInThePathIsTreatedAsUnknown(): void
    {
        static::assertSame(404, $this->handler()->resolve('/index%00.html')->status());
    }

    #[Test]
    public function anUnknownExtensionIsServedAsOctetStream(): void
    {
        $this->page('assets/blob.zzz', 'blob');

        static::assertSame('application/octet-stream', $this->handler()->resolve('/assets/blob.zzz')->contentType());
    }

    #[Test]
    public function anUnknownPathFallsBackWhenTheSiteHasNoNotFoundDocument(): void
    {
        $this->filesystem->remove(Path::join($this->root, '404.html'));

        $response = $this->handler()->resolve('/does-not-exist');

        static::assertSame(404, $response->status());
        static::assertStringContainsString('404 Not Found', $response->body());
    }

    #[Test]
    public function anUnknownPathServesTheSiteNotFoundDocument(): void
    {
        $response = $this->handler()->resolve('/does-not-exist');

        static::assertSame(404, $response->status());
        static::assertSame('the site 404 document', $response->body());
    }

    #[Test]
    public function anUnsupportedMethodIsRejected(): void
    {
        $response = $this->handler()->handle('POST', '/');

        static::assertSame(405, $response->status());
        static::assertSame('Method Not Allowed', $response->reasonPhrase());
    }

    #[Test]
    public function aParentTraversalIsTreatedAsUnknown(): void
    {
        static::assertSame(404, $this->handler()->resolve('/../secret')->status());
        static::assertSame(404, $this->handler()->resolve('/versioned/../../secret')->status());
    }

    #[Test]
    public function aQueryStringAndFragmentAreIgnored(): void
    {
        static::assertSame(
            'version one guide',
            $this->handler()->resolve('/versioned/v1/guide.html?x=1#top')->body(),
        );
    }

    #[Test]
    public function aSlugServesTheRedirectDocument(): void
    {
        $response = $this->handler()->resolve('/versioned/');

        static::assertSame(200, $response->status());
        static::assertSame('redirect to v2', $response->body());
    }

    #[Test]
    public function aSlugWithoutATrailingSlashServesTheSameRedirectDocument(): void
    {
        static::assertSame('redirect to v2', $this->handler()->resolve('/versioned')->body());
    }

    #[Test]
    public function aTargetThatIsNotAbsoluteIsTreatedAsUnknown(): void
    {
        static::assertSame(404, $this->handler()->resolve('versioned/v1/guide.html')->status());
    }

    #[Test]
    public function aVersionPathServesTheVersionEntryPage(): void
    {
        $response = $this->handler()->resolve('/versioned/v1/');

        static::assertSame(200, $response->status());
        static::assertSame('version one entry', $response->body());
    }

    #[Test]
    public function binaryAssetsDoNotCarryACharacterSet(): void
    {
        static::assertSame('font/woff2', $this->handler()->resolve('/assets/font.woff2')->contentType());
    }

    #[Test]
    public function getAndHeadAreBothServed(): void
    {
        static::assertSame(200, $this->handler()->handle('GET', '/')->status());
        static::assertSame(200, $this->handler()->handle('head', '/')->status());
    }

    #[Test]
    public function textualAssetsCarryACharacterSet(): void
    {
        static::assertSame('text/css; charset=utf-8', $this->handler()->resolve('/assets/site.css')->contentType());
        static::assertSame(
            'text/javascript; charset=utf-8',
            $this->handler()->resolve('/assets/theme.js')->contentType(),
        );
    }

    #[Test]
    public function theHeadersDescribeTheBody(): void
    {
        $response = $this->handler()->resolve('/');
        $headers  = $response->headerBlock();

        static::assertStringStartsWith('HTTP/1.1 200 OK', $headers);
        static::assertStringContainsString('Content-Length: 10', $headers);
        static::assertStringContainsString('Cache-Control: no-store', $headers);
        static::assertStringContainsString('Connection: close', $headers);
        static::assertStringEndsWith("\r\n\r\n", $headers);
    }

    #[Test]
    public function theRootServesTheIndexDocument(): void
    {
        $response = $this->handler()->resolve('/');

        static::assertSame(200, $response->status());
        static::assertSame('root index', $response->body());
        static::assertSame('text/html; charset=utf-8', $response->contentType());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->root       = $this->createTempDirectory();

        $this->page('index.html', 'root index');
        $this->page('versioned/index.html', 'redirect to v2');
        $this->page('versioned/v1/index.html', 'version one entry');
        $this->page('versioned/v1/guide.html', 'version one guide');
        $this->page('versioned/v2/index.html', 'version two entry');
        $this->page('assets/site.css', 'body{}');
        $this->page('assets/theme.js', 'void 0;');
        $this->page('assets/font.woff2', 'binary');
        $this->page('404.html', 'the site 404 document');
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    private function handler(string $basePath = '/'): StaticSiteHandler
    {
        return new StaticSiteHandler($this->root, $basePath);
    }

    private function page(string $relative, string $contents): void
    {
        $path = Path::join($this->root, $relative);

        $this->filesystem->ensureDirectory(dirname($path));
        $this->filesystem->writeAtomic($path, $contents);
    }
}
