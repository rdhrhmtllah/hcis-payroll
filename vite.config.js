import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import vue from "@vitejs/plugin-vue";
export default defineConfig(({ mode }) => {
    /*
     * laravel-vite-plugin menulis isi public/hot dari server.hmr
     * (resolveDevServerUrl: host = hmr.host ?? server.host, port =
     * hmr.clientPort ?? port aktual), BUKAN dari alamat yang benar-benar
     * didengarkan. Selama tunnel di-hardcode di sini, setiap mesin yang
     * menjalankan `npm run dev` — termasuk laptop lokal — mengumumkan domain
     * tunnel sebagai asal aset, sehingga halaman lokal memuat modul dev
     * lintas-origin ke domain yang tidak melayaninya: CORS ditolak, lalu 504.
     *
     * Karena itu blok server digerakkan .env, yang tidak ikut ter-commit:
     *   VPS   : VITE_DEV_TUNNEL_HOST=server-dev-noirxevo.ajt.my.id
     *   Lokal : biarkan kosong (opsional VITE_DEV_HOST untuk akses LAN)
     */
    const env = loadEnv(mode, process.cwd(), "");
    const tunnelHost = (env.VITE_DEV_TUNNEL_HOST || "").trim();
    const localHost = (env.VITE_DEV_HOST || "localhost").trim();
    const localPort = Number(env.VITE_DEV_PORT || 5173);

    /*
     * strictPort WAJIB, dan bukan soal kerapian.
     *
     * public/hot ditulis dari hmr.clientPort yang dipatri ke localPort. Tanpa
     * strictPort, Vite diam-diam bergeser ke 5174 saat 5173 sudah dipakai —
     * TAPI tetap mengumumkan 5173. Halaman lalu memuat modul dari dev server
     * worktree LAIN: pohon sumber lain, cache dependensi lain. Gejalanya
     * "504 Outdated Optimize Dep" dan "Failed to fetch dynamically imported
     * module", yang menunjuk ke berkas halaman dan membuat orang mencari
     * kesalahan di kodenya.
     *
     * Dengan strictPort, tabrakan port gagal NYARING saat start ("Port 5173 is
     * already in use") alih-alih menyajikan berkas milik orang lain diam-diam.
     * Tiap worktree menyetel VITE_DEV_PORT sendiri di .env-nya.
     */
    const server = tunnelHost
        ? {
              host: "0.0.0.0",
              port: localPort,
              strictPort: true,
              allowedHosts: [tunnelHost],
              hmr: {
                  host: tunnelHost,
                  protocol: "wss",
                  clientPort: 443,
                  path: "/__vite_hmr",
              },
          }
        : {
              host: localHost,
              port: localPort,
              strictPort: true,
              hmr: {
                  host: localHost,
                  protocol: "ws",
                  clientPort: localPort,
              },
          };

    return {
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/design-system.css",
                "resources/css/element-plus-bridge.css",
                "resources/css/shell.css",
                "resources/js/app.js",
                "resources/js/shell.js",
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
                compilerOptions: {
                    /* <lord-icon> adalah custom element dari pustaka Lordicon,
                       bukan komponen Vue. Tanpa baris ini Vue mencoba
                       me-resolve-nya sebagai komponen, gagal, lalu mencetak
                       "Failed to resolve component" di SEBELAS halaman —
                       kebisingan yang menutupi error sungguhan di konsol. */
                    isCustomElement: (tag) => tag === 'lord-icon',
                },
            },
        }),
    ],
    server,
    resolve: {
        alias: {
            // Memaksa versi Vue yang membawa compiler template.
            vue: "vue/dist/vue.esm-bundler.js",

            /*
             * "@" sempat menunjuk "./src" — konvensi proyek Vue SPA yang tidak
             * pernah ada di repo Laravel ini; kodenya hidup di resources/js.
             * Kesalahan itu tidak pernah ketahuan karena tak satu pun berkas
             * nyata memakai "@/": semuanya impor relatif. Yang memakainya cuma
             * teks dokumentasi — termasuk tombol "Salin Import" di
             * /design-system, sehingga potongan yang dibagikan design system
             * justru satu-satunya yang tidak bisa diselesaikan Vite.
             *
             * Sejak komponen bersama dipindahkan keluar dari Pages/, alias ini
             * juga menjadi cara baku merujuknya: kedalaman folder tidak lagi
             * berpengaruh, sehingga memindahkan sebuah page tidak memutus
             * impornya.
             */
            "@": path.resolve(__dirname, "resources/js"),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes("node_modules")) {
                        return;
                    }

                    if (id.includes("@fullcalendar")) return "vendor-calendar";
                    if (id.includes("exceljs")) return "vendor-excel";
                    if (id.includes("apexcharts") || id.includes("chart.js")) return "vendor-charts";
                    if (id.includes("datatables.net") || id.includes("jquery")) return "vendor-tables";
                    if (id.includes("primevue") || id.includes("@primeuix") || id.includes("element-plus")) return "vendor-ui";
                    if (id.includes("@formkit") || id.includes("vee-validate") || id.includes("yup")) return "vendor-forms";
                    if (id.includes("quill") || id.includes("marked") || id.includes("turndown")) return "vendor-editor";
                    if (id.includes("vue") || id.includes("@inertiajs")) return "vendor-vue";

                    return "vendor";
                },
            },
        },
    },
};
});
