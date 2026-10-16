import './bootstrap';

// 1. Vendor third-party CSS
import 'floating-vue/dist/style.css';
import '@formkit/themes/genesis';
import 'vue-select/dist/vue-select.css';
import 'element-plus/dist/index.css';

// 2. Element Plus bridge to tame vendor defaults
import '../css/element-plus-bridge.css';

// 3. HCIS Design System tokens & primitives
import '../css/design-system.css';

// 4. Application global styles & fonts
import '../css/app.css';

import { mulaiModeGerak } from './motionMode';
import { createApp, h } from 'vue';

// Dipasang sebelum halaman pertama dirender: kalau atributnya baru menempel
// setelah render, animasi masuk halaman pertama sempat berjalan penuh dulu
// baru dipadamkan — dan kedipan itu justru gerak tambahan.
mulaiModeGerak();
import { createInertiaApp } from '@inertiajs/vue3';
import { nomorVersi, labelVersi } from './Pages/LMS/SOP/Shared/support/versi';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FloatingVue from 'floating-vue';
import { plugin, defaultConfig } from '@formkit/vue';
import ElementPlus from 'element-plus';
import axios from 'axios';
import AppShell from './Layouts/AppShell.vue';
// Refresh CSRF single-flight dipusatkan di utils/csrf agar dipakai bersama
// interceptor axios (di bawah) dan wrapper fetch (di bootstrap.js).
import { CsrfCookieTidakTerbit, refreshCsrfToken } from './utils/csrf';

// Bersihkan SEMUA jejak token CSRF lama dari sebuah request config sebelum
// retry. Laravel memeriksa token dengan urutan: body `_token` -> header
// X-CSRF-TOKEN -> header/cookie X-XSRF-TOKEN. Jadi membuang header saja TIDAK
// cukup kalau komponen ikut menaruh `_token` di body (token meta yang basi) —
// retry tetap 419. Fungsi ini menghapus header stale + field `_token` dari body
// (object / JSON string / FormData / URLSearchParams) supaya axios mengisi ulang
// X-XSRF-TOKEN otomatis dari cookie XSRF-TOKEN yang segar.
function stripStaleCsrf(config) {
    if (!config) return;

    // 1) Header stale.
    if (config.headers) {
        delete config.headers['X-XSRF-TOKEN'];
        delete config.headers['X-CSRF-TOKEN'];
        if (config.headers.common) {
            delete config.headers.common['X-XSRF-TOKEN'];
            delete config.headers.common['X-CSRF-TOKEN'];
        }
    }

    // 2) Field `_token` di body.
    const data = config.data;
    if (!data) return;

    if (typeof FormData !== 'undefined' && data instanceof FormData) {
        if (data.has('_token')) data.delete('_token');
        return;
    }

    if (typeof URLSearchParams !== 'undefined' && data instanceof URLSearchParams) {
        if (data.has('_token')) data.delete('_token');
        return;
    }

    if (typeof data === 'string') {
        // Body sudah ter-serialize jadi JSON string (axios biasanya melakukan ini
        // SETELAH interceptor, tapi jaga-jaga kalau komponen kirim string sendiri).
        try {
            const parsed = JSON.parse(data);
            if (parsed && typeof parsed === 'object' && '_token' in parsed) {
                delete parsed._token;
                config.data = JSON.stringify(parsed);
            }
        } catch (e) {
            // Bukan JSON (mis. form-urlencoded string) — abaikan, biarkan apa adanya.
        }
        return;
    }

    if (typeof data === 'object' && '_token' in data) {
        delete data._token;
    }
}

axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;
        const config = error?.config || {};

        // Permintaan refresh CSRF itu sendiri tidak boleh memicu refresh lagi.
        const isRefreshRequest = config.skipAuthRefresh === true;

        // CATATAN secure-pdf: viewer menangani sendiri 401/403 (nonce replay /
        // signature expired) lewat re-handshake. TAPI 419 = CSRF token mismatch,
        // yaitu cookie XSRF-TOKEN basi setelah session regenerate — ini masalah
        // SESI BROWSER, bukan nonce. Re-handshake viewer TIDAK menolong karena
        // POST handshake-nya kena 419 yang sama. Jadi 419 tetap harus di-refresh
        // + retry di sini (termasuk untuk secure-pdf), kalau tidak viewer mentok
        // "Akses Ditolak" selamanya.
        if (status === 419 && !isRefreshRequest && !config._csrfRetried) {
            // 419 = CSRF token mismatch (mis. session di-regenerate saat login).
            // Daripada reload halaman (yang membuang aksi user & memaksa klik ulang),
            // segarkan token CSRF secara silent lalu ULANGI request asli sekali.
            // User tidak melihat error apa pun; approve/reject langsung berhasil.
            try {
                await refreshCsrfToken();

                config._csrfRetried = true; // tandai agar tidak retry berulang

                // PENTING: jangan set token manual. config asli masih membawa
                // header X-XSRF-TOKEN / X-CSRF-TOKEN token LAMA. Hapus keduanya
                // agar axios mengisi ulang X-XSRF-TOKEN otomatis dari cookie
                // XSRF-TOKEN yang baru (sesuai xsrfCookieName/xsrfHeaderName).
                // Menimpa manual dengan nilai cookie mentah justru bisa salah
                // format -> tetap 419.
                stripStaleCsrf(config);

                return axios(config);
            } catch (refreshError) {
                // Cookie CSRF tidak bisa terpasang sama sekali: melempar ke
                // /login TIDAK menolong, karena orangnya masih login dan akan
                // langsung dipantulkan ke beranda. Itulah persis gejala yang
                // dilaporkan — "menekan pratinjau lalu tiba-tiba kembali ke
                // homepage" — dan ia menyembunyikan sebabnya alih-alih
                // menunjukkannya.
                if (refreshError instanceof CsrfCookieTidakTerbit) {
                    return Promise.reject(
                        Object.assign(error, {
                            pesanRamah:
                                'Sesi keamanan tidak dapat diperbarui karena browser menolak menyimpan cookie. ' +
                                'Muat ulang halaman; bila tetap gagal, periksa pengaturan cookie untuk situs ini.',
                        }),
                    );
                }

                // Refresh gagal karena sebab lain (jaringan/sesi mati) -> login.
                window.location.assign('/login');
                return new Promise(() => {});
            }
        }

        /*
         * 419 KEDUA = SESI SUDAH MATI, BUKAN TOKEN BASI.
         *
         * Cabang di atas hanya melempar ke login bila permintaan refresh-nya
         * SENDIRI gagal. Padahal /sanctum/csrf-cookie membalas 204 dengan
         * riang untuk sesi anonim: ia hanya mengirim ulang token sesi yang
         * sekarang, tidak memeriksa siapa pemiliknya. Jadi ketika sesi
         * kedaluwarsa, urutannya jadi refresh BERHASIL → ulang → 419 lagi →
         * ditolak apa adanya, dan komponen menampilkan pesan mentah Laravel
         * "CSRF token mismatch." kepada orang yang sebenarnya cuma perlu
         * masuk kembali.
         *
         * Terlihat 10 Sep 2026: satu halaman menampilkan 419 pada handshake
         * aktivitas DAN pada konfirmasi baca SOP, berbarengan dengan 401 pada
         * /api/v1/activities — 401 itulah yang membuktikan sesinya memang
         * sudah tak dikenali, bukan tokennya yang tertinggal.
         *
         * Sesudah token disegarkan sekali dan masih 419, tak ada lagi yang
         * bisa diselamatkan di halaman ini.
         */
        if (status === 419 && !isRefreshRequest && config._csrfRetried) {
            window.location.assign('/login');
            return new Promise(() => {});
        }

        return Promise.reject(error);
    },
);

import { HcisSwitch, hcisMessage } from './components/ui';

// Bridge Element Plus ElMessage to hcisMessage globally
const patchedElMessage = function (options) {
    return hcisMessage(options);
};
patchedElMessage.success = (msg, titleOrOpts) => hcisMessage.success(msg, titleOrOpts);
patchedElMessage.error = (msg, titleOrOpts) => hcisMessage.error(msg, titleOrOpts);
patchedElMessage.warning = (msg, titleOrOpts) => hcisMessage.warning(msg, titleOrOpts);
patchedElMessage.info = (msg, titleOrOpts) => hcisMessage.info(msg, titleOrOpts);
patchedElMessage.closeAll = () => hcisMessage.closeAll();

if (typeof window !== 'undefined') {
    window.ElMessage = patchedElMessage;
}

createInertiaApp({
    progress: {
        color: '#4f46e5',
        delay: 150,
        includeCSS: true,
        showSpinner: false,
    },
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        if (typeof page.default.layout === 'undefined') {
            page.default.layout = AppShell;
        }

        return page;
    },
    setup({ el, App, props, plugin: inertiaPlugin }) {
        const vueApp = createApp({ render: () => h(App, props) });
        vueApp.config.globalProperties.$message = hcisMessage;

        /* Nomor versi SOP: dua digit, mulai dari nol. Didaftarkan global
           supaya keenam belas layar yang menampilkannya memakai satu aturan
           yang sama — bukan enam belas salinan padStart yang pasti menyimpang.
           Lihat Pages/LMS/SOP/Shared/support/versi.js untuk alasan nomornya tetap INT. */
        vueApp.config.globalProperties.$vnum = nomorVersi;
        vueApp.config.globalProperties.$vlabel = labelVersi;

        // Register HcisSwitch globally
        vueApp.component('HcisSwitch', HcisSwitch);

        vueApp
            .use(inertiaPlugin)
            .use(FloatingVue)
            .use(plugin, defaultConfig)
            .use(ElementPlus, { zIndex: 3000 })
            .mount(el);
    },
});
