/*
 * Field-weighted search over a documentation index.
 *
 * Kept free of DOM access so the same scoring runs in the browser and under the Node harness in
 * test/js/search-core.test.mjs. Ranking is title 3, heading 2, body 1, with ties broken by page URL
 * so the order is stable across engines.
 */
(function (root, factory) {
    'use strict';

    var api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.AsciidocSearchCore = api;
    }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    var WEIGHTS = { title: 3, heading: 2, body: 1 };

    function normalise(value) {
        return String(value === undefined || value === null ? '' : value)
            .toLowerCase()
            .replace(/\u00a0/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function tokenise(query) {
        var normalised = normalise(query);

        if (normalised === '') {
            return [];
        }

        var parts = normalised.split(' ');
        var terms = [];
        var index;

        for (index = 0; index < parts.length; index += 1) {
            if (parts[index] !== '' && terms.indexOf(parts[index]) === -1) {
                terms.push(parts[index]);
            }
        }

        return terms;
    }

    function occurrences(haystack, term) {
        if (haystack === '' || term === '') {
            return 0;
        }

        var count = 0;
        var index = haystack.indexOf(term);

        while (index !== -1) {
            count += 1;
            index = haystack.indexOf(term, index + term.length);
        }

        return count;
    }

    function headingsOf(page) {
        return Array.isArray(page.headings) ? page.headings : [];
    }

    function scorePage(page, terms) {
        var title = normalise(page.title);
        var text = normalise(page.text);
        var headings = headingsOf(page);
        var score = 0;
        var term;
        var heading;

        for (term = 0; term < terms.length; term += 1) {
            score += WEIGHTS.title * occurrences(title, terms[term]);
            score += WEIGHTS.body * occurrences(text, terms[term]);

            for (heading = 0; heading < headings.length; heading += 1) {
                score += WEIGHTS.heading * occurrences(normalise(headings[heading].title), terms[term]);
            }
        }

        return score;
    }

    function matchingSection(page, terms) {
        var headings = headingsOf(page);
        var heading;
        var term;
        var title;

        for (heading = 0; heading < headings.length; heading += 1) {
            title = normalise(headings[heading].title);

            for (term = 0; term < terms.length; term += 1) {
                if (title.indexOf(terms[term]) !== -1) {
                    return headings[heading];
                }
            }
        }

        return null;
    }

    function compare(left, right) {
        if (right.score !== left.score) {
            return right.score - left.score;
        }

        if (left.url < right.url) {
            return -1;
        }

        if (left.url > right.url) {
            return 1;
        }

        return 0;
    }

    function projectTitle(index, slug) {
        if (index && index.project && typeof index.project.title === 'string' && index.project.title !== '') {
            return index.project.title;
        }

        return slug;
    }

    /*
     * scope: { slugs: [slug, ...], version: label or null, limit: number }
     *
     * A null version searches every version of every slug in scope. Results carry the project,
     * version and matching section so the caller can render one line per hit.
     */
    function search(indexes, query, scope) {
        var terms = tokenise(query);

        if (terms.length === 0) {
            return [];
        }

        var options = scope || {};
        var slugs = Array.isArray(options.slugs) ? options.slugs : [];
        var version = typeof options.version === 'string' && options.version !== '' ? options.version : null;
        var limit = typeof options.limit === 'number' && options.limit > 0 ? options.limit : 20;
        var matches = [];
        var slugIndex;
        var index;
        var versions;
        var versionIndex;
        var pages;
        var pageIndex;
        var page;
        var score;

        for (slugIndex = 0; slugIndex < slugs.length; slugIndex += 1) {
            index = indexes[slugs[slugIndex]];

            if (!index || !Array.isArray(index.versions)) {
                continue;
            }

            versions = index.versions;

            for (versionIndex = 0; versionIndex < versions.length; versionIndex += 1) {
                if (version !== null && versions[versionIndex].label !== version) {
                    continue;
                }

                pages = Array.isArray(versions[versionIndex].pages) ? versions[versionIndex].pages : [];

                for (pageIndex = 0; pageIndex < pages.length; pageIndex += 1) {
                    page = pages[pageIndex];
                    score = scorePage(page, terms);

                    if (score <= 0) {
                        continue;
                    }

                    matches.push({
                        score: score,
                        url: String(page.url),
                        title: String(page.title),
                        slug: slugs[slugIndex],
                        project: projectTitle(index, slugs[slugIndex]),
                        version: String(versions[versionIndex].label),
                        latest: versions[versionIndex].latest === true,
                        section: matchingSection(page, terms)
                    });
                }
            }
        }

        matches.sort(compare);

        return matches.slice(0, limit);
    }

    return {
        WEIGHTS: WEIGHTS,
        normalise: normalise,
        tokenise: tokenise,
        scorePage: scorePage,
        matchingSection: matchingSection,
        search: search
    };
}));
