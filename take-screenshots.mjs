import puppeteer from 'puppeteer-core';
import { existsSync, mkdirSync } from 'fs';

const BASE = 'https://listen.ntfier.com';
const OUT = 'public/docs/screenshots';
const VIEWPORT = { width: 1440, height: 900 };

if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true });

const pages = [
    // Auth
    { name: 'login', path: '/login', noAuth: true },
    { name: 'register', path: '/register', noAuth: true },
    { name: 'forgot-password', path: '/forgot-password', noAuth: true },

    // Dashboard
    { name: 'dashboard', path: '/dashboard' },

    // Users
    { name: 'users', path: '/admin/users' },
    { name: 'user-create', path: '/admin/users/create' },

    // Roles
    { name: 'roles', path: '/admin/roles' },

    // CMS
    { name: 'articles', path: '/admin/articles' },
    { name: 'article-create', path: '/admin/articles/create' },
    { name: 'categories', path: '/admin/categories' },
    { name: 'pages-admin', path: '/admin/pages' },
    { name: 'page-create', path: '/admin/pages/create' },

    // Media
    { name: 'media', path: '/admin/media' },

    // Email
    { name: 'email-templates', path: '/admin/email-templates' },
    { name: 'email-log', path: '/admin/email-logs' },
    { name: 'email-settings', path: '/admin/email-settings' },

    // System
    { name: 'settings', path: '/admin/settings' },
    { name: 'settings-ai', path: '/admin/settings', tab: 'AI' },
    { name: 'activity-log', path: '/admin/activity-logs' },
    { name: 'backups', path: '/admin/backups' },
    { name: 'system-health', path: '/admin/system-health' },
    { name: 'api-tokens', path: '/admin/api-tokens' },
    { name: 'notifications', path: '/admin/notifications' },

    // Profile
    { name: 'profile', path: '/profile' },

    // Public
    { name: 'homepage', path: '/', noAuth: true },
];

(async () => {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium-browser',
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--window-size=1440,900',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport(VIEWPORT);

    // Login first
    console.log('Logging in...');
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2', timeout: 30000 });
    await page.type('input[type="email"], input[name="email"]', 'admin@admin.com');
    await page.type('input[type="password"], input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
    console.log('Logged in! Current URL:', page.url());

    for (const p of pages) {
        try {
            console.log(`Capturing: ${p.name} (${p.path})`);
            await page.goto(`${BASE}${p.path}`, { waitUntil: 'networkidle2', timeout: 30000 });
            // Wait a bit for animations
            await new Promise(r => setTimeout(r, 1500));
            // Optionally activate a specific tab before capturing
            if (p.tab) {
                const clicked = await page.evaluate((label) => {
                    const tab = [...document.querySelectorAll('[role="tab"]')]
                        .find(el => el.textContent.trim() === label);
                    if (tab) { tab.click(); return true; }
                    return false;
                }, p.tab);
                if (!clicked) console.warn(`  WARN: tab "${p.tab}" not found on ${p.name}`);
                await new Promise(r => setTimeout(r, 1000));
            }
            await page.screenshot({
                path: `${OUT}/${p.name}.png`,
                fullPage: false,
            });
            console.log(`  OK: ${p.name}.png`);
        } catch (err) {
            console.error(`  FAIL: ${p.name} - ${err.message}`);
        }
    }

    await browser.close();
    console.log('Done!');
})();
