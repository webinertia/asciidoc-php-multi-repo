<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use InvalidArgumentException;
use Laminas\Diactoros\RequestFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use Throwable;
use Webware\AsciidocPhp\MultiRepo\Config\OrganizationSection;
use Webware\AsciidocPhp\MultiRepo\Http\CurlHttpTransport;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\JsonDecodeException;

use function array_key_exists;
use function count;
use function rtrim;
use function sprintf;

/**
 * The authenticated, paginated read side of the GitHub REST API.
 *
 * The token travels as a parameter marked `#[SensitiveParameter]` so it cannot reach a backtrace,
 * and it never appears in an exception message: failures name the organisation and the property.
 *
 * The transport is any PSR-18 client, so the network layer is swappable without touching this
 * class, and the request is built through the PSR-17 factory rather than by this class directly.
 */
final class GitHubApiClient
{
    private const int PER_PAGE = 100;

    private const int MAX_PAGES = 100;

    public function __construct(
        private readonly ClientInterface $transport = new CurlHttpTransport(),
        private readonly string $apiBase = 'https://api.github.com',
        private readonly Json $json = new Json(),
        private readonly RequestFactoryInterface $requestFactory = new RequestFactory(),
    ) {}

    /**
     * Reads every page of a list endpoint.
     *
     * @return list<mixed>
     *
     * @throws DiscoveryException
     */
    public function get(
        string $path,
        #[SensitiveParameter]
        string $token,
        OrganizationSection $organization,
    ): array {
        return $this->pages($this->url($path), $token, $organization);
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    public function url(string $path): string
    {
        return (
            rtrim(
                string    : $this->apiBase,
                characters: '/',
            ) . $path
        );
    }

    /**
     * @throws DiscoveryException when the body is not valid JSON
     */
    private function decode(ResponseInterface $response, OrganizationSection $organization): mixed
    {
        try {
            return $this->json->decode((string) $response->getBody(), 'GitHub response');
        } catch (JsonDecodeException $exception) {
            throw $this->failure($organization, 'the response was not valid JSON.', $exception);
        }
    }

    private function failure(
        OrganizationSection $organization,
        string $detail,
        ?Throwable $previous = null,
    ): DiscoveryException {
        return new DiscoveryException(
            sprintf(
                'Discovery for organization "%s" property "%s" failed: %s',
                $organization->name(),
                $organization->propertyName(),
                $detail,
            ),
            $previous,
        );
    }

    /**
     * @return list<mixed>
     *
     * @throws DiscoveryException when a page cannot be read or decoded
     */
    private function pages(
        string $url,
        #[SensitiveParameter]
        string $token,
        OrganizationSection $organization,
    ): array {
        $pages   = [];
        $visited = [];
        $next    = $url;

        while (null !== $next && ! array_key_exists($next, $visited) && count($pages) < self::MAX_PAGES) {
            $visited[$next] = true;
            $response       = $this->request($next, $token, $organization);

            $pages[] = $this->decode($response, $organization);
            $next    = GitHubLinkHeader::next($response->getHeaderLine('link'));
        }

        return $pages;
    }

    /**
     * A rejected header name or value is reported without the message the message implementation
     * raised, because that message quotes the value and the value may be the token.
     *
     * @throws DiscoveryException when the transport fails or answers a non-success status
     */
    private function request(
        string $url,
        #[SensitiveParameter]
        string $token,
        OrganizationSection $organization,
    ): ResponseInterface {
        try {
            $request = $this->requestFactory
                ->createRequest('GET', $url)
                ->withHeader('Accept', 'application/vnd.github+json')
                ->withHeader('Authorization', sprintf('Bearer %s', $token))
                ->withHeader('X-GitHub-Api-Version', '2022-11-28');

            $response = $this->transport->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw $this->failure($organization, $exception->getMessage(), $exception);
        } catch (InvalidArgumentException) {
            throw $this->failure($organization, 'the request could not be built.');
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status > 299) {
            throw $this->failure(
                $organization,
                sprintf('GitHub answered HTTP %d.', $status),
            );
        }

        return $response;
    }
}
