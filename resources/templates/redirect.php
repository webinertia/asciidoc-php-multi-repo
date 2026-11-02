<?php

declare(strict_types=1);

/**
 * @var Closure(string): string $e
 * @var string $label
 * @var string $targetUrl
 * @var string $canonicalUrl
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e("Redirecting to {$label}") ?></title>
<link rel="canonical" href="<?= $e($canonicalUrl) ?>">
<meta name="robots" content="noindex">
<meta http-equiv="refresh" content="0; url=<?= $e($targetUrl) ?>">
</head>
<body>
<p>Redirecting to <a href="<?= $e($targetUrl) ?>"><?= $e($targetUrl) ?></a>.</p>
</body>
</html>
