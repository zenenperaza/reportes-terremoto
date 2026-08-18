const CACHE_VERSION = "asonacop-pwa-v35";
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const OFFLINE_URL = "/offline.html";

const APP_SHELL = [
    OFFLINE_URL,
    "/icons/asonacop-app.png",
    "/favicon.ico",
    "/css/app.css",
    "/css/navigation-fixes.css",
    "/css/geolocation.css",
    "/css/report-form-fixes.css",
    "/css/beneficiary-records.css",
    "/css/recurrence-alert.css",
    "/css/beneficiary-immediate.css",
    "/css/beneficiary-entry.css",
    "/css/user-management.css",
    "/css/beneficiary-summary.css",
    "/css/donor-report.css",
    "/css/pwa.css",
    "/css/catalog-management.css",
    "/css/select2-custom.css",
    "/css/indicator-select2.css",
    "/js/pwa.js",
    "/vendor/select2/css/select2.min.css",
    "/vendor/select2/js/jquery.min.js",
    "/vendor/select2/js/select2.full.min.js",
    "/vendor/bootstrap/css/bootstrap.min.css",
    "/vendor/bootstrap/js/bootstrap.bundle.min.js"
];

self.addEventListener("install", event => {
    event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.addAll(APP_SHELL)));
    self.skipWaiting();
});

self.addEventListener("activate", event => {
    event.waitUntil(
        caches
            .keys()
            .then(keys => Promise.all(keys.filter(key => key.startsWith("asonacop-pwa-") && key !== STATIC_CACHE).map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", event => {
    const { request } = event;

    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Never cache authenticated HTML, API responses, exports or beneficiary data.
    if (request.mode === "navigate") {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (!APP_SHELL.includes(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(request).then(cached => {
            if (cached) {
                return cached;
            }

            return fetch(request).then(response => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then(cache => cache.put(request, copy));
                }

                return response;
            });
        })
    );
});
