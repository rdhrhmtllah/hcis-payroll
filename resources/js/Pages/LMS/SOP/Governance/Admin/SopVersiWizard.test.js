import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('axios', () => {
    const get = vi.fn().mockResolvedValue({ data: { result: [] } });
    const put = vi.fn().mockResolvedValue({ data: { message: 'ok' } });
    const post = vi.fn().mockResolvedValue({ data: { result: { Id_SOP_Item_Versi: 'zzz' } } });

    return { default: { get, put, post } };
});

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn(), replace: vi.fn() },
}));

/* Mock kit HCIS. Komponennya HARUS ikut disebut, bukan hanya dua fungsinya:
   sejak halaman ini diadopsi ke kit, `@/components/ui` juga mengekspor
   HcisHeader dan kawan-kawan. Mock yang tak menyebutnya membuat SELURUH berkas
   ini gagal dimuat — vitest melaporkannya sebagai "0 test", bentuk kegagalan
   yang mudah dikira "tidak ada yang dijalankan" alih-alih "tak satu pun sempat
   berjalan".

   Pabriknya di-hoist ke puncak berkas, jadi pembantunya WAJIB didefinisikan di
   dalam sini; menaruhnya di luar menghasilkan "Cannot access before
   initialization". */
vi.mock('@/components/ui', () => {
    const stubKit = (nama) => ({ name: nama, template: '<div><slot /></div>' });

    return {
        HcisHeader: stubKit('HcisHeader'),
        HcisBreadcrumb: stubKit('HcisBreadcrumb'),
        HcisCard: stubKit('HcisCard'),
        HcisButton: stubKit('HcisButton'),
        HcisBadge: stubKit('HcisBadge'),
        HcisAlert: stubKit('HcisAlert'),
        HcisStatGroup: stubKit('HcisStatGroup'),
        HcisStatCard: stubKit('HcisStatCard'),
        HcisSkeleton: stubKit('HcisSkeleton'),
        hcisMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
        hcisConfirm: vi.fn().mockResolvedValue(true),
    };
});

import axios from 'axios';
import SopVersiWizard from './SopVersiWizard.vue';

const stub = (nama) => ({ name: nama, template: '<div />' });

const pasang = (props = {}) => mount(SopVersiWizard, {
    props: {
        idDokumen: 'dok1',
        idVersi: null,
        dokumen: { Judul: 'SOP Uji', Tipe: 'DOKUMEN', Nomor_SOP_Resmi: 'SOP-001', No_Dokumen: 'D-001' },
        ...props,
    },
    global: {
        stubs: {
            HcisContextBanner: { template: '<div><slot name="actions" /></div>' },
            HcisAlert: stub('HcisAlert'),
            HcisBadge: { template: '<span><slot /></span>' },
            HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
            VersiStepper: stub('VersiStepper'),
            VersiRingkasan: stub('VersiRingkasan'),
            LangkahPengajuan: stub('LangkahPengajuan'),
            LangkahReview: stub('LangkahReview'),
            LangkahPengesahan: stub('LangkahPengesahan'),
            LangkahPemberkasan: stub('LangkahPemberkasan'),
            SopTerbitPratinjau: stub('SopTerbitPratinjau'),
            KontenPickerModal: stub('KontenPickerModal'),
        },
    },
});

describe('SopVersiWizard', () => {
    /* `restoreMocks: true` di vitest.config.js MENGHAPUS implementasi mock
       sebelum tiap uji, termasuk yang dipasang di factory vi.mock() di atas.
       Tanpa dipasang ulang di sini, axios mengembalikan undefined: simpan()
       gagal diam-diam, id versi baru tak pernah terbaca, dan uji lolos karena
       kebetulan tak menyentuh nilai baliknya — bukan karena perilakunya benar. */
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: { result: [] } });
        axios.put.mockResolvedValue({ data: { message: 'ok' } });
        axios.post.mockResolvedValue({ data: { result: { Id_SOP_Item_Versi: 'zzz' } } });
    });

    /**
     * Profil tipe memutuskan blok pengesahan — bukan daftar larangan di layar.
     *
     * Bentuk sebelumnya `!isKuis && Tipe !== 'FORM'` adalah daftar LARANGAN:
     * ia memberi pengesahan kepada setiap tipe yang kebetulan tak disebut, dan
     * VIDEO tidak disebut. Padahal profil resminya — cermin
     * SopTipeItem::PROFIL di server — hanya memberi VIDEO satu blok: berkas.
     * DML-2026-013 bahkan pernah dijalankan khusus untuk MENGHAPUS data
     * pengesahan pada item bertipe FORM, VIDEO, dan KUIS.
     *
     * VIDEO sengaja disebut lebih dulu di daftar di bawah: dialah satu-satunya
     * yang dulu lolos, dan satu-satunya alasan uji ini ada.
     */
    it.each([
        ['VIDEO', false],
        // FORM ikut disahkan sejak 5 Okt 2026 (opsional di server).
        ['FORM', true],
        ['KUIS', false],
        ['DOKUMEN', true],
    ])('blok pengesahan untuk %s mengikuti profil tipe', (tipe, diharapkan) => {
        const w = pasang({ dokumen: { Judul: 'SOP Uji', Tipe: tipe } });

        expect(w.vm.pakaiPengesahan, tipe).toBe(diharapkan);
    });

    /**
     * Sisi kedua, dan inilah yang membuat perbaikannya tahan lama: peta profil
     * di layar harus tetap sama isinya dengan yang dipakai server. Kalau
     * keduanya menyimpang, layar dan server tak lagi sepakat field mana yang
     * berlaku — persis keadaan yang peta itu dibuat untuk mengakhiri.
     */
    it('tipe di luar profil tidak diberi pengesahan secara diam-diam', () => {
        for (const tipe of ['TAUTAN', 'PARAGRAF', 'ABSENSI', 'TIPE_BARU_YANG_BELUM_ADA', null]) {
            const w = pasang({ dokumen: { Judul: 'SOP Uji', Tipe: tipe } });

            expect(w.vm.pakaiPengesahan, String(tipe)).toBe(false);
        }
    });

    it('menampilkan label aksi utama apa adanya dari server', async () => {
        /* Layar TIDAK merakit "Simpan & Lanjut ke X" sendiri. Begitu tahap
           dinamai ulang di master — dan Approved baru saja menjadi Pemberkasan
           — kalimat rakitan layar ikut basi diam-diam. */
        const w = pasang({ idVersi: 'abc' });
        /* WAJIB sebelum menyuntik keadaan. `idVersi` membuat created() memanggil
           alur.muat() DAN menyalakan memuatInisial. Tanpa menunggunya: (a) promise
           axios yang sudah resolve menimpa suntikan kita — s.langkah kembali [] —
           dan (b) skeleton masih terpasang sehingga badan wizard belum ada di DOM.
           Uji ini merah karena WAKTU, bukan karena perilaku yang diujinya salah. */
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN_LANJUT',
            Label: 'Simpan & Lanjut ke Pemberkasan',
            Ke_Tahap: 'APPROVED',
            Syarat_Kurang: [],
        };
        await w.vm.$nextTick();

        expect(w.text()).toContain('Simpan & Lanjut ke Pemberkasan');
    });

    it('menyebut syarat yang kurang alih-alih menawarkan tombol yang pasti ditolak', async () => {
        const w = pasang({ idVersi: 'abc' });
        /* WAJIB sebelum menyuntik keadaan. `idVersi` membuat created() memanggil
           alur.muat() DAN menyalakan memuatInisial. Tanpa menunggunya: (a) promise
           axios yang sudah resolve menimpa suntikan kita — s.langkah kembali [] —
           dan (b) skeleton masih terpasang sehingga badan wizard belum ada di DOM.
           Uji ini merah karena WAKTU, bukan karena perilaku yang diujinya salah. */
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN',
            Label: 'Simpan',
            Ke_Tahap: null,
            Syarat_Kurang: ['Realisasi pengesah'],
        };
        await w.vm.$nextTick();

        expect(w.text()).toContain('Realisasi pengesah');
    });

    it('menandai formulir bersih sebelum apa pun yang menunggu', async () => {
        /* Menaruh penanda sesudah await berikutnya membuat "belum disimpan"
           muncul SESUDAH simpan berhasil — jendelanya cukup lebar untuk
           terbaca mata. */
        const w = pasang();
        /* created() menunggu muatTemplate(). Tanpa flush, `asli` masih null dan
           `berubah` sengaja melapor kosong — uji ini pernah merah karenanya,
           bukan karena perilakunya salah. */
        await flushPromises();
        w.vm.form.Ringkasan_Perubahan = 'diubah';

        expect(w.vm.kotor).toBe(true);
        w.vm.tandaiBersih();
        expect(w.vm.kotor).toBe(false);
    });

    it('menandai langkah terkunci sehingga isian ikut dimatikan', async () => {
        const w = pasang({ idVersi: 'abc' });
        /* WAJIB sebelum menyuntik keadaan. `idVersi` membuat created() memanggil
           alur.muat() DAN menyalakan memuatInisial. Tanpa menunggunya: (a) promise
           axios yang sudah resolve menimpa suntikan kita — s.langkah kembali [] —
           dan (b) skeleton masih terpasang sehingga badan wizard belum ada di DOM.
           Uji ini merah karena WAKTU, bukan karena perilaku yang diujinya salah. */
        await flushPromises();
        w.vm.alur.langkah = [
            { Nomor: 1, Kelompok: 'PENGAJUAN', Terkunci: false },
            { Nomor: 3, Kelompok: 'PENGESAHAN', Terkunci: true },
        ];
        w.vm.alur.dibuka = 3;
        await w.vm.$nextTick();

        expect(w.vm.langkahTerkunci).toBe(true);
    });

    it('kelompok default PENGAJUAN saat alur belum termuat', () => {
        const w = pasang();

        expect(w.vm.alur.kelompok).toBe('PENGAJUAN');
    });

    /* ── Satu klik, dua kiriman ──────────────────────────────────────────── */

    const barisReview = () => ([{
        kunci: 'baru-1',
        Review_Ke: 1,
        Tanggal_Review: '2026-04-01',
        Hasil_Review: 'Perbaikan redaksi',
        Lampiran: [],
        galat: '',
    }]);

    const urlPut = () => axios.put.mock.calls.map((c) => c[0]);

    it('menyimpan catatan review sekaligus, tanpa klik kedua', async () => {
        /* INI keluhan aslinya: admin menambah catatan review lalu menekan
           "Simpan & Lanjut", dan catatannya hilang karena aksi utama tak pernah
           menyentuh baris review. */
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        w.vm.review = barisReview();

        await w.vm.simpan();

        expect(urlPut().filter((u) => /\/review$/.test(u))).toHaveLength(1);
    });

    it('mengirim versi lebih dulu, baru review', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        w.vm.review = barisReview();

        await w.vm.simpan();

        const u = urlPut();
        expect(u.findIndex((x) => /\/review$/.test(x)))
            .toBeGreaterThan(u.findIndex((x) => /\/versi\/[^/]+$/.test(x)));
    });

    it('tidak mengirim review bila tak ada perubahan', async () => {
        /* Muatan review berat karena lampirannya; mengirimnya di tiap
           penyimpanan kecil adalah persis yang dihindari dengan memisahkan
           endpoint-nya sejak awal. */
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();

        await w.vm.simpan();

        expect(urlPut().filter((u) => /\/review$/.test(u))).toHaveLength(0);
    });

    it('menghitung catatan review sebagai perubahan yang belum disimpan', async () => {
        /* Sebelum 2026-08-27 lencana ini hanya menghitung formulir versi,
           sehingga meninggalkan halaman dengan catatan review yang belum
           tersimpan tak memicu peringatan apa pun. */
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        expect(w.vm.kotor).toBe(false);

        w.vm.review = barisReview();
        await w.vm.$nextTick();

        expect(w.vm.kotor).toBe(true);
        expect(w.vm.jumlahPerubahan).toBe(1);
    });

    /**
     * REGRESI. Versi baru dulu selalu menghadapi "Simpan Draft Versi" — satu
     * klik yang hanya menyimpan, lalu satu klik lagi untuk lanjut. Sekarang
     * aksinya datang dari server dan satu klik melakukan keduanya: versinya
     * lahir (POST) lalu langsung dipindahkan ke tahap berikutnya (PUT tahap).
     */
    it('satu klik pada versi baru: membuat versi lalu memindahkan tahapnya', async () => {
        const w = pasang();
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Review',
            Ke_Tahap: 'REVIEW', Syarat_Kurang: [],
        };

        await w.vm.jalankanUtama();

        expect(axios.post).toHaveBeenCalledTimes(1);
        expect(urlPut().filter((u) => /\/tahap$/.test(u))).toHaveLength(1);
    });

    it('memakai label server apa adanya untuk versi baru', async () => {
        const w = pasang();
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Review',
            Ke_Tahap: 'REVIEW', Syarat_Kurang: [],
        };
        await w.vm.$nextTick();

        /* Bukan "Simpan Draft Versi" yang dirakit layar. */
        expect(w.vm.labelUtama).toBe('Simpan & Lanjut ke Review');
        expect(w.text()).toContain('Simpan & Lanjut ke Review');
    });

    /**
     * REGRESI. Syarat yang kurang dulu MENURUNKAN tombol jadi "Simpan", dan
     * itu melahirkan klik ganda: admin mengetik pengesah, tombolnya masih
     * "Simpan" karena server menilai data tersimpan, ia menekan Simpan, lalu
     * menekan tombol lanjut yang baru muncul.
     */
    it('menampilkan tombol maju walau masih ada syarat yang kurang', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Pemberkasan',
            Ke_Tahap: 'APPROVED', Syarat_Kurang: ['Realisasi pengesah'],
        };
        await w.vm.$nextTick();

        expect(w.text()).toContain('Simpan & Lanjut ke Pemberkasan');
        /* Yang kurang tetap disebut — kabar, bukan penghalang. */
        expect(w.text()).toContain('Realisasi pengesah');
        expect(w.vm.siapMaju).toBe(false);
    });

    it('menandai siap hanya ketika tak ada lagi yang kurang', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Pemberkasan',
            Ke_Tahap: 'APPROVED', Syarat_Kurang: [],
        };
        await w.vm.$nextTick();

        expect(w.vm.siapMaju).toBe(true);
    });

    /* ── Langkah 5: Tinjauan Akhir ───────────────────────────────────────── */

    /* Muatan alur seperti yang dikirim server ketika versi berdiri di
       Pemberkasan dan siap diterbitkan. Dipasang sebagai balasan endpoint
       /langkah supaya penyegaran sesudah simpan tak mengosongkan alurnya —
       menguji di atas alur kosong berarti menguji mock, bukan perilaku. */
    const alurSiapTerbit = {
        Langkah: [
            { Nomor: 1, Nama: 'Pengajuan', Kelompok: 'PENGAJUAN', Terkunci: false, Lengkap: true },
            { Nomor: 2, Nama: 'Review', Kelompok: 'REVIEW', Terkunci: false, Lengkap: true },
            { Nomor: 3, Nama: 'Pengesahan', Kelompok: 'PENGESAHAN', Terkunci: false, Lengkap: true },
            { Nomor: 4, Nama: 'Pemberkasan', Kelompok: 'TERBIT', Terkunci: false, Lengkap: true },
            { Nomor: 5, Nama: 'Tinjauan Akhir', Kelompok: 'TINJAUAN', Terkunci: false, Lengkap: false },
        ],
        Langkah_Aktif: 4,
        Aksi_Utama: {
            Jenis: 'TERBITKAN', Label: 'Terbitkan', Ke_Tahap: 'FILING_REGISTERED',
            Ke_Langkah: 5, Syarat_Kurang: [],
        },
        Aksi_Antar: {
            Jenis: 'LANJUT_LANGKAH', Label: 'Simpan & Lanjut ke Tinjauan Akhir', Ke_Langkah: 5,
        },
        Aksi_Lain: [],
    };

    const siapTerbit = (w, dibuka) => {
        axios.get.mockImplementation((url) => Promise.resolve(
            /\/langkah$/.test(url)
                ? { data: { result: alurSiapTerbit } }
                : { data: { result: [] } },
        ));
        Object.assign(w.vm.alur, {
            langkah: alurSiapTerbit.Langkah,
            langkahAktif: 4,
            dibuka,
            aksiUtama: alurSiapTerbit.Aksi_Utama,
            aksiAntar: alurSiapTerbit.Aksi_Antar,
        });
    };

    it('di Pemberkasan menawarkan antar ke Tinjauan Akhir, bukan menerbitkan', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        siapTerbit(w, 4);
        await w.vm.$nextTick();

        expect(w.vm.aksiKini.Jenis).toBe('LANJUT_LANGKAH');
        expect(w.vm.labelUtama).toBe('Simpan & Lanjut ke Tinjauan Akhir');
    });

    it('di Tinjauan Akhir barulah menawarkan Terbitkan', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        siapTerbit(w, 5);
        await w.vm.$nextTick();

        expect(w.vm.aksiKini.Jenis).toBe('TERBITKAN');
        expect(w.vm.labelUtama).toBe('Terbitkan');
    });

    it('antar-langkah memindahkan layar TANPA memindahkan tahap', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        siapTerbit(w, 4);
        /* muatVersi() tak menemukan versinya pada mock, sehingga `asli` tak
           pernah ditandai dan `kotor` selalu false. Ditandai di sini supaya
           yang diuji perilakunya, bukan kekosongan mock. */
        w.vm.tandaiBersih();
        w.vm.form.Ringkasan_Perubahan = 'diubah';
        await w.vm.$nextTick();

        await w.vm.jalankanUtama();

        expect(w.vm.alur.dibuka).toBe(5);
        expect(urlPut().filter((u) => /\/tahap$/.test(u))).toHaveLength(0);
        /* Isian tetap disimpan: rekap yang menampilkan isian belum tersimpan
           memperlihatkan sesuatu yang belum tentu bertahan. */
        expect(urlPut().filter((u) => /\/versi\/[^/]+$/.test(u))).toHaveLength(1);
        expect(w.vm.pratinjau).toBeNull();
    });

    it('Terbitkan membuka pratinjau dampak, bukan menerbitkan diam-diam', async () => {
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        siapTerbit(w, 5);

        await w.vm.jalankanUtama();

        expect(w.vm.pratinjau).not.toBeNull();
        expect(urlPut().filter((u) => /\/tahap$/.test(u))).toHaveLength(0);
    });

    it('tidak memindahkan tahap bila penyimpanan gagal', async () => {
        /* Urutannya simpan-dulu-baru-pindah: syarat transisi diperiksa server
           terhadap data TERSIMPAN, jadi memindahkan lebih dulu berarti
           memeriksanya terhadap isian yang belum ada. */
        const w = pasang({ idVersi: 'abc' });
        await flushPromises();
        w.vm.alur.aksiUtama = {
            Jenis: 'SIMPAN_LANJUT', Label: 'Simpan & Lanjut ke Pengesahan',
            Ke_Tahap: 'APPROVAL', Syarat_Kurang: [],
        };
        axios.put.mockRejectedValueOnce({ response: { data: { message: 'gagal' } } });

        await w.vm.jalankanUtama();

        expect(urlPut().filter((u) => /\/tahap$/.test(u))).toHaveLength(0);
    });

    describe('form kosong (lampiran) dokumen FORM', () => {
        const formulir = { Judul: 'Form Uji', Tipe: 'FORM', Nomor_SOP_Resmi: 'FRM-001', No_Dokumen: 'D-002' };

        it('hanya tipe FORM yang memakai lampiran', () => {
            expect(pasang().vm.pakaiLampiran).toBe(false);
            expect(pasang({ dokumen: formulir }).vm.pakaiLampiran).toBe(true);
        });

        it('pemilih berkas bertujuan lampiran menambah, bukan mengganti berkas utama', () => {
            const w = pasang({ dokumen: formulir });
            w.vm.form = { ...w.vm.form, Id_Master_Konten: 'utama', Konten_Nama: 'Contoh', Lampiran: [] };

            w.vm.bukaPemilih('lampiran');
            w.vm.onKonten({ id: 'kosong1', judul: 'Form kosong', ext: 'docx' });
            w.vm.bukaPemilih('lampiran');
            w.vm.onKonten({ id: 'kosong1', judul: 'Form kosong', ext: 'docx' });

            expect(w.vm.form.Id_Master_Konten).toBe('utama');
            expect(w.vm.form.Lampiran).toEqual([{ Id_Master_Konten: 'kosong1', Judul: 'Form kosong', Ext: 'docx' }]);
            expect(w.vm.pickerOpen).toBe(false);
        });

        it('pemilih contoh pengisian hanya PDF, pemilih form kosong semua format', async () => {
            // Contoh pengisian FORM wajib PDF sejak 10 Okt 2026; form kosong
            // justru lazim Word/Excel. Aturannya dipilih lewat tipe-item picker.
            const w = pasang({ dokumen: formulir });
            const tipePicker = () => w.findComponent({ name: 'KontenPickerModal' }).attributes('tipe-item');

            w.vm.bukaPemilih('berkas');
            await w.vm.$nextTick();
            expect(tipePicker()).toBe('FORM');

            w.vm.bukaPemilih('lampiran');
            await w.vm.$nextTick();
            expect(tipePicker()).toBe('LAMPIRAN_FORM');
        });

        it('mengirim hanya id konten lampiran, berurutan', () => {
            const w = pasang({ dokumen: formulir });
            w.vm.form = {
                ...w.vm.form,
                Lampiran: [
                    { Id_Master_Konten: 'b', Judul: 'B', Ext: 'xlsx' },
                    { Id_Master_Konten: 'a', Judul: 'A', Ext: 'docx' },
                ],
            };

            expect(w.vm.muatan().Lampiran).toEqual([{ Id_Master_Konten: 'b' }, { Id_Master_Konten: 'a' }]);
        });

        it('tipe tanpa lampiran tidak mengirim kuncinya sama sekali', () => {
            const w = pasang();
            expect('Lampiran' in w.vm.muatan()).toBe(false);
        });
    });
});
