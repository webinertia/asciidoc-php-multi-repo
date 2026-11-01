<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use SensitiveParameter;
use Throwable;
use Webware\AsciidocPhpMultiRepo\Config\OrganizationSection;
use Webware\AsciidocPhpMultiRepo\Http\CurlHttpTransport;
use Webware\AsciidocPhpMultiRepo\Http\HttpException;
use Webware\AsciidocPhpMultiRepo\Http\HttpResponse;
use Webware\AsciidocPhpMultiRepo\Http\HttpTransportInterface;
use Webware\AsciidocPhpMultiRepo\Support\Json;
use Webware\AsciidocPhpMultiRepo\Support\JsonDecodeException;

use function array_key_exists;
use function count;
use function rtrim;
use function sprintf;

/**
 * The authenticated, paginated read side of the GitHub REST API.
 *
 * The token travels as a parameter marked `#[SensitiveParameter]` so it cannot reach a backtrace,
 * and it never appears in an exception message: failures name the organisation and the property.
 */
final class GitHubApiClient
{
    private const int PER_PAGE = 100;

    private const int MAX_PAGES = 100;

    public function __construct(
        private readonly HttpTransportInterface $transport = new CurlHttpTransport(),
        private readonly string $apiBase = 'https://api.github.com',
        private readonly Json $json = new Json(),
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
    private function decode(HttpResponse $response, OrganizationSection $organization): mixed
    {
        try {
            return $this->json->decode($response->body(), 'GitHub response');
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
            $next    = GitHubLinkHeader::next($response->header('link'));
        }

        return $pages;
    }

    /**
     * @throws DiscoveryException when the transport fails or answers a non-success status
     */
    private function request(
        string $url,
        #[SensitiveParameter]
        string $token,
        OrganizationSection $organization,
    ): HttpResponse {
        try {
            $response = $this->transport->get($url, [
                'Accept'               => 'application/vnd.github+json',
                'Authorization'        => sprintf('Bearer %s', $token),
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
        } catch (HttpException $exception) {
            throw $this->failure($organization, $exception->getMessage(), $exception);
        }

        if (! $response->successful()) {
            throw $this->failure(
                $organization,
                sprintf('GitHub answered HTTP %d.', $response->status()),
            );
        }

        return $response;
    }
}
