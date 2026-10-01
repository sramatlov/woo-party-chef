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
async function render(html, width, containerWidth = '100%') {
  await page.goto('about:blank');
  await page.setViewportSize({ width, height: 1000 });
  await page.setContent(`<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Woo Party Chef regression fixture</title><style>body{margin:0;padding:16px;font-family:Arial,sans-serif}main{width:${containerWidth};max-width:100%}${css}</style></head><body><main>${html}</main></body></html>`);
  await page.addScriptTag({ content: js });
}

try {
  await render(fixtures.html.first + fixtures.html.second, 1280);
  const first = page.locator('.woopc').nth(0);
  const second = page.locator('.woopc').nth(1);
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
  await check(await first.locator('[data-ref="ext-link"]').textContent() === 'Voeg 4 uitbreidingssets toe →', 'Extension purchase copy must be unchanged.');

  for (const width of [320, 375, 640, 700, 800, 1280]) {
    await render(fixtures.html.first, width);
    const cards = page.locator('.woopc__cards');
    const table = page.locator('.woopc__table-wrap');
    const innerWidth = width - 32;
    await check(innerWidth < 760 ? await cards.isVisible() && !(await table.isVisible()) : await table.isVisible() && !(await cards.isVisible()), `Responsive layout must match available width at ${width}px.`);
    await check(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `The page must not overflow horizontally at ${width}px.`);
  }
  await render(fixtures.html.first, 1280, '600px');
  await check(await page.locator('.woopc__cards').isVisible(), 'A narrow desktop Elementor container must show cards.');
  await check(!(await page.locator('.woopc__table-wrap').isVisible()), 'A narrow desktop container must hide the table.');

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
