'use strict';

// Chromium runs only inside the verified P66 container; it never receives DB keys.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const readline = require('node:readline');
const { once } = require('node:events');
const WRITER = 'http://127.0.0.1:8787/p64-create-drafts-local.php';
const READER = 'http://127.0.0.1:8786/p56-live-pg-review-local.php';
const LOGO_SHA256 = '009e7096173ec354acc5e702f5f0b2609a8b2e34a860d31b252fbb3dc6d0db58';
class ProbeFailure extends Error {}
function check(value, code) { if (!value) throw new ProbeFailure(code); }
let browser;
let phase = 'PREFLIGHT';
let input;

async function main() {
  const mode = process.argv[2];
  const priorRef = process.argv[3];
  check(fs.existsSync('/.dockerenv') && path.resolve(__dirname) === '/repo/tests/Browser'
    && process.env.AIA_P66_BROWSER_CI === 'isolated-p66-v1'
    && process.env.GITHUB_ACTIONS === 'true'
    && process.env.GITHUB_REPOSITORY === 'rafhael100/imagine-ai-agents'
    && /^[a-f0-9]{40}$/.test(process.env.AIA_P66_SOURCE_SHA || '')
    && /^[a-f0-9]{40}$/.test(process.env.AIA_P66_SOURCE_TREE || ''), 'P66_CONTAINER_CONTEXT');
  check((mode === 'after' && process.argv.length === 3)
    || (mode === 'before' && process.argv.length === 4 && /^SYNTHETIC_PROPOSAL_[A-F0-9]{24}$/.test(priorRef)),
  'P66_MODE');
  const packagePath = '/deps/node_modules/playwright/package.json';
  check(JSON.parse(fs.readFileSync(packagePath, 'utf8')).version === '1.56.1', 'P66_PACKAGE_VERSION');
  const logo = fs.readFileSync('/repo/examples/assets/logo-imagine.png');
  check(crypto.createHash('sha256').update(logo).digest('hex') === LOGO_SHA256, 'P66_SOURCE_LOGO');
  check(fs.lstatSync('/artefacts').isDirectory() && !fs.lstatSync('/artefacts').isSymbolicLink(), 'P66_ARTIFACT_PATH');
  const { chromium } = require('/deps/node_modules/playwright');
  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 },
    locale: 'pt-BR', timezoneId: 'America/Sao_Paulo', serviceWorkers: 'block', reducedMotion: 'reduce' });
  context.setDefaultTimeout(10000);
  context.setDefaultNavigationTimeout(15000);
  let posts = 0;
  let unexpected = 0;
  const nativePosts = new Set();
  let writesFinished = mode === 'before';
  await context.route('**/*', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const valid = (url.origin === new URL(WRITER).origin && url.pathname === new URL(WRITER).pathname)
      || (url.origin === new URL(READER).origin && url.pathname === new URL(READER).pathname);
    const icon = [new URL(WRITER).origin, new URL(READER).origin].includes(url.origin)
      && url.pathname === '/favicon.ico' && request.method() === 'GET' && !url.search;
    if (request.method() === 'POST') posts++;
    if ((!valid && !icon) || !['GET', 'POST'].includes(request.method())
      || (request.method() === 'POST' && (writesFinished || request.url() !== WRITER))) {
      unexpected++;
      return route.abort();
    }
    return route.continue();
  });
  const page = await context.newPage();
  page.on('pageerror', () => { unexpected++; });
  async function navigation(action, status = 200) {
    const response = await action();
    check(response, 'P66_NAVIGATION_RESPONSE_MISSING');
    const submissions = [];
    for (let request = response.request(); request; request = request.redirectedFrom()) {
      if (request.method() !== 'POST') continue;
      // Observe Chromium's original header, including a POST followed by 303.
      // Never inject Origin, Referer, cookies or fetch metadata into the request.
      const origin = await request.headerValue('origin');
      const category = origin === new URL(WRITER).origin ? 'EXPECTED'
        : origin === 'null' ? 'NULL' : origin === null ? 'ABSENT' : 'OTHER';
      submissions.push({ request, category });
    }
    const diagnostic = submissions.length ? '_ORIGIN_' + submissions[0].category : '';
    check(response.status() === status,
      'P66_NAVIGATION_EXPECTED_' + status + '_ACTUAL_' + response.status() + diagnostic);
    for (const { request, category } of submissions) {
      check(mode === 'after' && request.url() === WRITER && category === 'EXPECTED',
        'P66_NATIVE_POST_ORIGIN_' + category);
      const submitted = await request.response();
      check(submitted && await submitted.headerValue('referrer-policy') === 'same-origin',
        'P66_WRITER_POST_REFERRER_POLICY');
      nativePosts.add(request);
    }
    const headers = await response.allHeaders();
    check(headers['cache-control']?.includes('no-store') && headers['x-content-type-options'] === 'nosniff'
      && headers['content-type']?.startsWith('text/html') && headers['x-frame-options'] === 'DENY',
    'P66_PRIVATE_HEADERS');
    const csp = headers['content-security-policy'] || '';
    check(csp.includes("default-src 'none'") && csp.includes("frame-ancestors 'none'")
      && csp.includes("base-uri 'none'") && (mode === 'before' || csp.includes('img-src data:')),
    'P66_PRIVATE_CSP');
    check(!Object.keys(headers).some(key => key.startsWith('access-control-')), 'P66_NO_CORS');
    const expectedPolicy = mode === 'after' && response.url() === WRITER ? 'same-origin' : 'no-referrer';
    check(headers['referrer-policy'] === expectedPolicy, 'P66_RESPONSE_REFERRER_POLICY');
    return response;
  }
  const go = url => navigation(() => page.goto(url, { waitUntil: 'domcontentloaded' }));
  const submit = (button, status = 200) => navigation(async () => {
    const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), button.press('Enter')]);
    return response;
  }, status);
  async function capture(name) {
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: '/artefacts/' + name, fullPage: true, animations: 'disabled' });
  }
  async function checkpoint(name) {
    const acknowledgement = once(input, 'line');
    process.stdout.write('P66_CHECKPOINT ' + name + '\n');
    const [line] = await acknowledgement;
    check(line === 'P66_ACK ' + name, 'P66_PARENT_ACK');
  }
  async function safeContent() {
    check(await page.locator('script,iframe').count() === 0, 'P66_NO_ACTIVE_CONTENT');
    const text = await page.locator('body').innerText();
    check(text.includes('NO-GO operacional') && !/SYNTHETIC_P64_AUTHOR|SYNTHETIC_P62_PRIVATE_B|canonical_vote|signature_b64|public_key_b64/.test(text),
      'P66_NO_PRIVATE_FIELDS');
    check(await page.getByRole('button', { name: /aprovar|executar|enviar/i }).count() === 0, 'P66_NO_BUSINESS_ACTIONS');
  }
  async function brandAndNavigation(kind) {
    const image = page.locator('img[data-imagine-brand="original"]');
    check(await image.count() === 1 && await image.getAttribute('alt') === 'Imagine', 'P66_OFFICIAL_BRAND');
    const source = await image.getAttribute('src');
    check(source?.startsWith('data:image/png;base64,')
      && Buffer.from(source.substring(22), 'base64').equals(logo), 'P66_EXACT_EMBEDDED_LOGO');
    check(await image.evaluate(async element => { await element.decode(); return element.naturalWidth > 0; }), 'P66_LOGO_RENDERED');
    const nav = page.locator('nav[data-imagine-lab-nav]');
    check(await nav.count() === 1 && await nav.getByRole('link').count() === 2, 'P66_TASK_NAVIGATION');
    const active = nav.locator('[aria-current="page"]');
    check(await active.count() === 1 && await active.innerText() === (kind === 'create' ? 'Criar rascunhos' : 'Fila de revisão'),
      'P66_ACTIVE_LOCATION');
    const createTarget = new URL(await nav.getByRole('link', { name: 'Criar rascunhos' }).getAttribute('href'), page.url());
    const reviewTarget = new URL(await nav.getByRole('link', { name: 'Fila de revisão' }).getAttribute('href'), page.url());
    check(createTarget.href === WRITER && [READER, READER + '?kind=all'].includes(reviewTarget.href),
      'P66_FIXED_NAVIGATION_TARGETS');
    check(await page.locator('h1').count() === 1 && await page.locator('main#main-content').getAttribute('tabindex') === '-1',
      'P66_PAGE_HIERARCHY');
    check(await page.locator('a[data-imagine-skip]').getAttribute('href') === '#main-content', 'P66_SKIP_DESTINATION');
  }
  async function layoutChecks() {
    const result = await page.evaluate(() => {
      const rgba = value => {
        const values = value.match(/[\d.]+/g)?.map(Number);
        return values?.length >= 3 ? [values[0], values[1], values[2], values[3] ?? 1] : null;
      };
      const blend = (front, back) => front.slice(0, 3).map((value, index) => value * front[3] + back[index] * (1 - front[3]));
      const luminance = color => color.map(value => value / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4)
        .reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
      const visible = element => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return box.width > 0 && box.height > 0 && box.bottom > 0 && box.right > 0
          && style.visibility !== 'hidden' && style.display !== 'none';
      };
      const touch = [...document.querySelectorAll('a,button,input:not([type="hidden"])')]
        .filter(visible).every(element => { const box = element.getBoundingClientRect(); return box.width >= 43.5 && box.height >= 43.5; });
      const labels = [...document.querySelectorAll('input:not([type="hidden"])')].every(element => element.labels?.length > 0);
      let contrast = true;
      let inspected = 0;
      for (const element of document.querySelectorAll('h1,h2,h3,p,label,small,nav a,button,.muted,.state,.help,.detail-link')) {
        if (!visible(element) || !element.textContent.trim()) continue;
        const style = getComputedStyle(element);
        const ancestry = [];
        for (let node = element; node; node = node.parentElement) ancestry.unshift(node);
        let background = [255, 255, 255];
        for (const node of ancestry) {
          const color = rgba(getComputedStyle(node).backgroundColor);
          if (color) background = blend(color, background);
        }
        const foreground = rgba(style.color);
        if (!foreground) { contrast = false; continue; }
        const light = luminance(blend(foreground, background));
        const dark = luminance(background);
        const ratio = (Math.max(light, dark) + .05) / (Math.min(light, dark) + .05);
        const large = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.667 && Number(style.fontWeight) >= 700);
        if (ratio + .001 < (large ? 3 : 4.5)) contrast = false;
        inspected++;
      }
      return { overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
        touch, labels, contrast, inspected };
    });
    check(!result.overflow, 'P66_PAGE_OVERFLOW');
    check(result.touch, 'P66_TOUCH_TARGETS');
    check(result.labels, 'P66_INPUT_LABELS');
    check(result.contrast && result.inspected >= 5, 'P66_COMPUTED_TEXT_CONTRAST');
  }
  async function layouts(kind) {
    await brandAndNavigation(kind);
    await safeContent();
    for (const width of [360, 768, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      await layoutChecks();
      if (width !== 768) await capture('p66-after-' + kind + '-' + (width === 360 ? 'mobile' : 'desktop') + '.png');
    }
    // This tests CSS magnification/reflow, not native browser zoom or OS DPI.
    await page.evaluate(() => { document.documentElement.style.zoom = '2'; });
    await layoutChecks();
    await page.evaluate(() => { document.documentElement.style.zoom = ''; });
  }
  async function keyboardSkip() {
    await page.keyboard.press('Tab');
    check(await page.locator('a[data-imagine-skip]').evaluate(element => element === document.activeElement), 'P66_SKIP_FIRST_FOCUS');
    check(await page.locator('a[data-imagine-skip]').evaluate(element => {
      const style = getComputedStyle(element);
      return style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) >= 2;
    }), 'P66_VISIBLE_KEYBOARD_FOCUS');
    await page.keyboard.press('Enter');
    check(await page.locator('main#main-content').evaluate(element => element === document.activeElement), 'P66_SKIP_ACTIVATION');
  }
  if (mode === 'before') {
    phase = 'BASE_COMPARISON';
    await go(WRITER);
    check(await page.locator('[data-p64-local-form]').count() === 1, 'P66_BASE_CREATE');
    await safeContent();
    await capture('p66-before-create-desktop.png');
    await go(READER);
    check(await page.locator('[data-proposal-detail]').count() === 2, 'P66_BASE_QUEUE');
    await safeContent();
    await capture('p66-before-queue-desktop.png');
    await go(READER + '?kind=human&proposal=' + priorRef + '#proposal-detail');
    check(await page.locator('[data-detail-state="VERIFIED"]').getAttribute('data-detail-proposal') === priorRef,
      'P66_BASE_SAME_DETAIL');
    await safeContent();
    await capture('p66-before-detail-desktop.png');
    check(posts === 0 && unexpected === 0, 'P66_BASE_GET_ONLY');
    return { schema: 'AIA_P66_BROWSER_BEFORE_V1', screenshots: 3, posts: 0 };
  }
  input = readline.createInterface({ input: process.stdin, terminal: false });
  phase = 'CREATE';
  await go(WRITER);
  await keyboardSkip();
  for (const id of ['visits', 'lead-rate', 'client-rate']) {
    await page.keyboard.press('Tab');
    check(await page.locator('#' + id).evaluate(element => element === document.activeElement), 'P66_FORM_FOCUS_ORDER');
  }
  await page.keyboard.press('Tab');
  check(await page.getByRole('button', { name: 'Ver prévia', exact: true })
    .evaluate(element => element === document.activeElement), 'P66_FORM_PRIMARY_FOCUS');
  await layouts('create');
  const previewButton = page.getByRole('button', { name: 'Ver prévia', exact: true });
  phase = 'VALIDATION';
  await page.locator('#lead-rate').fill('101');
  await submit(previewButton, 400);
  check(await page.getByRole('status').count() === 1
    && (await page.getByRole('status').innerText()).includes('Informe visitas inteiras'), 'P66_VALIDATION_FEEDBACK');
  await page.locator('#visits').fill('0');
  await page.locator('#lead-rate').fill('10');
  await submit(previewButton);
  check(await page.locator('[data-p64-preview]').count() === 1 && await page.locator('[data-p64-proposal]').count() === 0
    && await page.getByRole('button', { name: /^Guardar / }).count() === 0, 'P66_EMPTY_PREVIEW');
  phase = 'PREVIEW';
  await page.locator('#visits').fill('120');
  await page.locator('#lead-rate').fill('10');
  await page.locator('#client-rate').fill('20');
  await submit(previewButton);
  check(JSON.stringify(await page.locator('.metrics .metric b').allTextContents()) === JSON.stringify(['120', '12', '2']),
    'P66_REAL_PREVIEW_NUMBERS');
  check(await page.locator('[data-p64-proposal]').count() === 2, 'P66_PREVIEW_TWO_DRAFTS');
  const detailRef = await page.locator('[data-p64-proposal]').filter({ hasText: 'Proposta para revisão humana' }).getAttribute('data-p64-proposal');
  check(/^SYNTHETIC_PROPOSAL_[A-F0-9]{24}$/.test(detailRef || ''), 'P66_PREVIEW_REFERENCE');
  await checkpoint('preview');
  phase = 'SAVE';
  await submit(page.getByRole('button', { name: 'Guardar 2 rascunhos', exact: true }));
  writesFinished = true;
  check(page.url() === READER && await page.locator('[data-proposal-detail]').count() === 2, 'P66_SAVE_TO_QUEUE');
  await checkpoint('saved');
  phase = 'QUEUE';
  await layouts('queue');
  await navigation(async () => {
    const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.locator('.filters').getByRole('link', { name: 'Avisos', exact: true }).press('Enter')]);
    return response;
  });
  check(await page.locator('[data-proposal-detail]').count() === 0
    && (await page.locator('.queue').innerText()).includes('Nenhuma proposta'), 'P66_EMPTY_FILTER');
  await navigation(async () => {
    const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.locator('.filters').getByRole('link', { name: 'Revisão', exact: true }).press('Enter')]);
    return response;
  });
  check(await page.locator('.filters [aria-current="page"]').innerText() === 'Revisão'
    && await page.locator('[data-proposal-detail]').count() === 1, 'P66_PRESERVED_FILTER');
  phase = 'DETAIL';
  await navigation(async () => {
    const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.locator('[data-proposal-detail="' + detailRef + '"]').press('Enter')]);
    return response;
  });
  const detail = page.locator('[data-detail-state="VERIFIED"]');
  check(await detail.count() === 1 && await detail.getAttribute('data-detail-proposal') === detailRef
    && new URL(page.url()).searchParams.get('kind') === 'human', 'P66_VERIFIED_SAME_PROPOSAL');
  check((await detail.locator('.condition').innerText()).replace(/\s/g, '') === '12≥12'
    && JSON.stringify(await detail.locator('.inputs dd').allTextContents()) === JSON.stringify(['120', '10,00%', '20,00%']),
  'P66_ORIGINAL_INPUTS');
  await layouts('detail');
  await navigation(async () => {
    const [response] = await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.locator('nav[data-imagine-lab-nav]').getByRole('link', { name: 'Criar rascunhos' }).press('Enter')]);
    return response;
  });
  check(page.url() === WRITER && await page.locator('[data-p64-local-form]').count() === 1, 'P66_RETURN_TO_CREATION');
  check(posts === 4 && nativePosts.size === 4 && unexpected === 0, 'P66_ONLY_EXISTING_FORM_ACTIONS');
  await checkpoint('complete');
  return { schema: 'AIA_P66_BROWSER_AFTER_V1', detail_ref: detailRef, screenshots: 6,
    posts, native_post_origins_verified: 4, css_200_percent_reflow: true, native_browser_zoom_tested: false };
}

(async () => {
  try {
    const result = await main();
    await browser.close();
    browser = null;
    process.stdout.write('P66_RESULT ' + JSON.stringify(result) + '\n');
  } catch (error) {
    const code = error instanceof ProbeFailure && /^P66_[A-Z0-9_]+$/.test(error.message)
      ? error.message.substring(4) : 'RUNTIME';
    process.stdout.write('P66_BROWSER_DENIED_' + phase + '_' + code + '\n');
    process.exitCode = 1;
  } finally {
    input?.close();
    if (browser) {
      try { await browser.close(); } catch { process.exitCode = 1; }
    }
  }
})();
