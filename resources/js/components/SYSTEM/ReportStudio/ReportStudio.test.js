import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import ReportStudio from './ReportStudio.vue';
import { useDefinisi } from './composables/useDefinisi';

const KATALOG = {
    kunci: 'kepatuhan',
    label: 'Kepatuhan',
    grain: 'Satu baris = satu karyawan × paket × item.',
    dimensi: [
        { key: 'divisi', label: 'Divisi', grup: 'identitas', sensitif: false },
        { key: 'paket', label: 'Paket', grup: 'paket', sensitif: false },
        { key: 'ip', label: 'IP Konfirmasi', grup: 'atestasi', sensitif: true },
    ],
    ukuran: [
        { key: 'jml_karyawan', label: 'Jumlah Karyawan', format: 'angka', sensitif: false },
    ],
    saringan: [
        { key: 'divisi', label: 'Divisi', tipe: 'teks', operator: ['salah_satu'] },
    ],
};

const HASIL = {
    title: 'PRATINJAU',
    generated_at: '2026-08-18 10:00',
    columns: [
        { key: 'divisi', label: 'Divisi', width: 20, center: false },
        { key: 'jml_karyawan', label: 'Jumlah Karyawan', width: 12, center: true },
    ],
    rows: [{ divisi: 'IT', jml_karyawan: 15 }],
    catatan: [],
};

const panggilan = { pratinjau: 0, dibatalkan: 0 };

/**
 * Pusat Aktivitas ditiru sebagai singleton agar mata-matanya bisa diperiksa.
 *
 * Modul aslinya memegang timer polling dan memanggil axios saat dimuat; menirunya
 * juga menjaga uji ini tetap tentang Studio, bukan tentang jaringan.
 */
const pusatAktivitas = { notifyNewTask: vi.fn(), refresh: vi.fn() };

vi.mock('@/composables/useActivityCenter', () => ({
    useActivityCenter: () => pusatAktivitas,
}));

/**
 * Api tiruan yang SEDANG dipakai komponen terpasang, supaya satu uji bisa
 * memaksa satu panggilan gagal tanpa merombak seluruh factory mock. Diisi ulang
 * setiap kali komponen dipasang.
 */
let api = null;

vi.mock('./api/reportStudioApi', () => ({
    reportStudioApi: () => (api = {
        katalog: vi.fn().mockResolvedValue(KATALOG),
        galeri: vi.fn().mockResolvedValue({
            resmi: [{ kode: 'sop-sistem-rinci', nama: 'Rincian', dataset: 'kepatuhan', mode: 'rinci' }],
            // Satu template milik pengguna WAJIB ada: tanpa itu tombol hapus tak
            // pernah dirender, dan uji konfirmasi hapus lulus tanpa menekan apa pun.
            saya: [{ Kode: 'sop-saya-1', Nama: 'Punya Saya', Dataset_Key: 'kepatuhan', Mode: 'ringkasan' }],
            dibagikan: [],
        }),
        simpan: vi.fn().mockResolvedValue({ kode: 'sop-x' }),
        duplikat: vi.fn().mockResolvedValue({ kode: 'sop-y' }),
        hapus: vi.fn().mockResolvedValue({}),
        jalankan: vi.fn().mockResolvedValue({ task_id: 'rpt-exp-1' }),
        pratinjau: vi.fn((definisi, signal) => {
            panggilan.pratinjau += 1;
            return new Promise((resolve, reject) => {
                const t = setTimeout(() => resolve(HASIL), 5);
                signal?.addEventListener('abort', () => {
                    clearTimeout(t);
                    panggilan.dibatalkan += 1;
                    const e = new Error('canceled');
                    e.code = 'ERR_CANCELED';
                    reject(e);
                });
            });
        }),
    }),
}));

/**
 * Sejak Studio dibelah dua tab, tab yang terbuka lebih dulu adalah GALERI
 * TEMPLATE — bukan perakitnya. Uji yang memeriksa isi perakit karena itu tidak
 * menemukan apa pun, dan merahnya berbunyi seolah panelnya hilang.
 *
 * Tabnya dipilih lewat prop `tabUtama` yang memang disediakan komponennya,
 * bukan dengan menyetel keadaan dalamnya: prop itu jalur yang sama dengan yang
 * dipakai halaman sungguhan.
 */
function pasang(props = {}) {
    return mount(ReportStudio, {
        props: { baseUrl: '/api/v1/uji', tabUtama: 'studio', ...props },
    });
}

/** Untuk uji yang memang memeriksa galeri template. */
function pasangGaleri(props = {}) {
    return pasang({ tabUtama: 'galeri', ...props });
}

/**
 * Tombol dicari lewat prop `label`, bukan lewat selektor CSS.
 *
 * Dialog Studio di-Teleport ke body, sehingga wrapper.find() tidak menjangkau
 * isinya; pencarian berbasis komponen tetap menembusnya karena Teleport tak
 * memutus pohon komponen.
 */
function tombol(w, label) {
    return w.findAllComponents({ name: 'HcisButton' }).find((b) => b.props('label') === label);
}

async function siapkanDefinisi(w) {
    w.vm.pilihan.dimensi.push('divisi');
    w.vm.pilihan.ukuran.push('jml_karyawan');
    await flushPromises();

    // Menekan "Jalankan" hanya MEMBUKA dialog konfigurasi; pendaftarannya baru
    // terjadi pada tombol "Mulai Export" di dalam dialog itu.
    w.findComponent({ name: 'BilahAksi' }).vm.$emit('jalankan');
    await flushPromises();
}

/**
 * prosesEksekusiExport() menahan hasilnya minimal 600 ms agar animasi
 * "menyiapkan antrean" sempat terbaca. Dengan timer palsu, tanpa memajukan
 * waktu janji itu tak pernah selesai dan fase sukses tak pernah tercapai.
 */
async function selesaikanPermintaanExport() {
    await vi.advanceTimersByTimeAsync(600);
    await flushPromises();
}

async function jalankanExport(w) {
    await flushPromises();
    await siapkanDefinisi(w);
    await tombol(w, 'Mulai Export').trigger('click');
    await selesaikanPermintaanExport();
}

describe('useDefinisi', () => {
    it('menyimpan pilihan kedua mode sekaligus', () => {
        const { mode, pilihan, definisi } = useDefinisi('kepatuhan');

        pilihan.dimensi.push('divisi');
        pilihan.ukuran.push('jml_karyawan');
        mode.value = 'rinci';
        pilihan.kolom.push('paket');

        // Berpindah kembali harus menemukan pilihan ringkasan masih utuh --
        // orang yang salah pilih di awal tak pantas merakit ulang dari nol.
        mode.value = 'ringkasan';
        expect(definisi.value.dimensi).toEqual(['divisi']);
        expect(definisi.value.ukuran).toEqual(['jml_karyawan']);

        mode.value = 'rinci';
        expect(definisi.value.kolom).toEqual(['paket']);
    });

    it('menolak ringkasan tanpa ukuran dan rinci tanpa kolom', () => {
        const { mode, pilihan, galat } = useDefinisi();

        expect(galat.value.ukuran).toBeTruthy();

        pilihan.ukuran.push('jml_karyawan');
        expect(galat.value.ukuran).toBeUndefined();

        mode.value = 'rinci';
        expect(galat.value.kolom).toBeTruthy();
    });

    it('geser menukar posisi dimensi', () => {
        const { pilihan, geser } = useDefinisi();
        pilihan.dimensi.push('divisi', 'paket');

        geser(pilihan.dimensi, 'paket', -1);
        expect(pilihan.dimensi).toEqual(['paket', 'divisi']);

        // Di ujung daftar geseran diabaikan, bukan melempar.
        geser(pilihan.dimensi, 'paket', -1);
        expect(pilihan.dimensi).toEqual(['paket', 'divisi']);
    });
});

describe('ReportStudio', () => {
    beforeEach(() => {
        panggilan.pratinjau = 0;
        panggilan.dibatalkan = 0;
        pusatAktivitas.notifyNewTask.mockClear();
        pusatAktivitas.refresh.mockClear();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('membangun panel dari katalog, bukan dari daftar yang ditulis ulang', async () => {
        const w = pasang();
        await flushPromises();

        expect(w.text()).toContain('Satu baris = satu karyawan × paket × item.');
        expect(w.text()).toContain('Divisi');
        expect(w.text()).toContain('Jumlah Karyawan');
    });

    it('tidak menawarkan field sensitif sama sekali, bukan sekadar menandainya', async () => {
        /* ATURANNYA DIPERKUAT, bukan dihapus. Dulu field sensitif tetap muncul
           di daftar dengan gembok 🔒; kini PanelDimensiUkuran menyaringnya
           keluar (`!d.sensitif && !d.dikunci && !d.locked`). Menandai masih
           menyisakan kemungkinan terpilih; tidak menawarkan tidak. */
        const w = pasang();
        await flushPromises();

        expect(w.text()).not.toContain('IP Konfirmasi');
        expect(w.text()).toContain('Divisi');
        expect(w.text()).toContain('Paket');
    });

    it('menampilkan status kosong yang membedakan sebabnya', async () => {
        const w = pasang();
        await flushPromises();

        // Belum ada ukuran terpilih -> alasannya disebut, bukan 'tidak ada data'.
        // 'atau agregat' WAJIB disebut: sejak perakit agregat racikan ada,
        // sebuah agregat buatan pemakai sudah cukup membuat definisinya sah.
        // Pesan yang hanya menyebut 'ukuran' membuat orang yang sudah
        // menyusun agregat menyangka dirinya mentok.
        expect(w.text()).toContain('Mode ringkasan membutuhkan minimal satu ukuran atau agregat.');
    });

    it('galeri menawarkan Duplikat & ubah untuk template resmi', async () => {
        /* Dipasang di tab GALERI: `pasang()` membuka tab studio, tempat
           GaleriTemplate memang tidak dirender — merah yang menyamar jadi
           "aksinya hilang". Aksinya kini tombol berikon, jadi kalimatnya ada
           di title/aria-label, bukan di teks tombol. */
        const w = pasangGaleri();
        await flushPromises();

        const galeri = w.findComponent({ name: 'GaleriTemplate' });

        expect(galeri.exists(), 'galeri tak dirender di tab galeri').toBe(true);

        /* Kalimatnya khusus tab RESMI — untuk template milik sendiri bunyinya
           "Duplikat template ini" tanpa "& ubah", sebab miliknya sendiri memang
           bisa langsung disunting. Perbedaan itulah yang dijaga. */
        galeri.vm.aktif = 'resmi';
        /* Mode LIST. Tampilan Grid hanya menawarkan Pratinjau dan "Edit di
           Studio"; deretan aksi berikon — jalankan, duplikat, hapus — hidup di
           mode list. Uji yang tak menyebut tampilannya akan mencari tombol di
           mode yang memang tak memilikinya. */
        galeri.vm.tampilan = 'list';
        await flushPromises();

        /* Diperiksa lewat PROP, bukan html(): kalimatnya diberikan sebagai prop
           `title` pada HcisButton, dan kit tak selalu meneruskannya ke atribut
           DOM — html() bisa bersih walau propnya benar. */
        /* Kalimatnya diberikan sebagai `title` pada HcisButton dan diteruskan ke
           atribut DOM, jadi terbaca di html() — tetapi HANYA di mode list. */
        expect(galeri.html()).toContain('Duplikat &amp; ubah template ini');
    });

    it('membatalkan pratinjau yang masih berjalan saat definisi berubah lagi', async () => {
        const w = pasang();
        await flushPromises();

        const vm = w.vm;
        vm.pilihan.dimensi.push('divisi');
        vm.pilihan.ukuran.push('jml_karyawan');
        await flushPromises();

        vi.advanceTimersByTime(400);
        await flushPromises();
        expect(panggilan.pratinjau).toBe(1);

        // Perubahan kedua sebelum yang pertama selesai: request lama WAJIB
        // dibatalkan, jika tidak hasil lama yang datang belakangan menimpa hasil baru.
        vm.pilihan.dimensi.push('paket');
        await flushPromises();
        vi.advanceTimersByTime(400);
        await flushPromises();

        expect(panggilan.dibatalkan).toBeGreaterThanOrEqual(1);
        expect(panggilan.pratinjau).toBe(2);
    });

    /*
     * DILEPAS: 'chip grup menyaring sisi tersedia, dan tidak pernah menyaring
     * sisi terpilih'.
     *
     * Penyaring grup berbentuk chip (.rs-chip-grup) sudah tidak ada di mana pun
     * di ReportStudio — PanelDimensiUkuran kini menyebut `grup` hanya di komentar
     * bentuk katalognya. Uji itu menjaga aturan bagi kendali yang tak lagi
     * dirender, jadi mempertahankannya berarti menuntut fitur yang memang
     * dihapus. Invariannya sendiri ("sisi terpilih tak pernah ikut tersaring")
     * tak punya jalan untuk dilanggar tanpa penyaring itu.
     */
    it('merakit kokpit dua panel dan melaporkan ringkasan ke halaman', async () => {
        const w = pasang();
        await flushPromises();

        expect(w.findComponent({ name: 'HcisSplitter' }).exists()).toBe(true);

        w.vm.pilihan.dimensi.push('divisi');
        w.vm.pilihan.ukuran.push('jml_karyawan');
        await flushPromises();

        const terakhir = w.emitted('ringkas').at(-1)[0];
        expect(terakhir.mode).toBe('ringkasan');
        expect(terakhir.kolom).toBe(2);
        // Belum ada pratinjau → baris TIDAK boleh dilaporkan sebagai 0.
        expect(terakhir.baris).toBeNull();
    });

    it('menyediakan jalan ke Pusat Aktivitas, bukan sekadar menyebutnya', async () => {
        const { useShellState } = await import('@/composables/useShellState');
        const shell = useShellState();
        shell.state.showActivity = false;

        const w = pasang();
        await jalankanExport(w);

        await tombol(w, 'Buka Pusat Aktivitas').trigger('click');

        expect(shell.state.showActivity).toBe(true);
    });

    /**
     * Tanpa ini, tugas yang baru didaftarkan tidak terlihat oleh siapa pun.
     *
     * Polling Pusat Aktivitas berhenti sendiri saat tak ada tugas berjalan dan
     * hanya menyala lagi lewat notifyNewTask(). Studio yang lupa memanggilnya
     * membuat panel baru memuat tugasnya pada pemicu lain — membuka lonceng,
     * tab kembali fokus, pindah halaman — yang umumnya terjadi setelah tugasnya
     * SELESAI. Fase antre dan proses tak pernah terlihat, dan export tampak
     * "tiba-tiba sudah jadi".
     */
    it('memberi tahu Pusat Aktivitas begitu export didaftarkan', async () => {
        const w = pasang();
        await flushPromises();

        await siapkanDefinisi(w);
        await tombol(w, 'Mulai Export').trigger('click');

        // Sebelum permintaannya selesai, belum ada yang perlu dipantau.
        expect(pusatAktivitas.notifyNewTask).not.toHaveBeenCalled();

        await selesaikanPermintaanExport();

        expect(pusatAktivitas.notifyNewTask).toHaveBeenCalledTimes(1);
    });

    it('tidak memberi tahu Pusat Aktivitas ketika pendaftaran export gagal', async () => {
        const w = pasang();
        await flushPromises();

        // Memberitahu di blok finally akan menyalakan polling untuk tugas yang
        // tak pernah ada — panel berputar tanpa isi. Mock-nya baru lahir saat
        // komponen dipasang, jadi penimpaan harus sesudahnya.
        api.jalankan.mockRejectedValueOnce(new Error('gagal'));

        await siapkanDefinisi(w);
        await tombol(w, 'Mulai Export').trigger('click');
        await selesaikanPermintaanExport();

        expect(pusatAktivitas.notifyNewTask).not.toHaveBeenCalled();
    });

    it('memuat ulang panel saat dibuka dari Studio, bukan menampilkan daftar lama', async () => {
        const w = pasang();
        await jalankanExport(w);

        pusatAktivitas.refresh.mockClear();
        await tombol(w, 'Buka Pusat Aktivitas').trigger('click');

        expect(pusatAktivitas.refresh).toHaveBeenCalled();
    });

    it('setelah menyimpan template, galeri berpindah ke tab Milik Saya', async () => {
        const w = pasang();
        await flushPromises();

        w.vm.pilihan.dimensi.push('divisi');
        w.vm.pilihan.ukuran.push('jml_karyawan');
        await flushPromises();

        w.findComponent({ name: 'BilahAksi' }).vm.$emit('simpan');
        await flushPromises();

        // Dialog ter-teleport ke body. Diisi lewat input sungguhan, bukan lewat
        // vm: menambahkan namaTemplate ke defineExpose hanya demi uji adalah API
        // yang lahir dari uji, bukan dari kebutuhan.
        const nama = document.querySelector('#rs-nama-template');
        expect(nama, 'input nama template harus ada di dialog').toBeTruthy();
        nama.value = 'Laporan Saya';
        nama.dispatchEvent(new Event('input', { bubbles: true }));
        await flushPromises();

        document.querySelector('.rs-simpan-kirim').dispatchEvent(new Event('click', { bubbles: true }));
        await flushPromises();

        expect(w.findComponent({ name: 'GaleriTemplate' }).vm.aktif).toBe('saya');
    });

    it('menuliskan sebab tombol jalankan mati, bukan sekadar mematikannya', async () => {
        const w = pasang();
        await flushPromises();

        // Belum ada ukuran → definisi tidak sah.
        // 'atau agregat' WAJIB disebut: sejak perakit agregat racikan ada,
        // sebuah agregat buatan pemakai sudah cukup membuat definisinya sah.
        // Pesan yang hanya menyebut 'ukuran' membuat orang yang sudah
        // menyusun agregat menyangka dirinya mentok.
        expect(w.text()).toContain('Mode ringkasan membutuhkan minimal satu ukuran atau agregat.');

        const bilah = w.findComponent({ name: 'BilahAksi' });
        expect(bilah.exists()).toBe(true);
        expect(bilah.props('sebabMati')).toContain('ukuran');
    });

    it('hapus template tidak memanggil apa pun sebelum dikonfirmasi', async () => {
        // Tab GALERI: di tab studio GaleriTemplate tak dirender sama sekali.
        const w = pasangGaleri();
        await flushPromises();

        const galeri = w.findComponent({ name: 'GaleriTemplate' });
        galeri.vm.aktif = 'saya';
        await flushPromises();

        const tombol = w.find('.rs-btn-hapus');
        expect(tombol.exists(), 'tombol hapus harus dirender di tab Milik Saya').toBe(true);

        await tombol.trigger('click');
        await flushPromises();

        // Klik pertama HANYA membuka dialog.
        expect(galeri.emitted('hapus')).toBeFalsy();
        expect(w.findComponent({ name: 'HcisDialog' }).exists()).toBe(true);

        // HcisDialog ter-teleport ke body, jadi ia ada di document — bukan di
        // dalam pohon wrapper.
        const kirim = document.querySelector('.rs-hapus-kirim');
        expect(kirim, 'tombol konfirmasi harus ada di dialog').toBeTruthy();

        // Baru setelah dikonfirmasi ia benar-benar terjadi.
        kirim.dispatchEvent(new Event('click', { bubbles: true }));
        await flushPromises();
        expect(galeri.emitted('hapus')).toBeTruthy();
    });

    it('pratinjau memakai HcisDataTable dan catatan kaki tidak bisa dilipat', async () => {
        const w = pasang();
        await flushPromises();

        w.vm.pilihan.dimensi.push('divisi');
        w.vm.pilihan.ukuran.push('jml_karyawan');
        await flushPromises();

        vi.advanceTimersByTime(400); // debounce definisi
        await flushPromises();
        vi.advanceTimersByTime(10); // pratinjau tiruan menyelesaikan diri
        await flushPromises();

        expect(w.findComponent({ name: 'HcisDataTable' }).exists()).toBe(true);
        // Catatan kaki wajib polos — tidak ada pemicu lipat apa pun.
        expect(w.find('.rs-catatan details').exists()).toBe(false);
        expect(w.find('.rs-catatan button').exists()).toBe(false);
    });

    it('saringan memakai kontrol design system, bukan select telanjang', async () => {
        const w = pasang();
        await flushPromises();

        await w.find('.rs-tambah').trigger('click');
        await flushPromises();

        expect(w.findComponent({ name: 'HcisSelect' }).exists()).toBe(true);
        expect(w.find('.rs-saringan select').exists()).toBe(false);
    });

    it('pemilih mode dan tombol urutan memakai kontrol design system', async () => {
        const w = pasang();
        await flushPromises();

        expect(w.findComponent({ name: 'HcisTabs' }).exists()).toBe(true);
        // Pil buatan sendiri tidak boleh tersisa.
        expect(w.find('.rs-mode-btn').exists()).toBe(false);
    });
});

describe('ReportStudio di ponsel', () => {
    const asliMatchMedia = window.matchMedia;

    beforeEach(() => {
        window.matchMedia = (q) => ({
            matches: true,
            media: q,
            addEventListener() {},
            removeEventListener() {},
        });
    });

    /* Nilai matchMedia WAJIB dipulihkan: satu window dipakai bersama seluruh
       berkas uji, dan kebocorannya muncul sebagai kegagalan yang
       berpindah-pindah di berkas lain. */
    afterEach(() => {
        window.matchMedia = asliMatchMedia;
    });

    /*
     * KEPUTUSAN user 30 Sep 2026: di ponsel template TETAP bisa dipakai
     * (pratinjau & jalankan export); yang tidak tersedia hanya MERAKIT.
     * Dulu cabang ponsel hanya merender ringkasan, sehingga pengguna ponsel
     * tidak bisa menjalankan template mana pun.
     */
    it('merender galeri template, bukan perakit', async () => {
        const w = pasang();
        await flushPromises();

        // Disembunyikan CSS tidak cukup: 30 item HcisTransfer yang tak terpakai
        // tetap membayar biaya render dan tetap terjangkau pembaca layar.
        expect(w.findComponent({ name: 'PanelDimensiUkuran' }).exists()).toBe(false);
        expect(w.findComponent({ name: 'HcisTransfer' }).exists()).toBe(false);

        const galeri = w.findComponent({ name: 'GaleriTemplate' });
        expect(galeri.exists()).toBe(true);
        expect(galeri.props('bolehRakit')).toBe(false);
        expect(w.find('[data-testid="catatan-ponsel"]').exists()).toBe(true);
        expect(w.text()).not.toContain('Buat Laporan Baru di Studio');
    });

    it('tab studio tidak ditawarkan, dan tab studio yang terbuka jatuh ke galeri', async () => {
        const w = pasang();
        await flushPromises();

        expect(w.vm.tabUtama ?? 'galeri').toBe('galeri');
        const tab = w.emitted('daftarTab')?.at(-1)?.[0] ?? [];
        expect(tab.map((t) => t.key)).toEqual(['galeri']);
        expect(w.findComponent({ name: 'BilahAksi' }).exists()).toBe(false);
    });
});
