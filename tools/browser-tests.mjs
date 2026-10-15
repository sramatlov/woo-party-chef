import assert from 'node:assert/strict';
import { readFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { createRequire } from 'node:module';
import { root, fixtures } from './test.mjs';

const require = createRequire(import.meta.url);
const { chromium } = require(process.env.WOOPC_PLAYWRIGHT_MODULE || 'playwright');
const css = readFileSync(join(root, 'woo-party-chef/assets/woo-party-chef.css'), 'utf8');
const js = readFileSync(join(root, 'woo-party-chef/assets/woo-party-chef.js'), 'utf8');
const browser = await chromium.launch({ headless: true, ...(process.env.WOOPC_BROWSER_CHANNEL ? { channel: process.env.WOOPC_BROWSER_CHANNEL } : {}) });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
let checks = 0;
async function check(ok, message) { assert.ok(ok, message); ++checks; }
async function render(html, width, containerWidth = '100%', height = 1000, interactive = true) {
  await page.goto('about:blank');
  await page.setViewportSize({ width, height });
  await page.setContent(`<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Woo Party Chef regression fixture</title><style>body{margin:0;padding:16px;font-family:Arial,sans-serif}main{width:${containerWidth};max-width:100%}${css}</style></head><body><main>${html}</main></body></html>`);
  if (interactive) await page.addScriptTag({ content: js });
}

try {
  const plannerMetrics = () => page.locator('.woopc__planner').evaluate(el => {
    const find = selector => el.querySelector(selector);
    const style = selector => getComputedStyle(find(selector));
    return {height: el.getBoundingClientRect().height, columns: getComputedStyle(el).gridTemplateColumns,
      padding: style('.woopc__controls').padding, image: find('.woopc__media').getBoundingClientRect().height,
      titleFont: style('.woopc__advice-title').fontSize, bodyFont: style('.woopc__advice-body').fontSize,
      bodyMin: style('.woopc__advice-body').minHeight, button: find('.woopc__step-btn').getBoundingClientRect().height};
  });
  await render(fixtures.html.classic, 1280, '1140px', 640, false);
  const classicLaptop = await plannerMetrics();
  await render(fixtures.html.first, 1280, '1140px', 640, false);
  const autoLaptop = await plannerMetrics();
  await check(autoLaptop.height < classicLaptop.height * 0.8 && autoLaptop.height <= 520, 'A short laptop viewport must shrink the initial planner by at least 20% to at most 520px, before JavaScript.');
  await check(autoLaptop.bodyFont === classicLaptop.bodyFont && autoLaptop.button >= 44, 'Compact laptop text must retain its font size and controls remain at least 44px.');
  console.log(`Planner height at 1280x640: classic ${classicLaptop.height.toFixed(0)}px, auto ${autoLaptop.height.toFixed(0)}px.`);
  if (process.env.WOOPC_SCREENSHOTS) {
    mkdirSync(process.env.WOOPC_SCREENSHOTS, { recursive: true });
    await page.locator('.woopc__planner').screenshot({ path: join(process.env.WOOPC_SCREENSHOTS, 'laptop-auto.png') });
  }
  await page.setViewportSize({ width: 1280, height: 1000 });
  const roomy = await plannerMetrics();
  await render(fixtures.html.classic, 1280, '1140px', 1000, false);
  await check(JSON.stringify(roomy) === JSON.stringify(await plannerMetrics()), 'A large, tall viewport must retain the spacious planner exactly; resizing must work without JavaScript.');
  await render(fixtures.html.classic, 1280, '1140px', 1000, false);
  const classicTall = await plannerMetrics();
  await page.setViewportSize({ width: 1280, height: 640 });
  await check(JSON.stringify(classicTall) === JSON.stringify(await plannerMetrics()), 'Classic rollback must ignore the new viewport-height adaptation.');

  for (const [width, height, container] of [[1024, 768, '100%'], [1440, 900, '100%'], [1920, 1080, '100%'], [1280, 1000, '600px'], [320, 640, '100%'], [375, 667, '100%'], [768, 1024, '100%']]) {
    await render(fixtures.html.first, width, container, height);
    await check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Auto planner must fit without horizontal overflow at ${width}x${height}, container ${container}.`);
    for (const color of ['grey', 'wit']) {
      await page.locator('[data-pick-color="' + color + '"]').first().click();
      for (let persons = 1; persons <= 12; persons++) {
        const root = page.locator('.woopc');
        const current = Number(await root.getAttribute('data-persons'));
        const name = current > persons ? 'Minder personen' : 'Meer personen';
        for (let i = 0; i < Math.abs(current - persons); i++) await root.getByRole('button', { name, exact: true }).click();
        const fits = await root.locator('.woopc__advice').evaluate(el => {
          const outer = el.getBoundingClientRect();
          return [...el.querySelectorAll('[data-ref="title"],[data-ref="text"],[data-ref="alt"],[data-ref="ext-link"],[data-ref="total"],[data-ref="cta"]')].filter(node => !node.hidden).every(node => {
            const rect = node.getBoundingClientRect();
            return rect.left >= outer.left && rect.right <= outer.right + 1 && rect.bottom <= outer.bottom + 1 && node.scrollWidth <= node.clientWidth + 1;
          });
        });
        await check(fits, `Advice, total and purchase links must remain unclipped for ${color}, ${persons} persons at ${width}x${height}.`);
      }
    }
  }

  await render(fixtures.html.first + fixtures.html.second, 1280);
  const first = page.locator('.woopc').nth(0);
  const second = page.locator('.woopc').nth(1);
  await check(await first.locator('[data-ref="save"]').textContent() === 'Je bespaart €20', 'The planner must display whole-euro savings on initial load.');
  await check(await first.getByRole('table').isVisible(), 'Desktop must show the semantic comparison table.');
  await check(await first.getByRole('columnheader').count() === 6, 'Comparison must expose all six header cells.');
  await check(await first.locator('[data-image-id]:not([hidden])').count() === 1, 'Only the active picture wrapper may be visible.');
  await check(await first.locator('[data-ref="status"]').textContent() === '', 'Initial rendering must not produce duplicate live announcements.');
  await first.getByRole('button', { name: 'Meer personen', exact: true }).click();
  await check(await first.getAttribute('data-persons') === '5', 'The first interaction must update the planner.');
  await check(await second.getAttribute('data-persons') === '4', 'One instance must not change another.');
  await check((await first.locator('[data-ref="status"]').textContent()).includes('5 personen'), 'Updated advice must reach the live region.');
  const six = first.getByRole('button', { name: 'Kies de set voor 6 personen', exact: true });
  await six.focus(); await page.keyboard.press('Enter');
  await check(await first.getAttribute('data-persons') === '6', 'Keyboard selection must update the planner.');
  await check(await six.getAttribute('aria-pressed') === 'true', 'Keyboard selection must expose pressed state.');
  await first.locator('[data-pick-color="wit"]').first().click();
  await check(await first.getAttribute('data-color') === 'wit', 'Finish selection must still work.');
  await check(await first.locator('[data-image-id]:not([hidden])').getAttribute('data-image-id') === '3', 'The correct product image must follow the selection.');
  await check(await page.evaluate(() => {
    const ids = [...document.querySelectorAll('[id]')].map(el => el.id);
    return ids.length === new Set(ids).size && [...document.querySelectorAll('[aria-labelledby]')].every(el => el.getAttribute('aria-labelledby').split(' ').every(id => !!document.getElementById(id)));
  }), 'IDs and ARIA references must be unique and resolve.');
  await first.getByRole('button', { name: 'Kies de set voor 8 personen', exact: true }).click();
  for (let i = 0; i < 4; i++) await first.getByRole('button', { name: 'Meer personen', exact: true }).click();
  await check(await first.getByRole('button', { name: 'Meer personen', exact: true }).isDisabled(), 'Person-count upper bound must remain 12.');
  await check(await first.locator('[data-ref="ext-link"]').textContent() === 'Voeg de set voor 4 personen toe →', 'Extension purchase copy must be unchanged.');

  for (const width of [320, 375, 640, 700, 800, 1280]) {
    await render(fixtures.html.first, width);
    const roundingRoot = page.locator('.woopc');
    await check(await roundingRoot.locator('[data-col="ext"] [data-ref="badge"]').first().textContent() === '-€6', 'Extension badges must round the fixture saving of 5.50 euros upwards.');
    await roundingRoot.getByRole('button', { name: 'Minder personen', exact: true }).click();
    await roundingRoot.getByRole('button', { name: 'Minder personen', exact: true }).click();
    await roundingRoot.getByRole('button', { name: 'Minder personen', exact: true }).click();
    await check(await roundingRoot.locator('[data-ref="save"]').textContent() === 'Je bespaart €6', 'After interaction the planner must match the rounded extension badge.');
    const cards = page.locator('.woopc__cards');
    const table = page.locator('.woopc__table-wrap');
    const innerWidth = width - 32;
    await check(innerWidth < 760 ? await cards.isVisible() && !(await table.isVisible()) : await table.isVisible() && !(await cards.isVisible()), `Responsive layout must match available width at ${width}px.`);
    await check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `The page must not overflow horizontally at ${width}px.`);
  }
  await render(fixtures.html.first, 1280, '600px');
  await check(await page.locator('.woopc__cards').isVisible(), 'A narrow desktop Elementor container must show cards.');
  await check(!(await page.locator('.woopc__table-wrap').isVisible()), 'A narrow desktop container must hide the table.');

  // Reproduce the staging Elementor kit overriding link typography on hover.
  for (const width of [1280, 375]) {
    await render(fixtures.html.first, width);
    await page.locator('body').evaluate(el => el.classList.add('elementor-kit-13'));
    await page.addStyleTag({ content: '.elementor-kit-13 a:hover,.elementor-kit-13 a:focus{font-family:RobotoFlex,sans-serif;font-weight:300}' });
    await page.getByRole('button', { name: 'Kies de set voor 8 personen', exact: true }).click();
    await page.getByRole('button', { name: 'Meer personen', exact: true }).click();
    for (const selector of ['.woopc__cta', '.woopc__ext-link', width > 759 ? '.woopc__col-cta' : '.woopc__card-link']) {
      const link = page.locator(selector).first();
      await page.mouse.move(0, 0);
      await link.evaluate(el => el.blur());
      const typography = el => {
        const style = getComputedStyle(el);
        return { family: style.fontFamily, weight: style.fontWeight, size: style.fontSize, lineHeight: style.lineHeight };
      };
      const normal = await link.evaluate(typography);
      await link.hover();
      await check(JSON.stringify(await link.evaluate(typography)) === JSON.stringify(normal), `${selector} must retain its typography on hover at ${width}px.`);
      await page.mouse.move(0, 0);
      await link.focus();
      await check(JSON.stringify(await link.evaluate(typography)) === JSON.stringify(normal), `${selector} must retain its typography on focus at ${width}px.`);
      await link.evaluate(el => el.blur());
    }
  }

  // Elementor's centred column containers leave widgets at their intrinsic width.
  // Inline-size containment must not collapse that width, including before JS runs.
  for (const [viewport, containerWidth] of [[1280, 1140], [1280, 600], [375, 343]]) {
    await page.goto('about:blank');
    await page.setViewportSize({ width: viewport, height: 1000 });
    await page.setContent(`<!doctype html><html lang="nl"><head><meta charset="utf-8"><style>body{margin:0;padding:16px;font-family:Arial,sans-serif}.e-con-inner{display:flex;flex-direction:column;align-items:center;width:${containerWidth}px;max-width:100%}.elementor-widget-shortcode{max-width:100%}${css}</style></head><body><div class="e-con-inner"><div class="elementor-widget-shortcode"><div class="elementor-widget-container"><div class="elementor-shortcode">${fixtures.html.first}</div></div></div></div></body></html>`);
    const bounds = await page.locator('.woopc').boundingBox();
    await check(Math.abs(bounds.width - containerWidth) < 1, `A centred Elementor widget must fill ${containerWidth}px before JavaScript.`);
    await check(containerWidth < 760 ? await page.locator('.woopc__cards').isVisible() : await page.locator('.woopc__table-wrap').isVisible(), `Centred Elementor layout must match ${containerWidth}px of available width.`);
    await page.addScriptTag({ content: js });
    await page.getByRole('button', { name: 'Meer personen', exact: true }).click();
    await check(await page.locator('.woopc').getAttribute('data-persons') === '5', `Centred Elementor interaction must work at ${containerWidth}px.`);
    await check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `Centred Elementor must not overflow at ${viewport}px.`);
  }

  // Deluxe opt-in: first and selected; finish switches swap power, copy and feature box.
  for (const width of [1280, 375]) {
    await render(fixtures.html.deluxe, width);
    const dx = page.locator('.woopc');
    const visibleFeatures = () => dx.locator('.woopc__features:not([hidden]) .woopc__features-title').textContent();
    await check(await dx.getAttribute('data-color') === 'deluxe' && await visibleFeatures() === 'In elke Chef’s Dinner Party Deluxe', `Deluxe must be selected with its own feature box at ${width}px.`);
    await check((await dx.locator('[data-ref="text"]').textContent()).startsWith('4 kookstations van 480 W'), `Deluxe advice must use 480 W at ${width}px.`);
    const pointsText = () => dx.locator('[data-ref="points"] li').allTextContents();
    await check(JSON.stringify(await pointsText()) === JSON.stringify(['Punt deluxe 4 & meer', 'Uitbreidbaar met stations']) && await dx.locator('[data-ref="points"]').isVisible(), `The Deluxe photo must show its product bullets at ${width}px.`);
    await dx.getByRole('button', { name: 'Kies de set voor 6 personen', exact: true }).click();
    await check(!(await dx.locator('[data-ref="points"]').isVisible()) && await dx.locator('.woopc__media').getAttribute('data-points') === 'false', `A product without a list must hide the bullets at ${width}px.`);
    await dx.getByRole('button', { name: 'Kies de set voor 4 personen', exact: true }).click();
    await dx.locator('[data-pick-color="wit"]').first().click();
    await check(JSON.stringify(await pointsText()) === JSON.stringify(['Punt wit 4 & meer', 'Uitbreidbaar met stations']), `Switching to Wit must show the Wit product bullets at ${width}px.`);
    await check(await visibleFeatures() === 'In elke Chef’s Dinner Party' && await dx.locator('.woopc__features:not([hidden])').count() === 1, `Switching to Wit must show the standard feature box at ${width}px.`);
    await check(await dx.locator('[data-col="8"] [data-ref="watt"]').textContent() === '2.000 W' && await dx.locator('[data-col="ext"] [data-ref="meta"]').textContent() === '1 station · 250 W', `Comparison power must follow Wit at ${width}px.`);
    await dx.locator('[data-pick-color="deluxe"]').first().click();
    await check(await dx.locator('[data-col="8"] [data-ref="watt"]').textContent() === '3.840 W' && await visibleFeatures() === 'In elke Chef’s Dinner Party Deluxe', `Switching back must restore Deluxe at ${width}px.`);
    await check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `Deluxe must not overflow at ${width}px.`);
  }

  // The photo gives about a quarter of its size to the list on desktop, not on phones.
  for (const [width, height, ratio] of [[1440, 1000, 0.75], [1280, 640, 0.75], [1024, 768, 0.75], [375, 667, 1]]) {
    const photoWidth = async html => { await render(html, width, '100%', height); return page.locator('.woopc__image:not([hidden]) img').evaluate(el => el.getBoundingClientRect().width); };
    const plain = await photoWidth(fixtures.html.first);
    const withPoints = await photoWidth(fixtures.html.deluxe);
    await check(Math.abs(withPoints / plain - ratio) < 0.03, `The photo must be ${ratio * 100}% of its size with the list at ${width}x${height} (got ${withPoints.toFixed(0)} / ${plain.toFixed(0)}px).`);
    const listFits = await page.locator('.woopc__media').evaluate(el => {
      const outer = el.getBoundingClientRect();
      return [...el.querySelectorAll('.woopc__point')].every(li => { const r = li.getBoundingClientRect(); return r.left >= outer.left && r.right <= outer.right + 1 && r.bottom <= outer.bottom + 1; });
    });
    await check(listFits, `The bullet list must fit inside the photo column at ${width}x${height}.`);
  }

  await render(fixtures.html.hidden, 375);
  await page.getByRole('button', { name: 'Meer personen', exact: true }).click();
  await check(await page.locator('.woopc').getAttribute('data-persons') === '5', 'The planner must work without price fields.');
  await check(!(await page.locator('.woopc').textContent()).includes('€'), 'Hidden-price interaction must not expose an amount.');
  await check(!(await page.locator('[data-ref="status"]').textContent()).includes('Totaal'), 'Hidden-price announcements must omit totals.');

  await render(fixtures.html.noPlanner, 1280);
  await page.getByRole('button', { name: 'Kies de set voor 5 personen', exact: true }).click();
  await check(await page.locator('.woopc').getAttribute('data-persons') === '5', 'Comparison-only rendering must still respond.');

  const invalid = fixtures.html.second.replace(/data-config="[^"]+"/, 'data-config="{broken"');
  await render(invalid + fixtures.html.second, 1280);
  await page.locator('.woopc').nth(1).getByRole('button', { name: 'Meer personen', exact: true }).click();
  await check(await page.locator('.woopc').nth(1).getAttribute('data-persons') === '5', 'Malformed configuration must not break the valid neighbour.');
  // Simulate the Elementor editor: init event, then element_ready for a re-rendered widget.
  await page.evaluate(html => {
    window.elementorFrontend = { isEditMode: () => true, hooks: { addAction(name, callback) { if (name === 'frontend/element_ready/global') window.__woopcReady = callback; } } };
    window.dispatchEvent(new Event('elementor/frontend/init'));
    const widget = document.createElement('div');
    widget.className = 'elementor-widget-shortcode';
    widget.innerHTML = html;
    document.querySelector('main').append(widget);
    window.__woopcReady([widget]);
  }, fixtures.html.second);
  const inserted = page.locator('.woopc').nth(2);
  await inserted.getByRole('button', { name: 'Meer personen', exact: true }).click();
  await check(await inserted.getAttribute('data-persons') === '5', 'Elementor editor re-renders must initialize through element_ready.');
  await page.evaluate(() => {
    const widget = document.querySelector('.elementor-widget-shortcode');
    widget.remove(); document.querySelector('main').append(widget);
    window.__woopcReady([widget]);
  });
  await inserted.getByRole('button', { name: 'Meer personen', exact: true }).click();
  await check(await inserted.getAttribute('data-persons') === '6', 'Moving an initialized instance must not duplicate click handlers.');
  await check(errors.length === 0, `Unexpected browser errors: ${errors.join('; ')}`);

  if (process.env.WOOPC_SCREENSHOTS) {
    mkdirSync(process.env.WOOPC_SCREENSHOTS, { recursive: true });
    await render(fixtures.html.first, 1280);
    await page.screenshot({ path: join(process.env.WOOPC_SCREENSHOTS, 'desktop.png'), fullPage: true });
    await render(fixtures.html.first, 375);
    await page.screenshot({ path: join(process.env.WOOPC_SCREENSHOTS, 'mobile.png'), fullPage: true });
    await render(fixtures.html.first, 700);
    await page.screenshot({ path: join(process.env.WOOPC_SCREENSHOTS, 'tablet.png'), fullPage: true });
  }
  console.log(`PASS: ${checks} Chromium layout, accessibility-markup and interaction checks.`);
} finally {
  await browser.close();
}
