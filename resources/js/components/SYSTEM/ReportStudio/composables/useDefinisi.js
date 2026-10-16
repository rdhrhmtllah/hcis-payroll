import { computed, reactive, ref } from 'vue';

/**
 * Menyimpan definisi laporan yang sedang dirakit.
 *
 * PILIHAN KEDUA MODE DISIMPAN SEKALIGUS. Berpindah Ringkasan↔Rinci tidak boleh
 * membuang pilihan mode satunya: orang yang salah memilih di awal tak pantas
 * dihukum dengan merakit ulang dari nol.
 */
/**
 * Memastikan baris filter sudah lengkap sebelum diterapkan ke kueri atau disimpan.
 */
export function apakahSaringanLengkap(s) {
    if (!s || !s.field || !s.op) return false;

    // Nilai array (antara / salah_satu)
    if (Array.isArray(s.nilai)) {
        if (s.nilai.length === 0) return false;
        if (s.op === 'antara') {
            const [dari, sampai] = s.nilai;
            return dari !== undefined && dari !== null && dari !== '' &&
                   sampai !== undefined && sampai !== null && sampai !== '';
        }
        return s.nilai.some((v) => v !== undefined && v !== null && v !== '');
    }

    // Nilai string/angka
    if (s.nilai === undefined || s.nilai === null) return false;
    if (typeof s.nilai === 'string' && s.nilai.trim() === '') return false;

    return true;
}

export function useDefinisi(datasetKey = 'kepatuhan') {
    const mode = ref('ringkasan');

    const pilihan = reactive({
        dimensi: [],
        ukuran: [],
        /**
         * Agregat racikan: [{ kolom, fungsi, label }].
         *
         * Pengguna memilih PASANGAN kolom + fungsi; ekspresi SQL-nya tetap
         * dirakit server. Itu yang membuat kebebasan ini tidak berubah menjadi
         * angka yang salah tapi tampak benar — lihat AgregatRacikan di sisi PHP.
         */
        agregat: [],
        kolom: [],
        saringan: [],
        urut: [],
        opsi: { subtotal: false, grand_total: false },
    });

    const definisi = computed(() => ({
        versi: 1,
        dataset: datasetKey,
        mode: mode.value,
        dimensi: mode.value === 'ringkasan' ? [...pilihan.dimensi] : [],
        ukuran: mode.value === 'ringkasan' ? [...pilihan.ukuran] : [],
        // Hanya yang sudah lengkap yang dikirim: baris perakit yang baru
        // ditambahkan tetapi belum dipilih kolomnya bukan kesalahan pengguna,
        // ia baru saja menekan tombol tambah.
        agregat: mode.value === 'ringkasan'
            ? pilihan.agregat.filter((a) => a && a.kolom && a.fungsi)
            : [],
        kolom: mode.value === 'rinci' ? [...pilihan.kolom] : [],
        saringan: pilihan.saringan.filter(apakahSaringanLengkap),
        urut: [...pilihan.urut],
        opsi: { ...pilihan.opsi },
        bagan: null,
        format_bawaan: 'xlsx',
    }));

    /**
     * Cermin sisi-klien dari DefinisiValidator, bukan penggantinya.
     *
     * Server tetap memeriksa ulang setiap definisi — permintaan bisa datang
     * tanpa melewati layar ini sama sekali. Yang dikerjakan di sini hanya
     * memberi tahu lebih awal supaya orang tidak menunggu satu putaran jaringan
     * untuk tahu bahwa ukurannya belum dipilih.
     */
    const galat = computed(() => {
        const g = {};
        const agregatSah = pilihan.agregat.filter((a) => a && a.kolom && a.fungsi);

        // Agregat racikan MENGHITUNG sebagai ukuran: ringkasan yang hanya
        // berisi racikan tetap ringkasan yang sah, dan menolaknya memaksa orang
        // memilih ukuran bawaan yang tidak ia inginkan.
        if (mode.value === 'ringkasan' && pilihan.ukuran.length === 0 && agregatSah.length === 0) {
            g.ukuran = 'Mode ringkasan membutuhkan minimal satu ukuran atau agregat.';
        }
        if (mode.value === 'rinci' && pilihan.kolom.length === 0) {
            g.kolom = 'Mode rinci membutuhkan minimal satu kolom.';
        }
        if (pilihan.dimensi.length > 6) {
            g.dimensi = 'Maksimal 6 dimensi.';
        }
        if (pilihan.ukuran.length + agregatSah.length > 12) {
            g.ukuran = 'Maksimal 12 ukuran (termasuk agregat racikan).';
        }

        // Pasangan kembar tidak menghasilkan kolom kedua; menyebutnya lebih baik
        // daripada melipatnya diam-diam, karena pemakainya memang mengira baru
        // saja menambah sesuatu.
        const kunci = agregatSah.map((a) => `${a.fungsi}:${a.kolom}`);
        if (new Set(kunci).size !== kunci.length) {
            g.agregat = 'Ada agregat yang sama ditambahkan dua kali.';
        }
        return g;
    });

    const valid = computed(() => Object.keys(galat.value).length === 0);

    /** Menggeser satu kunci di dalam daftar. Urutan dimensi = urutan GROUP BY. */
    function geser(daftar, kunci, arah) {
        const i = daftar.indexOf(kunci);
        const j = i + arah;
        if (i < 0 || j < 0 || j >= daftar.length) return;
        [daftar[i], daftar[j]] = [daftar[j], daftar[i]];
    }

    return { mode, pilihan, definisi, galat, valid, geser };
}
