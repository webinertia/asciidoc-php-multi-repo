<?php

declare(strict_types=1);

/**
 * The shared theme contract: the resolved colour mode, the mode painted before scripting runs, and
 * the inline bootstrap that applies the stored or preferred theme before first paint.
 *
 * `page.php` and `landing.php` both include this file, so the document element attributes and the
 * bootstrap cannot drift apart. An including template sets `$colorMode` and `$defaultColorMode`,
 * reads `$themeMode` and `$themeInitial` for the `data-theme-mode` and `data-theme` attributes, and
 * calls `$themeBootstrap()` inside the head.
 *
 * @var string $colorMode        the configured mode: light, dark, or system
 * @var string $defaultColorMode the mode painted before scripting runs
 */

$themeMode    = in_array($colorMode ?? '', ['light', 'dark', 'system'], true) ? $colorMode : 'system';
$themeInitial = in_array($defaultColorMode ?? '', ['light', 'dark'], true) ? $defaultColorMode : 'dark';

/**
 * The stored choice wins, then the configured mode, then the operating system preference. It runs
 * inline in the head so the first paint already carries the resolved theme.
 */
$themeBootstrap = static function (): void {
    ?>
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
<?php
};
