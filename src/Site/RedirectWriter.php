<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Render\PageShell;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;

use function preg_match;

/**
 * Writes the document a project's own directory serves, which sends the reader to the version the
 * project currently publishes.
 *
 * The document is deliberately scripting-independent: the meta refresh does the work, the canonical
 * link tells crawlers where the content really lives, `noindex` keeps the redirect out of search
 * results, and the plain link keeps the page usable when refresh is disabled.
 *
 * @api
 */
final readonly class RedirectWriter
{
    private const string NUMERIC_LABEL = '/^v([1-9][0-9]*)$/';

    public function __construct(
        private ContentWriter $writer = new ContentWriter(),
        private PageShell $shell = new PageShell(),
    ) {}

    /**
     * The version a project directory redirects to: the highest numeric version, else `latest`.
     *
     * @param list<string> $labels
     */
    public static function targetLabel(array $labels): string
    {
        $target  = DocVersion::LATEST;
        $highest = 0;

        foreach ($labels as $label) {
            if (1 !== preg_match(self::NUMERIC_LABEL, $label, $matches)) {
                continue;
            }

            $number = (int) $matches[1];

            if ($number > $highest) {
                $highest = $number;
                $target  = $label;
            }
        }

        return $target;
    }

    /**
     * @throws RuntimeException when the redirect template cannot be read
     */
    public function document(string $label, string $targetUrl, string $canonicalUrl): string
    {
        return $this->shell->renderTemplate('redirect.php', [
            'label'        => $label,
            'targetUrl'    => $targetUrl,
            'canonicalUrl' => $canonicalUrl,
            'e'            => $this->shell->escape(...),
        ]);
    }

    /**
     * @return string the output-relative path written
     *
     * @throws RuntimeException when the redirect template cannot be read
     */
    public function write(
        string $outputDirectory,
        string $slug,
        string $label,
        string $targetUrl,
        string $canonicalUrl,
    ): string {
        $path = "{$slug}/index.html";

        $this->writer->write($outputDirectory, $path, $this->document($label, $targetUrl, $canonicalUrl));

        return $path;
    }
}
