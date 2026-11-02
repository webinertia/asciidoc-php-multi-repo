/*
 * Light and dark theme toggle.
 *
 * The stored choice wins, then the site's configured default, then the operating system preference.
 * The page template applies the same resolution inline in the head, before first paint, so this file
 * only has to keep the toggle button in step and persist the user's choice.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'asciidoc-php-theme';
    var root = document.documentElement;
    var toggle = document.querySelector('[data-theme-toggle]');

    if (!toggle) {
        return;
    }

    function readStored() {
        try {
            return window.localStorage.getItem(STORAGE_KEY);
        } catch (error) {
            return null;
        }
    }

    function store(theme) {
        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch (error) {
            return;
        }
    }

    function current() {
        return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }

    function sync() {
        var dark = current() === 'dark';
        var label = dark ? 'Switch to light theme' : 'Switch to dark theme';

        toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        toggle.setAttribute('aria-label', label);
        toggle.textContent = label;
    }

    function apply(theme) {
        root.setAttribute('data-theme', theme);
        sync();
    }

    toggle.addEventListener('click', function () {
        var next = current() === 'dark' ? 'light' : 'dark';

        apply(next);
        store(next);
    });

    var media = window.matchMedia('(prefers-color-scheme: dark)');

    function followSystem() {
        if (null !== readStored()) {
            return;
        }

        apply(media.matches ? 'dark' : 'light');
    }

    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', followSystem);
    } else if (typeof media.addListener === 'function') {
        media.addListener(followSystem);
    }

    sync();
})();
