import puppeteer from 'puppeteer-core';

const BASE = 'https://listen.ntfier.com';
const OUT = 'public/docs/screenshots';
const VIEWPORT = { width: 1440, height: 900 };

const demos = [
    { name: 'demo', path: '/admin/demo' },
    { name: 'demo-rich-text-editor', path: '/admin/demo/rich-text-editor' },
    { name: 'demo-repeater-field', path: '/admin/demo/repeater-field' },
    { name: 'demo-form-builder', path: '/admin/demo/form-builder' },
    { name: 'demo-bulk-actions', path: '/admin/demo/bulk-actions' },
    { name: 'demo-soft-deletes', path: '/admin/demo/soft-deletes' },
    { name: 'demo-action-modals', path: '/admin/demo/action-modals' },
    { name: 'demo-import-export', path: '/admin/demo/import-export' },
    { name: 'demo-global-search', path: '/admin/demo/global-search' },
    { name: 'demo-inline-editing', path: '/admin/demo/inline-editing' },
    { name: 'demo-conditional-fields', path: '/admin/demo/conditional-fields' },
    { name: 'demo-infolist', path: '/admin/demo/infolist' },
    { name: 'demo-relation-manager', path: '/admin/demo/relation-manager' },
    { name: 'demo-grouping', path: '/admin/demo/grouping' },
    { name: 'demo-reordering', path: '/admin/demo/reordering' },
    { name: 'demo-widgets', path: '/admin/demo/widgets' },
    { name: 'demo-field-types', path: '/admin/demo/field-types' },
    { name: 'demo-advanced-filters', path: '/admin/demo/advanced-filters' },
    { name: 'demo-wizard', path: '/admin/demo/wizard' },
    { name: 'demo-map', path: '/admin/demo/map', wait: 6000 },
];

(async () => {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium-browser',
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            // Software WebGL so MapLibre GL renders in headless Chromium.
            '--use-gl=angle',
            '--use-angle=swiftshader',
            '--enable-unsafe-swiftshader',
            '--ignore-gpu-blocklist',
            '--window-size=1440,900',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport(VIEWPORT);

    // Login
    console.log('Logging in...');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2', timeout: 30000 });
    await page.type('input[type="email"], input[name="email"]', 'admin@admin.com');
    await page.type('input[type="password"], input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
    console.log('Logged in!');

    for (const d of demos) {
        try {
            console.log(`Capturing: ${d.name} (${d.path})`);
            await page.goto(`${BASE}${d.path}`, { waitUntil: 'networkidle2', timeout: 30000 });
            // Wait for animations and lazy content (longer for map tiles)
            await new Promise(r => setTimeout(r, d.wait ?? 2000));
            await page.screenshot({
                path: `${OUT}/${d.name}.png`,
                fullPage: true,
            });
            console.log(`  OK: ${d.name}.png`);
        } catch (err) {
            console.error(`  FAIL: ${d.name} - ${err.message}`);
        }
    }

    await browser.close();
    console.log(`Done! Captured ${demos.length} demo screenshots.`);
})();
