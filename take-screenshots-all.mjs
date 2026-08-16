// Full authenticated screenshot pass over every admin page.
//
// Capture and verification are the same step on purpose: a screenshot of a 404
// or an error modal is worse than no screenshot, because it looks like
// documentation. Anything that trips a signature is deleted, not committed.
//
//   MYRA_CHROME="C:/Program Files/Google/Chrome/Application/chrome.exe" node take-screenshots-all.mjs
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const BASE = 'https://listen.ntfier.com';
const OUT = 'public/docs/screenshots';
const VIEWPORT = { width: 1440, height: 900 };

/** [screenshot name, path, section, optional extra settle ms] */
const PAGES = [
    ['dashboard', 'dashboard', 'Core', 3500],
    ['profile', 'profile', 'Core'],
    ['profile-edit', 'profile/edit', 'Core'],
    ['profile-security', 'profile/security', 'Core'],
    ['notifications', 'notifications', 'Core'],
    ['notifications-preferences', 'notifications/preferences', 'Core'],

    ['users', 'dashboard/users', 'User management'],
    ['user-create', 'dashboard/users/create', 'User management'],
    ['roles', 'dashboard/roles', 'User management'],
    ['role-create', 'dashboard/roles/create', 'User management'],
    ['permissions', 'dashboard/permissions', 'User management'],

    ['pages', 'dashboard/pages', 'Content'],
    ['page-create', 'dashboard/pages/create', 'Content'],
    ['articles', 'dashboard/articles', 'Content'],
    ['article-create', 'dashboard/articles/create', 'Content'],
    ['categories', 'dashboard/categories', 'Content'],
    ['media', 'dashboard/media', 'Content'],
    ['landing', 'dashboard/landing', 'Content'],

    ['brand', 'dashboard/brand', 'Brand & appearance', 3000],
    ['settings', 'dashboard/settings', 'Brand & appearance', 3000],

    ['reports-index', 'dashboard/reports', 'Reporting', 3000],
    ['report-schedules', 'dashboard/report-schedules', 'Reporting'],

    ['blocks', 'dashboard/blocks', 'shadcn', 3000],
    ['examples', 'dashboard/examples', 'shadcn', 3000],

    ['email-templates', 'dashboard/email-templates', 'Email'],
    ['email-template-create', 'dashboard/email-templates/create', 'Email'],
    ['email-log', 'dashboard/email-logs', 'Email'],
    ['email-settings', 'dashboard/email-settings', 'Email'],
    ['firebase-settings', 'dashboard/firebase-settings', 'Email'],

    ['activity-log', 'dashboard/activity-logs', 'System'],
    ['api-tokens', 'dashboard/api-tokens', 'System'],
    ['backups', 'dashboard/backups', 'System'],
    ['system-health', 'dashboard/system-health', 'System'],
    ['admin-notifications', 'dashboard/notifications', 'System'],
    ['admin-notification-create', 'dashboard/notifications/create', 'System'],
    ['plugin-example', 'dashboard/myra-example', 'System'],
    ['learning-courses', 'dashboard/learning/courses', 'System'],
    ['learning-site-identity', 'dashboard/learning/site-identity', 'System'],

    ['demo', 'dashboard/demo', 'Feature gallery', 2500],
    ['demo-action-modals', 'dashboard/demo/action-modals', 'Feature gallery'],
    ['demo-advanced-filters', 'dashboard/demo/advanced-filters', 'Feature gallery', 3000],
    ['demo-ai-filter', 'dashboard/demo/ai-filter', 'Feature gallery', 3000],
    ['demo-bulk-actions', 'dashboard/demo/bulk-actions', 'Feature gallery'],
    ['demo-chart-primitives', 'dashboard/demo/chart-primitives', 'Feature gallery', 3500],
    ['demo-code-editor', 'dashboard/demo/code-editor', 'Feature gallery', 3500],
    ['demo-conditional-fields', 'dashboard/demo/conditional-fields', 'Feature gallery'],
    ['demo-conversation', 'dashboard/demo/conversation', 'Feature gallery', 3000],
    ['demo-dashboard-editor', 'dashboard/demo/dashboard-editor', 'Feature gallery', 3000],
    ['demo-empty-and-item', 'dashboard/demo/empty-and-item', 'Feature gallery'],
    ['demo-field-types', 'dashboard/demo/field-types', 'Feature gallery', 3000],
    ['demo-form-builder', 'dashboard/demo/form-builder', 'Feature gallery', 3000],
    ['demo-global-search', 'dashboard/demo/global-search', 'Feature gallery'],
    ['demo-grouping', 'dashboard/demo/grouping', 'Feature gallery'],
    ['demo-import-export', 'dashboard/demo/import-export', 'Feature gallery'],
    ['demo-infolist', 'dashboard/demo/infolist', 'Feature gallery'],
    ['demo-inline-editing', 'dashboard/demo/inline-editing', 'Feature gallery'],
    ['demo-landing-templates', 'dashboard/demo/landing-templates', 'Feature gallery', 3000],
    ['demo-live-widgets', 'dashboard/demo/live-widgets', 'Feature gallery', 3000],
    ['demo-map', 'dashboard/demo/map', 'Feature gallery', 6000],
    ['demo-map-markers', 'dashboard/demo/map-markers', 'Feature gallery', 6000],
    ['demo-offline-shell', 'dashboard/demo/offline-shell', 'Feature gallery'],
    ['demo-otp-and-combobox', 'dashboard/demo/otp-and-combobox', 'Feature gallery'],
    ['demo-playground', 'dashboard/demo/playground', 'Feature gallery', 3500],
    ['demo-plugins', 'dashboard/demo/plugins', 'Feature gallery'],
    ['demo-questionnaire', 'dashboard/demo/questionnaire', 'Feature gallery', 3000],
    ['demo-relation-manager', 'dashboard/demo/relation-manager', 'Feature gallery'],
    ['demo-reordering', 'dashboard/demo/reordering', 'Feature gallery'],
    ['demo-repeater-field', 'dashboard/demo/repeater-field', 'Feature gallery'],
    ['demo-report-delivery', 'dashboard/demo/report-delivery', 'Feature gallery', 3000],
    ['demo-reports', 'dashboard/demo/reports', 'Feature gallery', 4000],
    ['demo-rich-text-editor', 'dashboard/demo/rich-text-editor', 'Feature gallery', 3000],
    ['demo-saved-views', 'dashboard/demo/saved-views', 'Feature gallery', 3000],
    ['demo-scale', 'dashboard/demo/scale', 'Feature gallery', 4000],
    ['demo-scale-cursor', 'dashboard/demo/scale-cursor', 'Feature gallery', 4000],
    ['demo-soft-deletes', 'dashboard/demo/soft-deletes', 'Feature gallery'],
    ['demo-tenancy', 'dashboard/demo/tenancy', 'Feature gallery'],
    ['demo-widgets', 'dashboard/demo/widgets', 'Feature gallery', 3500],
    ['demo-wizard', 'dashboard/demo/wizard', 'Feature gallery'],
];

const BAD = [
    [/All Inertia requests must receive a valid Inertia response/i, 'INERTIA-JSON'],
    [/\b500\b[\s\S]{0,80}Server Error/i, '500'],
    [/\b404\b[\s\S]{0,80}Page Not Found/i, '404'],
    [/\b403\b[\s\S]{0,80}(Forbidden|Unauthorized)/i, '403'],
    [/Whoops|Stack trace:/i, 'TRACE'],
];

(async () => {
    const browser = await puppeteer.launch({
        executablePath: process.env.MYRA_CHROME || '/usr/bin/chromium-browser',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
               '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader',
               '--ignore-gpu-blocklist', '--window-size=1440,900'],
    });

    const page = await browser.newPage();
    await page.setViewport(VIEWPORT);

    // The first cold request can be slow; retry rather than abort the whole run.
    for (let attempt = 1; ; attempt++) {
        try {
            await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 90000 });
            await page.type('input[type="email"], input[name="email"]', 'admin@admin.com');
            await page.type('input[type="password"], input[name="password"]', 'password');
            await Promise.all([
                page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 90000 }),
                page.click('button[type="submit"]'),
            ]);
            break;
        } catch (err) {
            if (attempt >= 3) throw err;
            console.log(`login attempt ${attempt} failed (${String(err.message).slice(0, 50)}), retrying…`);
            await new Promise(r => setTimeout(r, 4000));
        }
    }
    console.log('logged in ->', page.url(), '\n');

    const ok = [];
    const failed = [];

    for (const [name, path, section, wait] of PAGES) {
        const file = `${OUT}/${name}.png`;
        try {
            const res = await page.goto(`${BASE}/${path}`, { waitUntil: 'networkidle2', timeout: 45000 });
            const status = res ? res.status() : 0;
            await new Promise(r => setTimeout(r, wait ?? 2000));

            const text = await page.evaluate(() => document.body?.innerText ?? '');
            const hit = BAD.find(([re]) => re.test(text));

            if (status >= 400 || hit || page.url().includes('/login') || text.trim().length < 40) {
                const why = hit ? hit[1] : status >= 400 ? `HTTP-${status}` : page.url().includes('/login') ? 'BOUNCED' : 'BLANK';
                failed.push({ name, path, why });
                console.log(`SKIP  ${name}  (${why}) — not captured`);
                continue;
            }

            await page.screenshot({ path: file, fullPage: true });
            const kb = Math.round(fs.statSync(file).size / 1024);
            ok.push({ name, path, section, kb });
            console.log(`ok    ${name}.png  ${kb}KB`);
        } catch (err) {
            failed.push({ name, path, why: String(err.message).slice(0, 70) });
            console.log(`FAIL  ${name}  ${String(err.message).slice(0, 70)}`);
        }
    }

    await browser.close();

    fs.writeFileSync('_shots.json', JSON.stringify({ ok, failed }, null, 2));
    console.log(`\n===== captured ${ok.length}/${PAGES.length}, skipped ${failed.length} =====`);
    for (const f of failed) console.log(`  ${f.name} (${f.path}) — ${f.why}`);
})();
