<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use Laminas\Diactoros\Uri;
use Override;

/**
 * A URI that yields whatever it was given, including bytes the real implementation would sanitise,
 * so the transport's control-character check is reachable from a test.
 */
final class UnsafeUri extends Uri
{
    public function __construct(
        private readonly string $rawUri,
    ) {
        parent::__construct();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->rawUri;
    }
}
