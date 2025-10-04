if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
        .then(() => console.log("✅ Service Worker Registered"))
        .catch((err) => console.error("❌ SW registration failed:", err));
}
window.addEventListener('load', () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const { latitude, longitude } = pos.coords;
            window.location = `/index.php?lat=${latitude}&lng=${longitude}`;
        }, err => {
            alert('Gagal mendapatkan lokasi');
        });
    } else alert('Geolocation tidak didukung');
});

