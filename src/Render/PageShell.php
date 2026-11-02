<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Render;

use RuntimeException;

use function dirname;
use function extract;
use function htmlspecialchars;
use function is_file;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function sprintf;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const EXTR_SKIP;

/**
 * Renders the HTML document shell around a converted article fragment.
 *
 * The templates live in `resources/templates/` and are plain PHP files that echo. They receive only
 * escaped values: the shell hands them an escape closure and a table-of-contents closure, and the
 * article body, which the converter already produced, is the single value written unescaped.
 *
 * @api
 */
final class PageShell
{
    private string $templateDirectory;

    public function __construct(?string $templateDirectory = null)
    {
        $this->templateDirectory = $templateDirectory ?? dirname(__DIR__, 2) . '/resources/templates';
    }

    /**
     * Escapes a value for HTML text and attribute context.
     */
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @throws RuntimeException when a template is missing
     */
    public function render(PageContext $context): string
    {
        return $this->renderTemplate('page.php', [
            'context' => $context,
            'e'       => $this->escape(...),
            /** @param list<TocEntry> $entries */
            'toc' => $this->renderToc(...),
        ]);
    }

    /**
     * Renders a template from the template directory with the given variables in scope.
     *
     * @param array<string, mixed> $variables
     *
     * @throws RuntimeException when the template is missing
     */
    public function renderTemplate(string $template, array $variables): string
    {
        $path = "{$this->templateDirectory}/{$template}";

        if (! is_file($path)) {
            throw new RuntimeException(sprintf(
                'Template "%s" is missing from "%s".',
                $template,
                $this->templateDirectory,
            ));
        }

        $level = ob_get_level();
        ob_start();

        try {
            (static function () use ($path, $variables): void {
                extract($variables, EXTR_SKIP);
                require $path;
            })();

            return (string) ob_get_clean();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * @param list<TocEntry> $entries
     *
     * @throws RuntimeException when a template is missing
     */
    public function renderToc(array $entries): string
    {
        return $this->renderTemplate('toc.php', [
            'entries' => $entries,
            'e'       => $this->escape(...),
        ]);
    }

    public function templateDirectory(): string
    {
        return $this->templateDirectory;
    }
}
