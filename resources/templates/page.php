<?php

declare(strict_types=1);

/**
 * @var Webware\AsciidocPhp\MultiRepo\Render\PageContext $context
 * @var Closure(string): string $e
 * @var Closure(list<Webware\AsciidocPhp\MultiRepo\Render\TocEntry>): string $toc
 */

$colorMode        = $context->colorMode();
$defaultColorMode = $context->defaultColorMode();
$chrome           = $context->chrome();

require __DIR__ . '/theme-bootstrap.php';

?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $e($themeInitial) ?>" data-theme-mode="<?= $e($themeMode) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($context->title()) ?></title>
<link rel="canonical" href="<?= $e($context->canonicalUrl()) ?>">
<?php if ('' !== $context->logoUrl()) : ?>
<link rel="icon" href="<?= $e($context->logoUrl()) ?>">
<?php endif ?>
<?php foreach ($context->stylesheets() as $stylesheet) : ?>
<link rel="stylesheet" href="<?= $e($stylesheet) ?>">
<?php endforeach ?>
<?php if ('' !== $context->iconFontCdn()) : ?>
<link rel="stylesheet" href="<?= $e($context->iconFontCdn()) ?>">
<?php endif ?>
<?php $themeBootstrap() ?></head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
<?php if ('' !== $context->logoUrl()) : ?>
<a class="site-brand" href="<?= $e($chrome->landingUrl()) ?>"><img class="site-logo" src="<?= $e($context->logoUrl()) ?>" alt="<?= $e($context->siteName()) ?>" width="34" height="34" decoding="async"></a>
<?php else : ?>
<p class="site-name"><a href="<?= $e($chrome->landingUrl()) ?>"><?= $e($context->siteName()) ?></a></p>
<?php endif ?>
<p class="project-name"><?= $e($context->projectTitle()) ?></p>
<p class="version-label"><?= $e($context->versionLabel()) ?></p>
<?php if ($chrome->hasProjectSwitcher()) : ?>
<?= $chrome->projectSwitcher() ?>
<?php endif ?>
<?php if ($chrome->hasVersionSelector()) : ?>
<?= $chrome->versionSelector() ?>
<?php endif ?>
<button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false" aria-label="Toggle dark mode">Toggle dark mode</button>
<?php if ($chrome->hasSearch()) : ?>
<div class="site-search" data-search data-site-index="<?= $e($chrome->siteIndexUrl()) ?>" data-project-index="<?= $e($chrome->projectIndexUrl()) ?>" data-project-slug="<?= $e($chrome->projectSlug()) ?>" data-version-label="<?= $e($context->versionLabel()) ?>" hidden>
<label class="search-label" for="site-search-input">Search</label>
<input class="search-input" id="site-search-input" type="search" autocomplete="off" spellcheck="false" data-search-input>
<label class="search-scope-label" for="site-search-scope">Scope</label>
<select class="search-scope" id="site-search-scope" data-search-scope>
<option value="version">This version</option>
<option value="project">All versions</option>
<option value="site">All projects</option>
</select>
<ul class="search-results" data-search-results hidden></ul>
<p class="search-status" data-search-status aria-live="polite"></p>
</div>
<?php endif ?>
</header>
<?php if ($chrome->hasOutdatedBanner()) : ?>
<?= $chrome->outdatedBanner() ?>
<?php endif ?>
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
