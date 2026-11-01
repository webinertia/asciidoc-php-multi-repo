<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Support;

use RuntimeException;

use function sprintf;
use function trim;

/**
 * Raised when an external process exits non-zero or exceeds its timeout.
 */
final class ProcessFailedException extends RuntimeException
{
    public function __construct(
        private readonly ProcessResult $result,
    ) {
        $stderr = trim($result->stderr());

        parent::__construct(sprintf(
            '%s%s',
            $result->describeFailure(),
            '' === $stderr ? '' : " stderr: {$stderr}",
        ));
    }

    public function result(): ProcessResult
    {
        return $this->result;
    }
}
