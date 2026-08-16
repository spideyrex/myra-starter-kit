import { describe, expect, it } from 'vitest';
import { isCacheable, isCacheableRequest } from '@/pwa/register';

const ORIGIN = globalThis.location?.origin ?? 'http://localhost:3000';

function req(path: string, init: { method?: string; headers?: Record<string, string>; origin?: string } = {}) {
    const origin = init.origin ?? ORIGIN;

    return {
        method: init.method ?? 'GET',
        headers: new Headers(init.headers ?? {}),
        url: `${origin}${path}`,
    };
}

describe('the service worker allow-list', () => {
    it('caches hashed build assets and the static shell — and nothing else', () => {
        expect(isCacheableRequest(req('/build/app-abc123.js'))).toBe(true);
        expect(isCacheableRequest(req('/build/assets/app-9f8e.css'))).toBe(true);
        expect(isCacheableRequest(req('/offline.html'))).toBe(true);
        expect(isCacheableRequest(req('/manifest.webmanifest'))).toBe(true);
        expect(isCacheableRequest(req('/favicon.ico'))).toBe(true);
    });

    it.each([
        ['an admin page', req('/admin/dashboard')],
        ['a paged index', req('/admin/users?page=2')],
        ['the widget batch', req('/admin/dashboard/widgets/data', { method: 'POST' })],
        ['the assist endpoint', req('/admin/ai/assist', { method: 'POST' })],
        ['report data', req('/admin/reports/users/data', { method: 'POST' })],
        ['an Inertia visit', req('/admin/dashboard', { headers: { 'X-Inertia': 'true' } })],
        ['an XHR', req('/admin/search', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })],
        ['a JSON request', req('/admin/users', { headers: { Accept: 'application/json' } })],
        ['an SSE request', req('/admin/ai/assist', { headers: { Accept: 'text/event-stream' } })],
        ['any POST', req('/build/app-abc123.js', { method: 'POST' })],
        ['a cross-origin asset', req('/a.js', { origin: 'https://cdn.example.com' })],
        ['anything with a query string', req('/build/app-abc123.js?v=1')],
        ['the root document', req('/')],
        ['the login page', req('/login')],
    ])('refuses %s', (_label, request) => {
        expect(isCacheableRequest(request)).toBe(false);
    });

    it('the (url, request) overload agrees with the request-only form', () => {
        const asset = req('/build/app-abc123.js');
        const page = req('/admin/dashboard');

        expect(isCacheable(new URL(asset.url), asset)).toBe(true);
        expect(isCacheable(new URL(page.url), page)).toBe(false);
    });

    it('an Inertia header defeats an otherwise cacheable asset', () => {
        expect(isCacheableRequest(req('/build/app-abc123.js', { headers: { 'X-Inertia': 'true' } }))).toBe(false);
    });
});
