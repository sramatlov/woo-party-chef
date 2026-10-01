import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

export const root = dirname(dirname(fileURLToPath(import.meta.url)));
const php = process.env.PHP_BINARY || 'php';
function run(args) {
  const result = spawnSync(php, args, { cwd: root, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
  assert.equal(result.status, 0, result.stderr || result.stdout || String(result.error));
  return result.stdout;
}
function lint(directory) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) lint(path);
    else if (entry.name.endsWith('.php')) run(['-l', path]);
  }
}
lint(join(root, 'woo-party-chef'));
lint(join(root, 'tests'));
run(['tests/cache-without-rocket.php']);
const source = readFileSync(join(root, 'woo-party-chef/assets/woo-party-chef.js'), 'utf8');
new vm.Script(source);
export const fixtures = JSON.parse(run(['tests/php-tests.php']));
const context = vm.createContext({
  URL, WeakSet,
  document: { readyState: 'loading', baseURI: 'https://example.test/', documentElement: {}, addEventListener() {} }
});
vm.runInContext(source.replace('function boot() {', 'globalThis.testApi = { compute, validConfig };\n\tfunction boot() {'), context);
const { compute, validConfig } = context.testApi;
function normalize(state) {
  return Object.fromEntries(Object.entries(state).map(([key, value]) => [key.replace(/[A-Z]/g, c => '_' + c.toLowerCase()), value]));
}
for (const test of fixtures.states) {
  const result = normalize(JSON.parse(JSON.stringify(compute(test.color, test.persons, test.config))));
  assert.deepEqual(result, test.expected, `PHP/JS mismatch for ${test.color.label}, ${test.persons} persons, ${JSON.stringify(test.config)}`);
}
assert.equal(validConfig(fixtures.config), true);
const hidden = JSON.parse(fixtures.html.hidden.match(/data-config="([^"]+)"/)[1].replace(/&quot;/g, '"').replace(/&amp;/g, '&'));
assert.equal(validConfig(hidden), true);
// The person limit is server-defined; another valid limit must not disable the planner.
const otherMax = structuredClone(fixtures.config); otherMax.max = 16;
assert.equal(validConfig(otherMax), true);
let validationChecks = 3;
for (const mutate of [
  c => { c.max = '12'; }, c => { c.max = 0; }, c => { c.colors = []; }, c => { c.colors = {}; },
  c => { c.showPrices = 'yes'; }, c => { delete c.colors.grey.items.ext; },
  c => { c.colors.grey.items['4'].price = NaN; }, c => { c.colors.grey.items['4'].regular = Infinity; },
  c => { c.colors.grey.items['4'].regular = 0; }, c => { c.colors.grey.items['4'].url = 'javascript:alert(1)'; },
  c => { c.colors.grey.items['4'].image = {}; }, c => { c.colors.grey.items['4'].pfas = 'false'; },
  c => { c.colors.constructor = c.colors.grey; }
]) {
  const bad = structuredClone(fixtures.config); mutate(bad);
  assert.equal(validConfig(bad), false); ++validationChecks;
}
console.log(`PASS: PHP/JS syntax, ${fixtures.checks} PHP checks, ${fixtures.states.length} calculation parity states, ${validationChecks} browser-config checks.`);
