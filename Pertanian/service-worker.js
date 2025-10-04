const CACHE_NAME = 'reco-cache-v2';
const FILES_TO_CACHE = [
    '/',
    '/index.php',
    '/offline.html',
    '/css/style.css',
    '/js/main.js',
    '/user/dashboard.php',
    '/user/tanaman.php',
    '/user/riwayat.php',
    '/user/profil.php',
    '/user/hasil.php',
    '/auth/login.php',
];

// Cache saat install Service Worker
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[Service Worker] Pre-caching offline assets');
                // Menambahkan semua file yang ditentukan ke cache
                return cache.addAll(FILES_TO_CACHE);
            })
            .catch((error) => {
                // Tangani error jika ada file yang gagal di-cache (misalnya path salah)
                console.error('[Service Worker] Pre-caching failed:', error);
                return Promise.reject(error); // Tolak promise untuk mengindikasikan kegagalan instalasi
            })
    );
    self.skipWaiting(); // Mengaktifkan Service Worker langsung setelah instalasi selesai
});

// Hapus cache lama saat Service Worker diaktifkan
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys().then((keyList) => {
            return Promise.all(
                keyList.map((key) => {
                    // Hapus cache yang namanya tidak sama dengan CACHE_NAME saat ini
                    if (key !== CACHE_NAME) {
                        console.log('[Service Worker] Removing old cache:', key);
                        return caches.delete(key);
                    }
                })
            );
        })
    );
    self.clients.claim(); // Memastikan Service Worker mengambil kendali atas semua klien yang terbuka
});

// Logika Fetch: Mengintersep permintaan jaringan
self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);

    // Prioritas: Lewati permintaan ke domain lain (misalnya CDN, API eksternal)
    // dan juga skrip PHP di folder `/lib/` karena mereka adalah server-side API.
    // Service Worker tidak bisa mengintersep atau meng-cache mereka secara langsung tanpa penanganan khusus (CORS).
    if (requestUrl.origin !== location.origin || requestUrl.pathname.startsWith('/lib/')) {
        return;
    }

    // Strategi untuk aset non-navigasi (CSS, JS, gambar, dll): Cache-First
    // Coba ambil dari cache terlebih dahulu, jika tidak ada, baru dari jaringan.
    if (event.request.mode !== 'navigate') {
        event.respondWith(
            caches.match(event.request)
                .then((response) => {
                    // Jika ada di cache, langsung kembalikan respon dari cache
                    if (response) {
                        return response;
                    }
                    // Jika tidak ada di cache, coba ambil dari jaringan
                    return fetch(event.request);
                })
                .catch((error) => {
                    // Tangani error jika fetching aset statis gagal (misalnya offline dan tidak di cache)
                    console.error('[Service Worker] Fetching static asset failed:', error);
                    // Anda bisa mengembalikan placeholder di sini jika itu gambar, dll.
                    // Untuk saat ini, biarkan browser menangani error jika tidak ada cache/jaringan.
                    throw error; // Melemparkan error agar browser bisa menampilkannya (opsional)
                })
        );
        return;
    }

    // Strategi untuk permintaan navigasi (permintaan halaman HTML): Cache-First, lalu Network, dengan Fallback Offline
    // Coba ambil halaman dari cache. Jika tidak ada, coba ambil dari jaringan.
    // Jika jaringan juga gagal (saat offline), kembalikan halaman `offline.html`.
    event.respondWith(
        caches.match(event.request)
            .then(function (response) {
                // Jika halaman ada di cache, langsung kembalikan dari cache
                if (response) {
                    return response;
                }
                // Jika tidak ada di cache, coba ambil dari jaringan
                return fetch(event.request).catch(function() {
                    // Jika jaringan juga gagal (offline), kembalikan halaman offline.html
                    console.log('[Service Worker] Fetch failed, serving offline.html for navigation.');
                    return caches.match('offline.html');
                });
            })
            .catch((error) => {
                // Penanganan error tingkat tinggi jika ada masalah pada proses fetch
                console.error('[Service Worker] Navigation fetch failed:', error);
                // Sebagai fallback terakhir, selalu coba kembalikan offline.html
                return caches.match('offline.html');
            })
    );
});