<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Discovery;

use Psr\Http\Client\ClientInterface;
use Webware\AsciidocPhp\MultiRepo\Config\ManifestLoader;
use Webware\AsciidocPhp\MultiRepo\Http\CurlHttpTransport;

/**
 * Chooses the discovery source: a manifest when one was named, the organisation property otherwise.
 *
 * Keeping the choice in one place is what lets a test prove that manifest discovery never touches
 * the network: it hands the factory a transport that fails on use.
 */
final class DiscoveryFactory
{
    public function __construct(
        private readonly ManifestLoader $manifestLoader = new ManifestLoader(),
        private readonly ClientInterface $transport = new CurlHttpTransport(),
        private readonly SlugNormalizer $normalizer = new SlugNormalizer(),
        private readonly SlugCollisionDetector $collisions = new SlugCollisionDetector(),
        private readonly string $apiBase = 'https://api.github.com',
    ) {}

    /**
     * @throws \Webware\AsciidocPhp\MultiRepo\Config\ConfigException when the manifest is unusable
     */
    public function create(?string $manifestPath): ProjectDiscoveryInterface
    {
        if (null === $manifestPath) {
            return new GitHubPropertyDiscovery(
                new GitHubApiClient(
                    transport: $this->transport,
                    apiBase  : $this->apiBase,
                ),
                $this->normalizer,
                $this->collisions,
            );
        }

        return new ManifestDiscovery(
            $this->manifestLoader->load($manifestPath),
            $this->normalizer,
            $this->collisions,
        );
    }
}
