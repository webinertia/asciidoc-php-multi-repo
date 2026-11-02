<?php

declare(strict_types=1);

/**
 * @var list<Webware\AsciidocPhp\MultiRepo\Render\TocEntry> $entries
 * @var Closure(string): string $e
 */

?>
<ol class="toc-list">
<?php foreach ($entries as $entry) : ?>
<li class="toc-level-<?= $e((string) $entry->level()) ?>"><a href="#<?= $e($entry->id()) ?>"><?= $e($entry->title()) ?></a></li>
<?php endforeach ?>
</ol>
