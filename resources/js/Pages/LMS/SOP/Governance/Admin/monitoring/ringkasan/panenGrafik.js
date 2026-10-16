import { createApp, h } from 'vue';
import { HcisChart } from '@/components/ui';

/**
 * Memanen grafik layar menjadi gambar untuk laporan cetak.
 *
 * CARANYA, DAN KENAPA BEGITU. Grafik tidak digambar ulang untuk kertas — yang
 * di-mount di sini adalah `HcisChart` yang SAMA PERSIS dengan yang dipakai
 * panel-panel di layar, hanya pada ukuran halaman A4 dan di luar bidang
 * pandang. Sesudah ECharts selesai melukis, kanvasnya dipanen jadi PNG.
 *
 * Alternatifnya — menggambar ulang batang dan sumbu dengan HTML — itulah yang
 * dipakai sebelum ini, dan akibatnya laporan punya penggambaran KEDUA yang
 * harus dijaga sejalan dengan yang di layar. Ia tak pernah dijaga: begitu chart
 * layar berubah, kertasnya diam-diam tertinggal, dan tak ada yang menyadarinya
 * sampai ada yang membandingkan keduanya berdampingan.
 *
 * LEBARNYA BUKAN LEBAR LAYAR. A4 potret dengan margin 15 mm menyisakan ±180 mm
 * area cetak; pada 96 dpi itu ±680 px. Dilukis pada 2× lalu dicetak pada lebar
 * itu, hasilnya tetap tajam di kertas — grafik yang dipanen seukuran layar akan
 * tampak kabur atau hurufnya raksasa saat diperkecil ke kolom cetak.
 */

/** Lebar lukis (px). Dua kali area cetak A4 supaya tajam di kertas. */
const LEBAR = 1360;

/** Ambang menyerah. Grafik yang tak juga selesai tak boleh menahan laporannya. */
const TENGGAT_MS = 8000;

/**
 * Mount satu HcisChart di luar layar dan kembalikan PNG-nya sebagai data URI.
 *
 * @param {object} props  Props HcisChart — sama dengan yang dipakai panelnya.
 * @param {number} tinggi Tinggi lukis dalam px.
 * @returns {Promise<string|null>} data URI, atau null bila gagal/kosong.
 */
export async function panenSatu(props, tinggi = 420) {
    /* Wadahnya HARUS punya ukuran nyata. ECharts mengukur dirinya dari
       elemen induk; `display:none` membuatnya berukuran 0×0 dan hasil panennya
       gambar kosong — kegagalan yang tak melempar galat apa pun. Karena itu ia
       disembunyikan dengan dipindahkan keluar layar, bukan dengan dimatikan. */
    const wadah = document.createElement('div');
    wadah.style.position = 'fixed';
    wadah.style.left = '-20000px';
    wadah.style.top = '0';
    wadah.style.width = `${LEBAR}px`;
    wadah.style.height = `${tinggi}px`;
    wadah.style.background = '#ffffff';
    wadah.setAttribute('aria-hidden', 'true');
    document.body.appendChild(wadah);

    let app = null;
    let chartRef = null;

    try {
        app = createApp({
            render: () => h(HcisChart, {
                ...props,
                /* Kerangka kartunya dilepas: laporan punya kepala babnya
                   sendiri, dan dua judul bertumpuk pada satu grafik adalah
                   pengulangan yang memakan tinggi halaman. */
                card: false,
                height: tinggi,
                loading: false,
                allowExport: false,
                allowTableToggle: false,
                /* ANIMASI DIMATIKAN LEWAT OPSI, bukan lewat prop — HcisChart
                   tak punya prop animasi, dan `options` digabung DALAM sehingga
                   satu kunci ini tak menelan opsi bawaan panelnya.

                   Wajib: grafik beranimasi yang dipanen di tengah jalan
                   menghasilkan batang setengah tinggi dan garis terpotong —
                   gambar yang tampak "hampir benar", jenis kesalahan yang
                   paling lama tak ketahuan. */
                options: { ...(props.options || {}), animation: false },
                ref: (el) => { chartRef = el; },
            }),
        });
        app.mount(wadah);

        const url = await tungguLalasPanen(() => chartRef, tinggi);

        return url;
    } catch {
        return null;
    } finally {
        try {
            app?.unmount();
        } catch {
            /* Pembongkaran yang gagal tak boleh menelan hasil panennya. */
        }
        wadah.remove();
    }
}

/**
 * Tunggu sampai ECharts benar-benar selesai melukis, lalu panen.
 *
 * `nextTick` saja tidak cukup: instansinya dibuat di `onMounted` dan animasi
 * masuk masih berjalan sesudahnya. Yang ditunggu adalah instansinya ADA dan
 * satu bingkai lewat — bukan tenggat tetap, yang akan terlalu cepat pada mesin
 * lambat dan sia-sia lama pada mesin cepat.
 */
function tungguLalasPanen(ambilRef, tinggi) {
    return new Promise((resolve) => {
        const mulai = Date.now();

        const coba = () => {
            const inst = ambilRef()?.getInstance?.();

            if (inst) {
                /* Satu bingkai lagi supaya lukisan terakhir masuk kanvas. */
                requestAnimationFrame(() => {
                    try {
                        resolve(inst.getDataURL({
                            type: 'png',
                            pixelRatio: 2,
                            backgroundColor: '#ffffff',
                        }));
                    } catch {
                        resolve(null);
                    }
                });

                return;
            }

            if (Date.now() - mulai > TENGGAT_MS) {
                resolve(null);

                return;
            }

            requestAnimationFrame(coba);
        };

        /* Tinggi ikut dibaca supaya wadah sempat menerapkan gayanya sebelum
           ECharts mengukur diri. */
        void tinggi;
        requestAnimationFrame(coba);
    });
}

/**
 * Panen sekumpulan grafik sekaligus.
 *
 * BERURUTAN, BUKAN PARALEL. Setiap panen memasang kanvas seukuran halaman;
 * membuka lima sekaligus membuat peramban mengukur lima kanvas besar dalam satu
 * bingkai, dan pada mesin yang tak kuat sebagiannya terpanen sebelum selesai
 * dilukis — separuh grafik keluar kosong tanpa satu pun galat.
 *
 * Yang gagal dikembalikan sebagai null, bukan melempar: satu grafik bermasalah
 * tak boleh membatalkan seluruh laporan. Perakitnya yang memutuskan apa yang
 * ditulis di tempat gambar yang tak jadi.
 *
 * @param {Array<{kunci: string, props: object, tinggi?: number}>} daftar
 * @returns {Promise<Record<string, string|null>>}
 */
export async function panenBanyak(daftar) {
    const hasil = {};

    for (const g of daftar) {
        // eslint-disable-next-line no-await-in-loop
        hasil[g.kunci] = await panenSatu(g.props, g.tinggi || 420);
    }

    return hasil;
}
