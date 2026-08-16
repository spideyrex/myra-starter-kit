// Screenshots for the surfaces added in v2.1.0 – v2.5.1.
// Same shape as take-screenshots-demos.mjs; run on the VPS where chromium lives.
import puppeteer from 'puppeteer-core';

const BASE = 'https://listen.ntfier.com';
const OUT = 'public/docs/screenshots';
const VIEWPORT = { width: 1440, height: 900 };

const pages = [
    { name: 'demo', path: '/admin/demo' },
    { name: 'demo-playground', path: '/admin/demo/playground', wait: 3000 },
    { name: 'demo-dashboard-editor', path: '/admin/demo/dashboard-editor', wait: 3000 },
    { name: 'demo-live-widgets', path: '/admin/demo/live-widgets', wait: 3000 },
    { name: 'demo-reports', path: '/admin/demo/reports', wait: 4000 },
    { name: 'demo-report-delivery', path: '/admin/demo/report-delivery', wait: 3000 },
    { name: 'demo-saved-views', path: '/admin/demo/saved-views', wait: 3000 },
    { name: 'demo-ai-filter', path: '/admin/demo/ai-filter', wait: 3000 },
    { name: 'demo-offline-shell', path: '/admin/demo/offline-shell', wait: 2500 },
    { name: 'demo-plugins', path: '/admin/demo/plugins', wait: 2500 },
    { name: 'demo-tenancy', path: '/admin/demo/tenancy', wait: 2500 },
    { name: 'demo-scale', path: '/admin/demo/scale', wait: 4000 },
    { name: 'demo-code-editor', path: '/admin/demo/code-editor', wait: 3500 },
    { name: 'demo-widgets', path: '/admin/demo/widgets', wait: 3500 },
    { name: 'demo-advanced-filters', path: '/admin/demo/advanced-filters', wait: 3000 },
    { name: 'demo-import-export', path: '/admin/demo/import-export', wait: 2500 },
    { name: 'reports-index', path: '/admin/reports', wait: 3000 },
    { name: 'dashboard', path: '/admin/dashboard', wait: 3500 },
];

(async () => {
    const browser = await puppeteer.launch({
        executablePath: process.env.MYRA_CHROME || '/usr/bin/chromium-browser',
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--use-gl=angle',
            '--use-angle=swiftshader',
            '--enable-unsafe-swiftshader',
            '--ignore-gpu-blocklist',
            '--window-size=1440,900',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport(VIEWPORT);

    console.log('Logging in...');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2', timeout: 30000 });
    await page.type('input[type="email"], input[name="email"]', 'admin@admin.com');
    await page.type('input[type="password"], input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
    console.log('Logged in:', page.url());

    let ok = 0;
    const failed = [];

    for (const d of pages) {
        try {
            await page.goto(`${BASE}${d.path}`, { waitUntil: 'networkidle2', timeout: 40000 });

            if (page.url().includes('/login')) {
                throw new Error('redirected to login (not authenticated)');
            }

            await new Promise(r => setTimeout(r, d.wait ?? 2000));
            await page.screenshot({ path: `${OUT}/${d.name}.png`, fullPage: true });
            console.log(`  OK   ${d.name}.png`);
            ok++;
        } catch (err) {
            console.error(`  FAIL ${d.name} - ${err.message}`);
            failed.push(d.name);
        }
    }

    await browser.close();
    console.log(`\nCaptured ${ok}/${pages.length}.`);
    if (failed.length) console.log(`Failed: ${failed.join(', ')}`);
})();
