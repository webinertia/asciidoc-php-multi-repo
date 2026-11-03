/*
 * Client search: loads the site index plus the indexes it needs, renders ranked hits, and keeps the
 * query in the address bar so a results URL can be shared and the back button restores the previous
 * query. Scoring lives in search-core.js.
 */
(function () {
    'use strict';

    var core = window.AsciidocSearchCore;

    if (!core) {
        return;
    }

    var container = document.querySelector('[data-search]');

    if (!container) {
        return;
    }

    var input = container.querySelector('[data-search-input]');
    var scopeSelect = container.querySelector('[data-search-scope]');
    var results = container.querySelector('[data-search-results]');
    var status = container.querySelector('[data-search-status]');

    if (!input || !results || !status) {
        return;
    }

    var siteIndexUrl = container.getAttribute('data-site-index') || '';
    var projectIndexUrl = container.getAttribute('data-project-index') || '';
    var projectSlug = container.getAttribute('data-project-slug') || '';
    var versionLabel = container.getAttribute('data-version-label') || '';

    var cache = {};
    var timer = null;
    var lastQuery = null;

    container.removeAttribute('hidden');

    function fetchJson(url) {
        if (!cache[url]) {
            cache[url] = window.fetch(url, { credentials: 'same-origin' }).then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' for ' + url);
                }

                return response.json();
            });
        }

        return cache[url];
    }

    function chosenScope() {
        return scopeSelect && scopeSelect.value ? scopeSelect.value : 'version';
    }

    function loadProject() {
        return fetchJson(projectIndexUrl).then(function (index) {
            var indexes = {};

            indexes[projectSlug] = index;

            return { slugs: [projectSlug], indexes: indexes };
        });
    }

    function loadSite() {
        if (!siteIndexUrl) {
            return Promise.resolve({ slugs: [], indexes: {} });
        }

        return fetchJson(siteIndexUrl).then(function (site) {
            var projects = Array.isArray(site.projects) ? site.projects : [];
            var slugs = [];
            var requests = [];
            var position;

            for (position = 0; position < projects.length; position += 1) {
                slugs.push(projects[position].slug);
                requests.push(fetchJson(projects[position].index));
            }

            return Promise.all(requests).then(function (documents) {
                var indexes = {};

                for (position = 0; position < documents.length; position += 1) {
                    indexes[slugs[position]] = documents[position];
                }

                return { slugs: slugs, indexes: indexes };
            });
        });
    }

    function resultNode(match) {
        var item = document.createElement('li');
        var link = document.createElement('a');
        var title = document.createElement('span');
        var version = document.createElement('span');

        item.className = 'search-result';

        link.className = 'search-result-link';
        link.href = match.url;
        title.className = 'search-result-title';
        title.textContent = match.title;
        link.appendChild(title);
        item.appendChild(link);

        if (chosenScope() === 'site') {
            var project = document.createElement('span');

            project.className = 'search-result-project';
            project.textContent = match.project;
            item.appendChild(project);
        }

        version.className = 'search-result-version';
        version.textContent = match.version;
        item.appendChild(version);

        if (match.section && match.section.title) {
            var section = document.createElement('a');

            section.className = 'search-result-section';
            section.href = match.url + '#' + match.section.id;
            section.textContent = match.section.title;
            item.appendChild(section);
        }

        return item;
    }

    function render(matches, query) {
        results.textContent = '';

        if (matches.length === 0) {
            results.setAttribute('hidden', 'hidden');
            status.textContent = 'No results for "' + query + '".';

            return;
        }

        var position;

        for (position = 0; position < matches.length; position += 1) {
            results.appendChild(resultNode(matches[position]));
        }

        results.removeAttribute('hidden');
        status.textContent = matches.length === 1 ? '1 result' : matches.length + ' results';
    }

    function run(push) {
        var query = input.value;
        var scope = chosenScope();

        if (push) {
            remember(query, scope);
        }

        if (core.tokenise(query).length === 0) {
            results.textContent = '';
            results.setAttribute('hidden', 'hidden');
            status.textContent = '';

            return;
        }

        status.textContent = 'Searching...';

        var loading = scope === 'site' ? loadSite() : loadProject();

        loading.then(function (loaded) {
            if (input.value !== query || chosenScope() !== scope) {
                return;
            }

            render(
                core.search(loaded.indexes, query, {
                    slugs: loaded.slugs,
                    version: scope === 'version' ? versionLabel : null,
                    limit: 20
                }),
                query
            );
        })['catch'](function (error) {
            results.setAttribute('hidden', 'hidden');
            status.textContent = 'Search is unavailable: ' + error.message;
        });
    }

    function remember(query, scope) {
        var url = new URL(window.location.href);
        var key = query + '\u0000' + scope;

        if (query === '') {
            url.searchParams['delete']('q');
        } else {
            url.searchParams.set('q', query);
        }

        if (scope === 'version') {
            url.searchParams['delete']('scope');
        } else {
            url.searchParams.set('scope', scope);
        }

        if (key === lastQuery) {
            return;
        }

        lastQuery = key;
        window.history.pushState({ q: query, scope: scope }, '', url.toString());
    }

    function restore() {
        var params = new URL(window.location.href).searchParams;
        var query = params.get('q') || '';
        var scope = params.get('scope') || 'version';

        if (scopeSelect) {
            scopeSelect.value = scope;
        }

        input.value = query;
        lastQuery = query + '\u0000' + scope;

        if (query !== '') {
            run(false);
        }
    }

    input.addEventListener('input', function () {
        if (timer !== null) {
            window.clearTimeout(timer);
        }

        timer = window.setTimeout(function () {
            timer = null;
            run(true);
        }, 200);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            input.value = '';
            run(true);
        }
    });

    if (scopeSelect) {
        scopeSelect.addEventListener('change', function () {
            run(true);
        });
    }

    window.addEventListener('popstate', function () {
        restore();
    });

    restore();
}());
