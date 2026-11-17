/* CodyChat PWA worker: website data remains network-only; public static assets are updated network-first. */
'use strict';
const CACHE_PREFIX = 'codychat-static-';
const CACHE_NAME = CACHE_PREFIX + 'v3';
const STATIC_DESTINATIONS = new Set(['style', 'script', 'font', 'image']);
const PRIVATE_PATH = /\/(?:system|control|addons|avatar|cover|upload|room_icon|gift)\//i;

self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.map(key => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME ? caches.delete(key) : Promise.resolve(false)));
        await self.clients.claim();
    })());
});
self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (request.mode === 'navigate' || /\.php$/i.test(url.pathname) || PRIVATE_PATH.test(url.pathname)) {
        event.respondWith(fetch(request));
        return;
    }
    if (!STATIC_DESTINATIONS.has(request.destination)) return;
    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        try {
            const response = await fetch(request);
            if (response && response.ok && response.type === 'basic') {
                const keys = await cache.keys();
                await Promise.all(keys.map(key => {
                    const oldUrl = new URL(key.url);
                    return oldUrl.pathname === url.pathname && oldUrl.href !== url.href ? cache.delete(key) : Promise.resolve(false);
                }));
                await cache.put(request, response.clone());
            }
            return response;
        } catch (error) {
            const cached = await cache.match(request);
            if (cached) return cached;
            throw error;
        }
    })());
});
