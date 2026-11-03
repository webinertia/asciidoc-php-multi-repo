<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Site;

use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Render\PageShell;

use function array_map;

/**
 * Writes the files that are not a page of any project: the GitHub Pages opt-out, the custom domain,
 * the not-found page, and the hashed theme assets.
 *
 * `.nojekyll` is unconditional because the published tree already contains directories whose names
 * begin with an underscore, which Jekyll would otherwise drop. `CNAME` is written only when a
 * hostname is configured, and carries exactly that hostname plus a trailing newline, which is what
 * GitHub Pages expects and what a byte comparison can assert.
 *
 * @api
 */
final readonly class StaticAssetWriter
{
    public const string CNAME_FILE = 'CNAME';

    public const string NOJEKYLL_FILE = '.nojekyll';

    public const string NOT_FOUND_FILE = '404.html';

    public function __construct(
        private ContentWriter $writer = new ContentWriter(),
        private PageShell $shell = new PageShell(),
    ) {}

    /**
     * @throws RuntimeException when a template or asset cannot be read
     */
    public function write(SiteAssembly $assembly): void
    {
        $output = $assembly->outputDirectory();

        $this->writer->write($output, self::NOJEKYLL_FILE, '');
        $this->writeCname($assembly, $output);
        $this->writer->write($output, self::NOT_FOUND_FILE, $this->notFound($assembly));

        foreach ($assembly->assets() as $name => $contents) {
            $this->writer->write($output, "assets/{$name}", $contents);
        }
    }

    /**
     * @throws RuntimeException when the not-found template cannot be read
     */
    private function notFound(SiteAssembly $assembly): string
    {
        return $this->shell->renderTemplate('not-found.php', [
            'siteName'       => $assembly->siteName(),
            'landingUrl'     => $assembly->urls()->basePath(),
            'baseStylesheet' => $assembly->baseStylesheet(),
            'stylesheet'     => $assembly->stylesheet(),
            'projects'       => array_map(
                static fn(ProjectSummary $project): array => [
                    'title' => $project->title(),
                    'url'   => $project->url(),
                ],
                $assembly->projects(),
            ),
            'e'              => $this->shell->escape(...),
        ]);
    }

    private function writeCname(SiteAssembly $assembly, string $output): void
    {
        $hostname = $assembly->hostname();

        if (null === $hostname || '' === $hostname) {
            return;
        }

        $this->writer->write($output, self::CNAME_FILE, "{$hostname}\n");
    }
}
