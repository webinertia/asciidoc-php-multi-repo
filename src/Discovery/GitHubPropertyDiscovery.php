<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Discovery;

use Override;
use Webware\AsciidocPhpMultiRepo\Config\OrganizationSection;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;

use function getenv;
use function is_string;
use function rawurlencode;
use function sprintf;
use function strrpos;
use function substr;

/**
 * Discovery backed by the GitHub organisation custom property.
 *
 * Two endpoints are needed: the property values say which repositories are onboarded, and the
 * repository search supplies the default branch and the archived flag. The token is read from the
 * environment variable named in the configuration so it never reaches a command line or a report.
 */
final class GitHubPropertyDiscovery extends AbstractProjectDiscovery
{
    public function __construct(
        private readonly GitHubApiClient $client = new GitHubApiClient(),
        ?SlugNormalizer $normalizer = null,
        ?SlugCollisionDetector $collisions = null,
    ) {
        parent::__construct(
            $normalizer ?? new SlugNormalizer(),
            $collisions ?? new SlugCollisionDetector(),
        );
    }

    private static function nameOf(string $fullName): string
    {
        $separator = strrpos(
            haystack: $fullName,
            needle  : '/',
        );

        return false === $separator
            ? $fullName
            : substr(
                string: $fullName,
                offset: $separator + 1,
            );
    }

    /**
     * @return list<DiscoveredRepository>
     *
     * @throws DiscoveryException when the token is missing or either endpoint fails
     * @throws SlugException      when a repository name cannot be turned into a slug
     */
    #[Override]
    protected function collect(SiteConfig $config): array
    {
        $organization = $config->organization();
        $token        = $this->token($organization);

        $onboarded = GitHubPropertyValues::fromPages(
            $this->client->get(
                sprintf(
                    '/orgs/%s/properties/values?per_page=%d',
                    rawurlencode($organization->name()),
                    $this->client->perPage(),
                ),
                $token,
                $organization,
            ),
            $organization,
        );

        $metadata = GitHubRepositoryMetadata::fromPages($this->client->get(
            sprintf(
                '/search/repositories?q=%s&per_page=%d',
                rawurlencode(sprintf('org:%s', $organization->name())),
                $this->client->perPage(),
            ),
            $token,
            $organization,
        ));

        $repositories = [];

        foreach ($onboarded->values() as $fullName => $isOnboarded) {
            $name  = self::nameOf($fullName);
            $entry = $metadata->for($fullName);

            $repositories[] = new DiscoveredRepository(
                $name,
                $this->normalizer->normalise($name),
                $entry['defaultBranch'],
                $entry['archived'],
                $isOnboarded,
                null,
            );
        }

        return $repositories;
    }

    private function failure(OrganizationSection $organization, string $detail): DiscoveryException
    {
        return new DiscoveryException(sprintf(
            'Discovery for organization "%s" property "%s" failed: %s',
            $organization->name(),
            $organization->propertyName(),
            $detail,
        ));
    }

    /**
     * @throws DiscoveryException when the configured environment variable is unset or empty
     */
    private function token(OrganizationSection $organization): string
    {
        $variable = $organization->tokenEnvVar();

        if (null === $variable || '' === $variable) {
            throw $this->failure($organization, 'organization.tokenEnvVar is not configured.');
        }

        $value = getenv($variable);

        if (! is_string($value) || '' === $value) {
            throw $this->failure($organization, sprintf('the environment variable %s is not set.', $variable));
        }

        return $value;
    }
}
