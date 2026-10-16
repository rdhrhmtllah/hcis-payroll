import path from 'path';
import { fileURLToPath } from 'url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

const dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    // Diperlukan agar berkas .vue bisa diuji; test JS polos tidak terpengaruh.
    plugins: [
        vue({
            // Harus sama dengan vite.config.js: tanpa ini komponen yang memakai
            // <lord-icon> berperilaku berbeda saat diuji dan saat dibangun.
            template: { compilerOptions: { isCustomElement: (tag) => tag === 'lord-icon' } },
        }),
    ],
    resolve: {
        alias: {
            // Harus sama dengan alias di vite.config.js. Vitest tidak membaca
            // vite.config.js milik project ini, jadi tanpa baris ini setiap
            // impor "@/..." gagal di-resolve saat test — dan gagalnya terbaca
            // seperti berkasnya tidak ada, bukan seperti alias yang hilang.
            '@': path.resolve(dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        // Penjaga CI di .github/scripts ikut diuji di sini. Penjaga tanpa uji
        // adalah penjaga yang bisa berhenti bekerja tanpa ada yang tahu, dan
        // kegagalannya selalu berbentuk "lolos", bukan "merah".
        include: ['resources/js/**/*.test.js', '.github/scripts/**/*.test.mjs'],
        // Mendaftarkan properti global yang sama dengan app.js. Tanpa ini,
        // komponen yang memakai $vnum/$vlabel gagal render saat di-mount
        // sendirian — dan gagalnya terbaca seperti komponennya yang rusak.
        setupFiles: ['resources/js/test-setup.js'],
        restoreMocks: true,
        server: {
            deps: {
                // element-plus menarik @popperjs/core yang masih CommonJS;
                // di-inline supaya named export-nya bisa diselesaikan.
                inline: ['element-plus'],
            },
        },
    },
});
