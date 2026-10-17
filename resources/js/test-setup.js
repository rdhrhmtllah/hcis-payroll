import { config } from "@vue/test-utils";
import { nomorVersi, labelVersi } from "./utils/versi";

/**
 * Cermin dari properti global yang didaftarkan app.js.
 *
 * Komponen di-mount sendirian saat diuji, tanpa melewati app.js, sehingga
 * $vnum/$vlabel tak ada dan template yang memakainya gagal render. Mendaftarkan
 * fungsi YANG SAMA di sini — bukan tiruan — membuat pengujian menguji perilaku
 * yang sebenarnya berjalan; tiruan hanya akan menyembunyikan perbedaannya.
 *
 * Kalau app.js kelak menambah properti global lain, tempatnya di sini juga:
 * satu berkas yang bisa dibandingkan, bukan tebaran mock di tiap berkas uji.
 */
/* config.global.config.globalProperties tak ada di versi @vue/test-utils ini —
   menyetelnya melempar "Cannot set properties of undefined". Yang tersedia
   adalah config.global.mocks, yang disuntikkan ke tiap komponen yang di-mount
   dengan cara yang sama seperti globalProperties di aplikasi. */
config.global.mocks = {
    ...(config.global.mocks || {}),
    $vnum: nomorVersi,
    $vlabel: labelVersi,
};

/**
 * jsdom tidak mengimplementasikan window.matchMedia, sedangkan SETIAP peramban
 * sungguhan punya. Perbedaannya bukan sepele: `useShellState` memanggilnya saat
 * modul dimuat (`useShellState.js:42`, tanpa penjaga), sehingga komponen mana
 * pun yang menyentuh shell akan gagal di-import — gagal yang murni artefak
 * lingkungan uji, bukan cacat kode.
 *
 * Bakunya `matches: false` = layar lebar. Berkas uji yang perlu menguji
 * perilaku ponsel menimpanya sendiri, dan WAJIB mengembalikannya di afterEach:
 * satu window dipakai bersama, dan kebocorannya muncul sebagai kegagalan yang
 * berpindah-pindah di berkas lain.
 */
if (typeof window !== 'undefined' && typeof window.matchMedia !== 'function') {
    window.matchMedia = (query) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener() {},
        removeEventListener() {},
        addListener() {},
        removeListener() {},
        dispatchEvent() { return false; },
    });
}

/**
 * jsdom 25 belum mengimplementasikan Blob.prototype.text(), sedangkan SETIAP
 * peramban sungguhan punya (sejak 2019). Kode yang membaca pesan galat dari
 * respons `responseType: 'blob'` (mis. PermitDigitalPass::pesanGalatBlob)
 * memakainya; tanpa pengganti ini ia diam-diam jatuh ke pesan umum dan ujinya
 * gagal karena lingkungan, bukan karena cacat kode. Dibangun di atas
 * FileReader yang sudah dimiliki jsdom, jadi isinya benar-benar dibaca.
 *
 * URL.createObjectURL sengaja TIDAK dipasang di sini: sebagian uji
 * (HcisFileViewer) justru bergantung pada ketiadaannya. Berkas uji yang
 * membutuhkannya memasang lalu melepasnya sendiri.
 */
if (typeof Blob !== 'undefined' && typeof Blob.prototype.text !== 'function') {
    Blob.prototype.text = function text() {
        return new Promise((resolve, reject) => {
            const pembaca = new FileReader();
            pembaca.onload = () => resolve(String(pembaca.result ?? ''));
            pembaca.onerror = () => reject(pembaca.error);
            pembaca.readAsText(this);
        });
    };
}

/**
 * jsdom tanpa paket `canvas` tidak punya konteks 2D: `getContext('2d')` mencetak
 * "Not implemented" lalu mengembalikan null. Setiap grafik ECharts (HcisChart) lalu
 * melempar `clearRect`/`dpr` dari timer animasinya — ribuan "Unhandled Errors" yang
 * tidak menggagalkan satu uji pun, tetapi membuat vitest keluar dengan kode gagal dan
 * menenggelamkan galat yang sungguhan.
 *
 * Yang dipasang konteks TIRUAN tanpa gambar: setiap metode tidak melakukan apa-apa,
 * properti gaya menyimpan nilainya, dan yang dibaca balik (measureText, gradien,
 * getImageData) memulangkan bentuk yang diharapkan pemanggil. Uji yang perlu kanvas
 * sendiri (mis. kompresGambar) tetap memasang tiruannya sendiri; tiruan ini hanya
 * pengganti null bawaan jsdom. Konteks lain ('webgl', 'bitmaprenderer') tetap null.
 *
 * SEMENTARA HANYA UNTUK UJI PERFORMANCE APPRAISAL. Berkas ini dipakai semua modul, dan
 * dampak tiruan ini pada uji modul lain belum diperiksa satu per satu. Perluas daftarnya
 * sesudah modul itu dijalankan ulang dan dibandingkan dengan baseline-nya.
 */
const BERKAS_UJI = String(globalThis.__vitest_worker__?.filepath || '').replace(/\\/g, '/');
const PAKAI_KANVAS_TIRUAN = ['/Pages/KPI/PerformanceAppraisal/'].some((jalur) => BERKAS_UJI.includes(jalur));

if (PAKAI_KANVAS_TIRUAN && typeof HTMLCanvasElement !== 'undefined') {
    const konteksKanvas = new WeakMap();
    const diam = () => {};
    const bawaan = {
        fillStyle: '#000000',
        strokeStyle: '#000000',
        lineWidth: 1,
        lineCap: 'butt',
        lineJoin: 'miter',
        miterLimit: 10,
        lineDashOffset: 0,
        globalAlpha: 1,
        globalCompositeOperation: 'source-over',
        font: '10px sans-serif',
        textAlign: 'start',
        textBaseline: 'alphabetic',
        direction: 'ltr',
        shadowBlur: 0,
        shadowColor: 'rgba(0, 0, 0, 0)',
        shadowOffsetX: 0,
        shadowOffsetY: 0,
        imageSmoothingEnabled: true,
    };
    const dataGambar = (w, h) => ({ width: w, height: h, data: new Uint8ClampedArray(Math.max(0, (w | 0) * (h | 0) * 4)) });

    const buatKonteks2d = (canvas) => new Proxy({
        ...bawaan,
        canvas,
        measureText: (teks) => ({
            width: String(teks ?? '').length * 6,
            actualBoundingBoxAscent: 8,
            actualBoundingBoxDescent: 2,
            actualBoundingBoxLeft: 0,
            actualBoundingBoxRight: String(teks ?? '').length * 6,
        }),
        createLinearGradient: () => ({ addColorStop: diam }),
        createRadialGradient: () => ({ addColorStop: diam }),
        createConicGradient: () => ({ addColorStop: diam }),
        createPattern: () => ({ setTransform: diam }),
        getImageData: (_x, _y, w, h) => dataGambar(w, h),
        createImageData: (w, h) => dataGambar(w, h),
        getLineDash: () => [],
        getTransform: () => ({ a: 1, b: 0, c: 0, d: 1, e: 0, f: 0 }),
        isPointInPath: () => false,
        isPointInStroke: () => false,
    }, {
        // Metode yang tidak didaftar (fillRect, clearRect, drawImage, …) = tanpa efek.
        get: (isi, kunci) => (kunci in isi ? isi[kunci] : (typeof kunci === 'string' ? diam : undefined)),
        set: (isi, kunci, nilai) => {
            isi[kunci] = nilai;
            return true;
        },
    });

    HTMLCanvasElement.prototype.getContext = function getContext(jenis) {
        if (jenis !== '2d') {
            return null;
        }

        if (!konteksKanvas.has(this)) {
            konteksKanvas.set(this, buatKonteks2d(this));
        }

        return konteksKanvas.get(this);
    };
}
