import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * Gerbang kemunculan modal pembaruan data.
 *
 * Keputusannya diambil SERVER dan datang lewat prop `tampilkanModal`;
 * penandanya tersimpan di N_HRIS_Pengumpulan_Data_Status.
 *
 * Sebelumnya penanda itu dititipkan ke sessionStorage/localStorage. Itu keliru:
 * keadaan yang hidup di browser tak terlihat dan tak bisa diurus siapa pun —
 * menghapus baris status tidak benar-benar mereset orangnya, dan admin tak
 * punya cara memaksa modal muncul lagi. Berkas ini sengaja tidak lagi menyentuh
 * penyimpanan browser sama sekali.
 */

/**
 * Halaman formulirnya sendiri — tujuan yang diajak oleh modal dan bar.
 *
 * Dipisah sebagai tetapan supaya kalau rutenya pindah, yang berubah satu baris,
 * bukan dua pemeriksaan yang gampang tertinggal salah satunya.
 */
const AWALAN_HALAMAN_FORMULIR = '/pengumpulan-data';

/**
 * Ambil jalur murni dari url Inertia: buang asal (kalau ada), query, dan
 * fragmen. Perbandingannya harus atas jalur, bukan atas teks apa adanya —
 * '/pengumpulan-data?dari=modal' adalah halaman yang sama.
 */
function jalurDari(url) {
    const teks = String(url ?? '');
    const tanpaAsal = teks.replace(/^[a-z][a-z0-9+.-]*:\/\/[^/]+/i, '');

    return tanpaAsal.split(/[?#]/)[0] || '/';
}

export function usePengumpulanDataGate() {
    const page = usePage();
    const ditutupDiLayarIni = ref(false);

    const data = computed(() => page.props.pengumpulanData ?? null);

    /**
     * Sedang berada DI halaman formulir.
     *
     * Pencocokannya berjangkar di awal jalur dan menuntut batas segmen, supaya
     * '/admin/pengumpulan-data' (layar HR) tidak ikut terbebaskan.
     */
    const diHalamanFormulir = computed(() => {
        const jalur = jalurDari(page.url);

        return jalur === AWALAN_HALAMAN_FORMULIR || jalur.startsWith(`${AWALAN_HALAMAN_FORMULIR}/`);
    });

    // AppShell adalah layout yang BERTAHAN hidup saat berpindah halaman di
    // dalam SPA, jadi penanda "sudah ditutup" ini ikut bertahan. Tanpa reset
    // per halaman, mode HALAMAN tidak akan pernah berperilaku sesuai namanya:
    // modalnya cuma kembali setelah muat ulang penuh.
    //
    // Menunda lewat router.reload() TIDAK mengubah url, jadi menutup modal
    // tetap menutupnya untuk halaman yang sedang dibuka — seperti seharusnya.
    watch(
        () => page.url,
        () => {
            ditutupDiLayarIni.value = false;
        },
    );

    const tampilkanModal = computed(() => {
        if (!data.value) return false;

        // Modal dan bar sama-sama mengajak orang MENUJU halaman formulir. Saat
        // orangnya sudah sampai, ajakan itu cuma menutupi tujuan yang tercapai
        // — dan bagi yang jatah tundanya habis, modalnya tidak punya tombol
        // tutup, tidak menerima Esc, dan tidak menerima klik-luar, sehingga
        // satu-satunya tombolnya membawa ke halaman yang sedang dibuka. Orang
        // itu terkurung: tidak bisa menutup, tidak bisa mengisi. Karena itu
        // pembebasan ini berlaku untuk siapa pun, bukan hanya yang terkunci.
        if (diHalamanFormulir.value) return false;

        if (ditutupDiLayarIni.value) return false;

        return data.value.tampilkanModal === true;
    });

    const tampilkanBar = computed(() => {
        if (!data.value) return false;
        if (!data.value.tampilkanBar) return false;

        // Bar diperiksa sendiri, bukan menumpang hasil modal: bar muncul justru
        // ketika modalnya tidak muncul, jadi tanpa baris ini membungkam modal
        // di halaman formulir malah MENYALAKAN bar di sana.
        if (diHalamanFormulir.value) return false;

        return !tampilkanModal.value;
    });

    /**
     * Dipanggil sekali saat modal benar-benar tampil. Kegagalan ditelan: kalau
     * penandanya tidak tercatat, modal muncul sekali lagi nanti — jauh lebih
     * baik daripada layar orang macet gara-gara pencatatan.
     */
    const catatTampil = () => {
        axios.post('/pengumpulan-data/tampil').catch(() => {});
    };

    const tunda = async () => {
        ditutupDiLayarIni.value = true;

        try {
            await axios.post('/pengumpulan-data/tunda');
        } catch (e) {
            /* jatah gagal tercatat tidak boleh mengunci layar orang */
        }

        // Muat ulang HANYA prop ini supaya hitungan jatah di layar ikut segar
        // tanpa menarik ulang seluruh halaman.
        router.reload({ only: ['pengumpulanData'] });
    };

    const bukaFormulir = () => {
        router.visit('/pengumpulan-data');
    };

    return { data, tampilkanModal, tampilkanBar, catatTampil, tunda, bukaFormulir };
}
