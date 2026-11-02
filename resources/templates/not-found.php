<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $e
 * @var string $siteName
 * @var string $landingUrl
 * @var string $stylesheet
 * @var list<array{title: string, url: string}> $projects
 */

?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e("Page not found | {$siteName}") ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= $e($stylesheet) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
<p class="site-name"><a href="<?= $e($landingUrl) ?>"><?= $e($siteName) ?></a></p>
</header>
<main id="main" class="not-found">
<h1>Page not found</h1>
<p>The page you asked for is not part of this documentation set. Start from the <a href="<?= $e($landingUrl) ?>">documentation home page</a>, or pick a project below.</p>
<ul class="project-list">
<?php foreach ($projects as $project) : ?>
<li><a href="<?= $e($project['url']) ?>"><?= $e($project['title']) ?></a></li>
<?php endforeach ?>
</ul>
</main>
</body>
</html>
