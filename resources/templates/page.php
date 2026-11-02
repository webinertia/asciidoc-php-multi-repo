<?php

declare(strict_types=1);

/**
 * @var Webware\AsciidocPhp\MultiRepo\Render\PageContext $context
 * @var Closure(string): string $e
 * @var Closure(list<Webware\AsciidocPhp\MultiRepo\Render\TocEntry>): string $toc
 */

$mode    = $context->colorMode();
$mode    = in_array($mode, ['light', 'dark', 'system'], true) ? $mode : 'system';
$initial = $context->defaultColorMode();
$initial = in_array($initial, ['light', 'dark'], true) ? $initial : 'dark';

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $e($initial) ?>" data-theme-mode="<?= $e($mode) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($context->title()) ?></title>
<link rel="canonical" href="<?= $e($context->canonicalUrl()) ?>">
<?php foreach ($context->stylesheets() as $stylesheet) : ?>
<link rel="stylesheet" href="<?= $e($stylesheet) ?>">
<?php endforeach ?>
<script>
(function () {
  var stored = null;
  try {
    stored = window.localStorage.getItem('asciidoc-php-theme');
  } catch (error) {
    stored = null;
  }
  var mode = document.documentElement.getAttribute('data-theme-mode');
  var theme;
  if (stored === 'light' || stored === 'dark') {
    theme = stored;
  } else if (mode === 'light' || mode === 'dark') {
    theme = mode;
  } else {
    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-theme', theme);
})();
</script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
<p class="site-name"><?= $e($context->siteName()) ?></p>
<p class="project-name"><?= $e($context->projectTitle()) ?></p>
<p class="version-label"><?= $e($context->versionLabel()) ?></p>
<button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Toggle dark mode">Toggle dark mode</button>
</header>
<div class="layout">
<nav class="sidebar" aria-label="On this page">
<?= $toc($context->toc()) ?>
</nav>
<main id="main">
<?= $context->body() ?>
</main>
</div>
<footer class="site-footer">
<?php if (null !== $context->sourceUrl()) : ?>
<a class="source-link" href="<?= $e($context->sourceUrl()) ?>" rel="noopener">Edit this page</a>
<?php endif ?>
<p class="footer-project"><?= $e($context->projectTitle()) ?></p>
<p class="footer-version"><?= $e($context->versionLabel()) ?></p>
</footer>
<?php foreach ($context->scripts() as $script) : ?>
<script src="<?= $e($script) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
