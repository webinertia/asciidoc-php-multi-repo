/*
 * Search behaviour harness.
 *
 * The browser script and this harness share one scoring implementation, resources/js/search-core.js,
 * so a ranking change that would break the site breaks this file first. Run it directly with
 * `node test/js/search-core.test.mjs`; PHPUnit runs it through
 * test/integration/SearchBehaviourTest.php, which is what keeps it inside the normal test run.
 *
 * The fixture index is taken from the first argument, or from SEARCH_CORE_FIXTURE when that
 * variable is set, so the test can point the harness at an edited copy and prove the assertions are
 * sensitive to ranking.
 */
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const core = require('../../resources/js/search-core.js');

const here = dirname(fileURLToPath(import.meta.url));
const fixture = process.argv[2]
    || process.env.SEARCH_CORE_FIXTURE
    || join(here, '..', 'fixtures', 'search', 'index.json');

const index = JSON.parse(readFileSync(fixture, 'utf8'));
const indexes = { 'message-bus': index };

let failures = 0;
let checks = 0;

function check(name, condition) {
    checks += 1;

    if (!condition) {
        failures += 1;
        console.error(`not ok ${checks} - ${name}`);
    }
}

function urls(results) {
    return results.map((result) => result.url);
}

const weights = core.WEIGHTS;
check('title weight is 3', weights.title === 3);
check('heading weight is 2', weights.heading === 2);
check('body weight is 1', weights.body === 1);

check('an empty query matches nothing', core.search(indexes, '   ', { slugs: ['message-bus'] }).length === 0);

const scoped = core.search(indexes, 'transport', { slugs: ['message-bus'], version: 'v2' });
check('the current version is searched on its own', urls(scoped).length === 1);
check('the current version result carries its version', scoped[0].version === 'v2');
check('the current version result is marked latest', scoped[0].latest === true);
check('the current version result carries the project title', scoped[0].project === 'Message Bus');

const allVersions = core.search(indexes, 'transport', { slugs: ['message-bus'] });
check('widening to all versions finds the older page', urls(allVersions).length === 2);
check(
    'the same page in two versions ties and breaks by url',
    urls(allVersions).join(',') === '/message-bus/v1/transport.html,/message-bus/v2/transport.html',
);

const v2Only = core.search(indexes, 'routes', { slugs: ['message-bus'], version: 'v2' });
const v1Only = core.search(indexes, 'routes', { slugs: ['message-bus'], version: 'v1' });
check('a term unique to one version is found there', v2Only.length === 1);
check('a term unique to one version is absent from the other', v1Only.length === 0);

const heading = core.search(indexes, 'retry', { slugs: ['message-bus'], version: 'v2' });
check('a heading match is returned', heading.length === 1);
check('a heading match names the matching section', heading[0]?.section?.title === 'Retry policy');

const bodyOnly = core.scorePage(
    { title: '', text: 'a retry', headings: [] },
    ['retry'],
);
const headingOnly = core.scorePage(
    { title: '', text: '', headings: [{ id: 'r', title: 'retry', level: 2 }] },
    ['retry'],
);
const titleOnly = core.scorePage({ title: 'retry', text: '', headings: [] }, ['retry']);

check('one body occurrence scores 1', bodyOnly === 1);
check('one heading occurrence scores 2', headingOnly === 2);
check('one title occurrence scores 3', titleOnly === 3);
check('a heading match outranks a body match', headingOnly > bodyOnly);

const ranked = core.search(indexes, 'message', { slugs: ['message-bus'], version: 'v2' });
check(
    'a title match outranks a body match',
    urls(ranked)[0] === '/message-bus/v2/index.html',
);

check('results are limited to the requested count', core.search(indexes, 'message', {
    slugs: ['message-bus'],
    limit: 1,
}).length === 1);

check('an unknown project is ignored', core.search(indexes, 'message', { slugs: ['missing'] }).length === 0);
check('normalise collapses whitespace and case', core.normalise('  Message   BUS ') === 'message bus');
check('tokenise removes duplicates', core.tokenise('Bus bus BUS').length === 1);

console.log(`${checks} checks, ${failures} failures`);

if (failures > 0) {
    process.exit(1);
}
