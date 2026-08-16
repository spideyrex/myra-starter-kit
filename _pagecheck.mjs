// Authenticated crawl of every admin PAGE, detecting failures an HTTP status
// check cannot see: Inertia error modals, rendered error pages, uncaught JS.
// JSON API routes are excluded — they are not pages and prove nothing here.
import puppeteer from 'puppeteer-core';

const BASE = 'https://listen.ntfier.com';
const SKIP = /export-csv|\/ping$|table-views|\/search$|import-sample|dashboard-catalogue/;

const routes = `
dashboard
profile
profile/edit
profile/security
notifications
notifications/preferences
admin/activity-logs
admin/api-tokens
admin/articles
admin/articles/create
admin/backups
admin/blocks
admin/brand
admin/categories
admin/demo
admin/demo/action-modals
admin/demo/advanced-filters
admin/demo/ai-filter
admin/demo/bulk-actions
admin/demo/chart-primitives
admin/demo/code-editor
admin/demo/conditional-fields
admin/demo/conversation
admin/demo/dashboard-editor
admin/demo/empty-and-item
admin/demo/field-types
admin/demo/form-builder
admin/demo/global-search
admin/demo/grouping
admin/demo/import-export
admin/demo/infolist
admin/demo/inline-editing
admin/demo/landing-templates
admin/demo/live-widgets
admin/demo/map
admin/demo/map-markers
admin/demo/offline-shell
admin/demo/otp-and-combobox
admin/demo/playground
admin/demo/plugins
admin/demo/questionnaire
admin/demo/relation-manager
admin/demo/reordering
admin/demo/repeater-field
admin/demo/report-delivery
admin/demo/reports
admin/demo/rich-text-editor
admin/demo/saved-views
admin/demo/scale
admin/demo/scale-cursor
admin/demo/soft-deletes
admin/demo/tenancy
admin/demo/widgets
admin/demo/wizard
admin/email-logs
admin/email-settings
admin/email-templates
admin/email-templates/create
admin/examples
admin/firebase-settings
admin/landing
admin/learning/courses
admin/learning/site-identity
admin/media
admin/myra-example
admin/notifications
admin/notifications/create
admin/pages
admin/pages/create
admin/permissions
admin/reports
admin/report-schedules
admin/roles
admin/roles/create
admin/settings
admin/system-health
admin/users
admin/users/create
`.trim().split('\n').map(s => s.trim()).filter(s => s && !SKIP.test(s));

const SIGNATURES = [
    { name: 'INERTIA-JSON', re: /All Inertia requests must receive a valid Inertia response/i },
    { name: '500', re: /\b500\b[\s\S]{0,80}Server Error/i },
    { name: '404', re: /\b404\b[\s\S]{0,80}Page Not Found/i },
    { name: '403', re: /\b403\b[\s\S]{0,80}(Forbidden|Unauthorized)/i },
    { name: '419', re: /\b419\b[\s\S]{0,80}Expired/i },
    { name: 'LARAVEL-TRACE', re: /Whoops|Stack trace:/i },
];

(async () => {
    const browser = await puppeteer.launch({
        executablePath: process.env.MYRA_CHROME || '/usr/bin/chromium-browser',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
               '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader',
               '--window-size=1440,900'],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2', timeout: 30000 });
    await page.type('input[type="email"], input[name="email"]', 'admin@admin.com');
    await page.type('input[type="password"], input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
    console.log('logged in ->', page.url(), '\n');

    const bad = [];

    for (const path of routes) {
        const jsErrors = [];
        const onErr = (e) => jsErrors.push(String(e.message || e).slice(0, 160));
        const onConsole = (m) => { if (m.type() === 'error') jsErrors.push('console: ' + m.text().slice(0, 160)); };
        page.on('pageerror', onErr);
        page.on('console', onConsole);

        let status = 0;
        const issues = [];

        try {
            const res = await page.goto(`${BASE}/${path}`, { waitUntil: 'networkidle2', timeout: 45000 });
            status = res ? res.status() : 0;
            await new Promise(r => setTimeout(r, 900));

            const text = await page.evaluate(() => document.body?.innerText ?? '');

            for (const sig of SIGNATURES) if (sig.re.test(text)) issues.push(sig.name);
            if (status >= 400) issues.push('HTTP-' + status);
            if (page.url().includes('/login')) issues.push('BOUNCED-TO-LOGIN');
            if (text.trim().length < 40) issues.push('BLANK-PAGE');

            const real = jsErrors.filter(e => !/favicon|manifest|sw\.js|net::ERR_|Download the Vue/i.test(e));
            if (real.length) issues.push('JS(' + real.length + '): ' + real[0]);
        } catch (err) {
            issues.push('NAV-FAIL: ' + String(err.message).slice(0, 90));
        }

        page.off('pageerror', onErr);
        page.off('console', onConsole);

        if (issues.length) {
            bad.push({ path, status, issues });
            console.log(`FAIL  /${path}  [${status}]  ${issues.join(' | ')}`);
        } else {
            console.log(`ok    /${path}`);
        }
    }

    await browser.close();
    console.log(`\n===== ${routes.length} pages checked, ${bad.length} with issues =====`);
    for (const b of bad) console.log(`  /${b.path}  ${b.issues.join(' | ')}`);
})();
