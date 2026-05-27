import puppeteer from 'puppeteer-core';
import { existsSync, mkdirSync } from 'fs';

// One-off capture for the AI settings tab (settings-ai.png).
const BASE = 'https://listen.ntfier.com';
const OUT = 'public/docs/screenshots';
const VIEWPORT = { width: 1440, height: 900 };

if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

(async () => {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium-browser',
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--window-size=1440,900'],
    });

    const page = await browser.newPage();
    await page.setViewport(VIEWPORT);

    console.log('Logging in...');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2', timeout: 30000 });
    await page.type('input[type="email"], input[name="email"]', 'admin@admin.com');
    await page.type('input[type="password"], input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
    console.log('Logged in! URL:', page.url());

    await page.goto(`${BASE}/admin/settings`, { waitUntil: 'networkidle2', timeout: 30000 });
    await new Promise(r => setTimeout(r, 1500));

    const clicked = await page.evaluate(() => {
        const tab = [...document.querySelectorAll('[role="tab"]')].find(el => el.textContent.trim() === 'AI');
        if (tab) { tab.click(); return true; }
        return false;
    });
    console.log('AI tab clicked:', clicked);
    await new Promise(r => setTimeout(r, 1200));

    await page.screenshot({ path: `${OUT}/settings-ai.png`, fullPage: false });
    console.log('Saved settings-ai.png');

    await browser.close();
    console.log('Done!');
})();
