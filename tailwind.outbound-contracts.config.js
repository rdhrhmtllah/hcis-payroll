/**
 * Konfigurasi Tailwind KHUSUS halaman viewer kontrak integrasi outbound (publik).
 *
 * Namanya sengaja berimbuhan: repo ini tidak memakai Tailwind secara umum, dan
 * sebuah `tailwind.config.js` polos di akar akan terbaca seolah seluruh proyek
 * ikut. Yang memakainya hanya berkas di `content` di bawah.
 *
 * `theme.extend` di sini adalah salinan persis blok `tailwind.config` yang dulu
 * ditulis inline di kedua Blade, DENGAN SATU PERUBAHAN: keluarga huruf beralih
 * dari Plus Jakarta Sans/JetBrains Mono (CDN pihak ketiga) ke Nunito yang
 * di-host sendiri — huruf resmi HCIS — dan tumpukan monospace bawaan sistem.
 * Tidak ada berkas huruf baru yang perlu diunduh.
 *
 * Membangun ulang (jalankan dari akar repo):
 *   npx --yes tailwindcss@3.4.17 \
 *     -c tailwind.outbound-contracts.config.js \
 *     -i resources/css/outbound-contracts.css \
 *     -o public/vendor/tailwind/outbound-contracts.css --minify
 *
 * WAJIB dijalankan ulang setiap kali kelas utilitas di kedua Blade berubah:
 * kelas yang tidak terpindai tidak ikut terbangun, dan gejalanya adalah gaya
 * yang diam-diam hilang di satu bagian halaman, bukan galat.
 */
module.exports = {
    // HANYA viewer publik. Portal manajemennya sudah jadi halaman Inertia di
    // dalam shell (resources/js/Pages/SYSTEM/Integration/OutboundContracts/)
    // dan memakai kit HCIS, bukan Tailwind.
    content: [
        './resources/views/SYSTEM/Integration/outbound-contract-viewer.blade.php',
        './resources/views/SYSTEM/Integration/outbound-contract-tautan-tidak-sah.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Nunito', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'Liberation Mono', 'monospace'],
            },
            colors: {
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    900: '#312e81',
                },
            },
        },
    },
    plugins: [],
};
