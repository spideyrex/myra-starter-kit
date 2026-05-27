import puppeteer from 'puppeteer-core';

const BASE = 'https://listen.ntfier.com';
const OUT = 'public/docs/screenshots';
const VIEWPORT = { width: 1440, height: 900 };

(async () => {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium-browser',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--window-size=1440,900'],
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

    // 1. Settings > Appearance tab (theme selector)
    console.log('Capturing: settings-appearance');
    await page.goto(`${BASE}/admin/settings`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 1000));
    // Click Appearance tab
    const tabs = await page.$$('[role="tab"]');
    for (const tab of tabs) {
        const text = await tab.evaluate(el => el.textContent.trim());
        if (text === 'Appearance') {
            await tab.click();
            break;
        }
    }
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: `${OUT}/settings-appearance.png`, fullPage: false });
    console.log('  OK: settings-appearance.png');

    // 2. Settings > Homepage tab
    console.log('Capturing: settings-homepage');
    const tabs2 = await page.$$('[role="tab"]');
    for (const tab of tabs2) {
        const text = await tab.evaluate(el => el.textContent.trim());
        if (text === 'Homepage') {
            await tab.click();
            break;
        }
    }
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: `${OUT}/settings-homepage.png`, fullPage: false });
    console.log('  OK: settings-homepage.png');

    // 3. Command palette
    console.log('Capturing: command-palette');
    await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 1000));
    await page.keyboard.down('Control');
    await page.keyboard.press('k');
    await page.keyboard.up('Control');
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: `${OUT}/command-palette.png`, fullPage: false });
    console.log('  OK: command-palette.png');

    // 4. Dark mode dashboard
    console.log('Capturing: dashboard-dark');
    // Close command palette first
    await page.keyboard.press('Escape');
    await new Promise(r => setTimeout(r, 500));
    // Toggle dark mode
    await page.evaluate(() => {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    });
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: `${OUT}/dashboard-dark.png`, fullPage: false });
    console.log('  OK: dashboard-dark.png');

    // 5. Security/2FA page
    console.log('Capturing: security');
    await page.evaluate(() => {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    });
    await page.goto(`${BASE}/profile/security`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: `${OUT}/security.png`, fullPage: false });
    console.log('  OK: security.png');

    // 6. Demo page
    console.log('Capturing: demo');
    await page.goto(`${BASE}/admin/demo`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: `${OUT}/demo.png`, fullPage: false });
    console.log('  OK: demo.png');

    await browser.close();
    console.log('Done!');
})();
