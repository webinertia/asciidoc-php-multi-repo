<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $e
 * @var string $siteName
 * @var string $canonicalUrl
 * @var string $stylesheet
 * @var list<array{description: string, title: string, url: string, version: string}> $cards
 */

?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($siteName) ?></title>
<link rel="canonical" href="<?= $e($canonicalUrl) ?>">
<link rel="stylesheet" href="<?= $e($stylesheet) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
<p class="site-name"><?= $e($siteName) ?></p>
<h1 class="landing-title">Documentation</h1>
</header>
<main id="main" class="landing">
<?php if ([] === $cards) : ?>
<p class="landing-empty">No documentation is published yet.</p>
<?php else : ?>
<ul class="project-cards">
<?php foreach ($cards as $card) : ?>
<li class="project-card">
<h2 class="card-title"><a href="<?= $e($card['url']) ?>"><?= $e($card['title']) ?></a></h2>
<p class="card-version">Latest version: <?= $e($card['version']) ?></p>
<?php if ('' !== $card['description']) : ?>
<p class="card-description"><?= $e($card['description']) ?></p>
<?php endif ?>
</li>
<?php endforeach ?>
</ul>
<?php endif ?>
</main>
</body>
</html>
