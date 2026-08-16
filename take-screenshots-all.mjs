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

    ['users', 'admin/users', 'User management'],
    ['user-create', 'admin/users/create', 'User management'],
    ['roles', 'admin/roles', 'User management'],
    ['role-create', 'admin/roles/create', 'User management'],
    ['permissions', 'admin/permissions', 'User management'],

    ['pages', 'admin/pages', 'Content'],
    ['page-create', 'admin/pages/create', 'Content'],
    ['articles', 'admin/articles', 'Content'],
    ['article-create', 'admin/articles/create', 'Content'],
    ['categories', 'admin/categories', 'Content'],
    ['media', 'admin/media', 'Content'],
    ['landing', 'admin/landing', 'Content'],

    ['brand', 'admin/brand', 'Brand & appearance', 3000],
    ['settings', 'admin/settings', 'Brand & appearance', 3000],

    ['reports-index', 'admin/reports', 'Reporting', 3000],
    ['report-schedules', 'admin/report-schedules', 'Reporting'],

    ['blocks', 'admin/blocks', 'shadcn', 3000],
    ['examples', 'admin/examples', 'shadcn', 3000],

    ['email-templates', 'admin/email-templates', 'Email'],
    ['email-template-create', 'admin/email-templates/create', 'Email'],
    ['email-log', 'admin/email-logs', 'Email'],
    ['email-settings', 'admin/email-settings', 'Email'],
    ['firebase-settings', 'admin/firebase-settings', 'Email'],

    ['activity-log', 'admin/activity-logs', 'System'],
    ['api-tokens', 'admin/api-tokens', 'System'],
    ['backups', 'admin/backups', 'System'],
    ['system-health', 'admin/system-health', 'System'],
    ['admin-notifications', 'admin/notifications', 'System'],
    ['admin-notification-create', 'admin/notifications/create', 'System'],
    ['plugin-example', 'admin/myra-example', 'System'],
    ['learning-courses', 'admin/learning/courses', 'System'],
    ['learning-site-identity', 'admin/learning/site-identity', 'System'],

    ['demo', 'admin/demo', 'Feature gallery', 2500],
    ['demo-action-modals', 'admin/demo/action-modals', 'Feature gallery'],
    ['demo-advanced-filters', 'admin/demo/advanced-filters', 'Feature gallery', 3000],
    ['demo-ai-filter', 'admin/demo/ai-filter', 'Feature gallery', 3000],
    ['demo-bulk-actions', 'admin/demo/bulk-actions', 'Feature gallery'],
    ['demo-chart-primitives', 'admin/demo/chart-primitives', 'Feature gallery', 3500],
    ['demo-code-editor', 'admin/demo/code-editor', 'Feature gallery', 3500],
    ['demo-conditional-fields', 'admin/demo/conditional-fields', 'Feature gallery'],
    ['demo-conversation', 'admin/demo/conversation', 'Feature gallery', 3000],
    ['demo-dashboard-editor', 'admin/demo/dashboard-editor', 'Feature gallery', 3000],
    ['demo-empty-and-item', 'admin/demo/empty-and-item', 'Feature gallery'],
    ['demo-field-types', 'admin/demo/field-types', 'Feature gallery', 3000],
    ['demo-form-builder', 'admin/demo/form-builder', 'Feature gallery', 3000],
    ['demo-global-search', 'admin/demo/global-search', 'Feature gallery'],
    ['demo-grouping', 'admin/demo/grouping', 'Feature gallery'],
    ['demo-import-export', 'admin/demo/import-export', 'Feature gallery'],
    ['demo-infolist', 'admin/demo/infolist', 'Feature gallery'],
    ['demo-inline-editing', 'admin/demo/inline-editing', 'Feature gallery'],
    ['demo-landing-templates', 'admin/demo/landing-templates', 'Feature gallery', 3000],
    ['demo-live-widgets', 'admin/demo/live-widgets', 'Feature gallery', 3000],
    ['demo-map', 'admin/demo/map', 'Feature gallery', 6000],
    ['demo-map-markers', 'admin/demo/map-markers', 'Feature gallery', 6000],
    ['demo-offline-shell', 'admin/demo/offline-shell', 'Feature gallery'],
    ['demo-otp-and-combobox', 'admin/demo/otp-and-combobox', 'Feature gallery'],
    ['demo-playground', 'admin/demo/playground', 'Feature gallery', 3500],
    ['demo-plugins', 'admin/demo/plugins', 'Feature gallery'],
    ['demo-questionnaire', 'admin/demo/questionnaire', 'Feature gallery', 3000],
    ['demo-relation-manager', 'admin/demo/relation-manager', 'Feature gallery'],
    ['demo-reordering', 'admin/demo/reordering', 'Feature gallery'],
    ['demo-repeater-field', 'admin/demo/repeater-field', 'Feature gallery'],
    ['demo-report-delivery', 'admin/demo/report-delivery', 'Feature gallery', 3000],
    ['demo-reports', 'admin/demo/reports', 'Feature gallery', 4000],
    ['demo-rich-text-editor', 'admin/demo/rich-text-editor', 'Feature gallery', 3000],
    ['demo-saved-views', 'admin/demo/saved-views', 'Feature gallery', 3000],
    ['demo-scale', 'admin/demo/scale', 'Feature gallery', 4000],
    ['demo-scale-cursor', 'admin/demo/scale-cursor', 'Feature gallery', 4000],
    ['demo-soft-deletes', 'admin/demo/soft-deletes', 'Feature gallery'],
    ['demo-tenancy', 'admin/demo/tenancy', 'Feature gallery'],
    ['demo-widgets', 'admin/demo/widgets', 'Feature gallery', 3500],
    ['demo-wizard', 'admin/demo/wizard', 'Feature gallery'],
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
