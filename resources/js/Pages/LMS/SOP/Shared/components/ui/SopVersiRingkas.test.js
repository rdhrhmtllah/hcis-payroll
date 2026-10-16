import { describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import axios from 'axios';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import SopVersiRingkas from './SopVersiRingkas.vue';

vi.mock('axios');
vi.mock('element-plus', async (importOriginal) => {
    const asli = await importOriginal();

    return { ...asli, ElMessage: Object.assign(vi.fn(), { error: vi.fn(), success: vi.fn() }) };
});

/**
 * Panel versi di Builder — BACA SAJA sejak 27 Agu 2026.
 *
 * Menggantikan SopVersionPanel, yang selain menampilkan juga bisa membuat versi,
 * menandainya siap rilis, MENERBITKANNYA, dan menghapus draft. Uji di berkas ini
 * membawa dua hal: perilaku memuat & jejak orang yang memang layak dipertahankan,
 * dan satu penjaga baru — bahwa tak ada satu pun jalur tulis yang tersisa.
 */

/* Daftarnya dikirim lewat endpoint yang memang dipakai komponen ini, bukan
   ditanam ke data(): menanamnya melewati pemetaan apa pun yang terjadi saat
   memuat, sehingga uji ini bisa hijau untuk bentuk data yang tak pernah benar
   -benar sampai ke layar. */
async function pasang(versi) {
    axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: versi } }));

    const w = shallowMount(SopVersiRingkas, {
        props: { idItem: 'hash-item' },
        global: { mocks: { $vlabel: (v) => `v${String(v ?? 0).padStart(2, '0')}` } },
    });
    /* Panel lahir TERTUTUP dan tak memuat apa pun sampai dibuka. Uji isi harus
       membukanya lebih dulu, persis seperti admin. */
    await w.vm.toggle();
    await flushPromises();

    return w;
}

/** Sama seperti pasang(), tapi propnya bebas diatur — dipakai uji cakupan. */
async function pasangPanel(props = {}) {
    axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: [] } }));

    const w = shallowMount(SopVersiRingkas, {
        props: { idItem: 'hash-item', idDokumen: 'dok-abc', ...props },
        global: { mocks: { $vlabel: (v) => `v${String(v ?? 0).padStart(2, '0')}` } },
    });
    await w.vm.toggle();
    await flushPromises();

    return w;
}

const SATU = (extra = {}) => [
    {
        Id_SOP_Item_Versi: 'h1',
        Versi_Ke: 3,
        Status: 'PUBLISHED',
        Konten_Nama: 'Berkas',
        Created_At: '2026-08-10 09:00:00',
        Updated_At: '2026-08-10 09:00:00',
        Dibuat_Oleh_Nama: 'Aji',
        Diubah_Oleh_Nama: 'Aji',
        ...extra,
    },
];

describe('SopVersiRingkas — tak menulis apa pun', () => {
    /**
     * PENJAGA UTAMA berkas ini, dan sengaja diuji dari SUMBERNYA.
     *
     * Menguji lewat "tombolnya tak terlihat" tak cukup: tombol yang hilang bisa
     * kembali dalam satu baris, sementara metode yang memanggil POST/PATCH/DELETE
     * bisa hidup tanpa satu pun tombol dan tetap terjangkau dari mana saja.
     * Yang dijaga di sini adalah tak adanya jalur tulis, bukan tak adanya tombol.
     */
    it('tak memuat satu pun panggilan tulis ke API', () => {
        const sumber = readFileSync(
            resolve(process.cwd(), 'resources/js/Pages/LMS/SOP/Shared/components/ui/SopVersiRingkas.vue'),
            'utf8',
        );

        for (const metode of ['axios.post', 'axios.patch', 'axios.put', 'axios.delete']) {
            expect(sumber.includes(metode), `${metode} muncul di panel versi yang seharusnya baca saja`).toBe(false);
        }
    });

    it('tak menawarkan tombol menerbitkan, menyiapkan rilis, atau menghapus versi', async () => {
        const w = await pasang(SATU({ Status: 'DRAFT' }));
        const teks = w.text();

        for (const kata of ['Terbitkan', 'Siapkan Rilis', 'Kembalikan Draft', 'Versi Baru', 'Buat Versi']) {
            expect(teks.includes(kata), `panel masih menawarkan "${kata}"`).toBe(false);
        }
    });

    it('mengarahkan ke Master Dokumen sebagai gantinya', async () => {
        // Penolakan yang tak menyebut ke mana harus pergi meninggalkan admin
        // menyimpulkan bahwa versinya tak bisa diubah sama sekali.
        const w = await pasangPanel();

        expect(w.text()).toContain('Master Dokumen');
    });
});

describe('SopVersiRingkas — cakupan versi', () => {
    it('menyebut bahwa versinya milik dokumen, bukan paket ini', async () => {
        /* Panel ini duduk di dalam satu paket, jadi tanpa keterangan ini ia
           terbaca seperti kendali versi milik paket tersebut. Revisi dokumen
           berlaku serentak di semua paket pemakainya — termasuk paket yang tak
           sedang dibuka sama sekali. */
        const w = await pasangPanel();

        /* Kalimatnya ditulis ulang, MAKNANYA utuh: peringatan fan-out — revisi
           dokumen menyentuh setiap paket pemakainya, termasuk yang tak sedang
           dibuka — tetap ada. Itulah yang dijaga, bukan susunan katanya. */
        expect(w.text()).toContain('tersinkronisasi ke seluruh paket pemakai');
    });

    it('menautkan ke dokumennya di Master, dibuka di tab baru', async () => {
        // Menavigasi pergi di tengah penyuntingan membuang working draft yang
        // belum disimpan; tautan yang menelan pekerjaan orang lebih buruk
        // daripada tak ada tautan.
        const w = await pasangPanel({ idDokumen: 'dok-abc' });
        /* Tautannya duduk di KEPALA panel, bukan di dalam .ver-lingkup —
           selektor lama tak pernah menemukan apa pun, sehingga uji negatif di
           bawah pun hijau tanpa memeriksa apa-apa. */
        const a = w.find('a[href^="/sop/admin/dokumen"]');

        expect(a.exists(), 'tautan Master Dokumen tak ditemukan').toBe(true);

        expect(a.attributes('href')).toBe('/sop/admin/dokumen?id=dok-abc');
        expect(a.attributes('target')).toBe('_blank');
    });

    it('tak menawarkan tautan Master saat item belum bertaut dokumen', async () => {
        /*
         * KEPUTUSANNYA SUDAH DIAMBIL, dan uji ini berbalik mengikutinya.
         *
         * Versi sebelumnya MENCATAT keadaan apa adanya — tautan selalu ada —
         * sambil menandai bahwa keadaan itu tidak koheren: tautanMaster()
         * memulangkan '/sop/admin/dokumen' sebagai cadangan, nilai truthy yang
         * membuat `v-if="tautanMaster"` di atasnya tak pernah bisa salah.
         * Penjaganya jadi kode mati.
         *
         * Cadangan itu kini dihapus. Mengantar orang ke daftar Master yang
         * kosong tidak lebih menolong daripada tidak menawarkan tautan sama
         * sekali — dan yang kedua setidaknya jujur bahwa dokumennya memang
         * belum ada.
         */
        const w = await pasangPanel({ idDokumen: '' });

        expect(w.find('a[href^="/sop/admin/dokumen"]').exists()).toBe(false);
    });

    it('memuat ulang riwayat saat dokumennya berganti', async () => {
        /* Ganti dokumen = ganti seluruh riwayat versinya, sementara idItem-nya
           TETAP. Tanpa pemuatan ulang, panel terus menampilkan versi milik
           dokumen lama — atau "Belum ada versi" padahal dokumen barunya punya
           beberapa — dan tak ada apa pun di layar yang menjelaskan kenapa. */
        const w = await pasangPanel({ idDokumen: 'dok-lama' });
        const sebelum = axios.get.mock.calls.length;

        await w.setProps({ idDokumen: 'dok-baru' });
        await flushPromises();

        expect(axios.get.mock.calls.length).toBeGreaterThan(sebelum);
    });
});

describe('SopVersiRingkas — accordion & muat bertahap', () => {
    /** Tanpa toggle: persis keadaan saat editor item baru dibuka. */
    async function pasangTertutup(jumlah = 0) {
        const versi = Array.from({ length: jumlah }, (_, i) => ({
            Id_SOP_Item_Versi: `h${i}`,
            Versi_Ke: jumlah - i,
            Status: i === 0 ? 'PUBLISHED' : 'OBSOLETE',
            Konten_Nama: 'Berkas',
            Created_At: '2026-08-10 09:00:00',
            Updated_At: '2026-08-10 09:00:00',
            Dibuat_Oleh_Nama: 'Aji',
            Diubah_Oleh_Nama: 'Aji',
        }));
        axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: versi } }));

        const w = shallowMount(SopVersiRingkas, {
            props: { idItem: 'hash-item' },
            global: { mocks: { $vlabel: (v) => `v${String(v ?? 0).padStart(2, '0')}` } },
        });
        await flushPromises();

        return w;
    }

    /** Permintaan RIWAYAT penuh — dibedakan dari permintaan ringkas. */
    const panggilanPenuh = () => axios.get.mock.calls.filter((c) => !c[1]?.params?.ringkas).length;
    const panggilanRingkas = () => axios.get.mock.calls.filter((c) => c[1]?.params?.ringkas).length;

    it('sebelum dibuka hanya meminta versi yang berlaku, bukan seluruh riwayat', async () => {
        /* Panel ini dirender untuk SETIAP item ber-versi di editor. Yang dicari
           orang saat editor dibuka cuma "sekarang berlaku versi mana" — satu
           baris. Menarik seluruh riwayat dokumen berikut blok penanda tangan
           tiap versinya, untuk sesuatu yang mungkin tak pernah dibuka, adalah
           beban yang dibayar tanpa ditukar apa pun. */
        const w = await pasangTertutup(3);

        expect(panggilanRingkas(), 'versi berlaku tak diambil').toBe(1);
        expect(panggilanPenuh(), 'riwayat penuh ikut ditarik padahal panel tertutup').toBe(0);
        expect(w.find('.ver-timeline').exists()).toBe(false);
        expect(w.find('.ver-aktif').exists(), 'versi berlaku tak terlihat tanpa membuka panel').toBe(true);
    });

    it('memuat riwayat sekali saat dibuka, dan tidak meminta ulang saat ditutup-buka lagi', async () => {
        const w = await pasangTertutup(3);

        await w.vm.toggle();
        await flushPromises();
        expect(panggilanPenuh()).toBe(1);

        await w.vm.toggle(); // tutup
        await w.vm.toggle(); // buka lagi
        await flushPromises();
        expect(panggilanPenuh(), 'isi yang sama diminta dua kali').toBe(1);
    });

    it('merender sebagian dulu, sisanya saat diminta', async () => {
        const w = await pasangTertutup(12);
        await w.vm.toggle();
        await flushPromises();

        expect(w.findAll('.ver-node')).toHaveLength(5);
        expect(w.text()).toContain('5 dari 12 versi');

        await w.vm.muatLagi();
        expect(w.findAll('.ver-node')).toHaveLength(10);

        await w.vm.muatLagi();
        expect(w.findAll('.ver-node')).toHaveLength(12);
        // Habis → tombolnya hilang, bukan menawarkan yang tak ada.
        expect(w.find('.ver-lagi').exists()).toBe(false);
    });

    it('gagal memuat bisa dicoba lagi dengan menutup lalu membuka', async () => {
        // Ditandai "sudah dimuat" saat gagal berarti panelnya mengunci diri
        // pada daftar kosong sampai halaman dimuat ulang.
        axios.get = vi.fn(() => Promise.reject(new Error('putus')));
        const w = shallowMount(SopVersiRingkas, {
            props: { idItem: 'hash-item' },
            global: { mocks: { $vlabel: (v) => `v${v}` } },
        });

        await w.vm.toggle();
        await flushPromises();
        expect(w.vm.sudahDimuat).toBe(false);

        const sebelum = axios.get.mock.calls.length;
        await w.vm.toggle();
        await w.vm.toggle();
        await flushPromises();
        expect(axios.get.mock.calls.length).toBeGreaterThan(sebelum);
    });
});

describe('SopVersiRingkas — jejak pembuat & pengubah', () => {
    it('menyebut siapa yang membuat versinya', async () => {
        const w = await pasang(SATU());

        expect(w.find('.vn-jejak').text()).toMatch(/Dibuat[^·]*Aji/);
    });

    it('menyertakan jam, bukan tanggal saja', async () => {
        // Beberapa suntingan pada hari yang sama itu lumrah di draft bersama;
        // tanpa jam, urutannya tak bisa ditelusuri sama sekali.
        const w = await pasang(SATU());

        expect(w.find('.vn-jejak').text()).toMatch(/\d{2}[.:]\d{2}/);
    });

    it('tak mengulang baris "diubah" bila sama persis dengan "dibuat"', async () => {
        const w = await pasang(SATU());

        expect(w.find('.vn-jejak').text()).not.toMatch(/Diubah/);
    });

    it('menyebut pengubah terakhir bila memang orang lain', async () => {
        const w = await pasang(SATU({ Diubah_Oleh_Nama: 'Ridho', Updated_At: '2026-08-12 17:53:00' }));

        const teks = w.find('.vn-jejak').text();
        expect(teks).toMatch(/Dibuat[^·]*Aji/);
        expect(teks).toMatch(/Diubah[^·]*Ridho/);
    });

    it('menyebutnya juga bila orangnya sama tapi waktunya berbeda', async () => {
        const w = await pasang(SATU({ Updated_At: '2026-08-11 14:20:00' }));

        expect(w.find('.vn-jejak').text()).toMatch(/Diubah[^·]*Aji/);
    });

    it('diam sepenuhnya untuk versi lama yang tak punya jejaknya', async () => {
        // Baris warisan sebelum kolomnya diisi. Menuliskan "Dibuat —" hanya
        // menambah baris kosong yang tampak seperti data rusak.
        const w = await pasang(SATU({ Dibuat_Oleh_Nama: null, Diubah_Oleh_Nama: null }));

        expect(w.find('.vn-jejak').exists()).toBe(false);
    });
});

describe('SopVersiRingkas — isi versi', () => {
    /* Admin perlu tahu apa yang sebenarnya diterima karyawan: satu judul berkas
       tak menyebut jenisnya, apalagi form kosong yang ikut terpasang pada FORM. */
    const FORM_LENGKAP = () =>
        SATU({
            Konten_Nama: 'FILE BARU TESTING',
            Konten_Ext: 'PDF',
            Ukuran_File: 1258291,
            Lampiran: [
                { Nama: 'Formulir A', Ext: 'XLSX', Ukuran: 20480 },
                { Nama: 'Formulir B', Ext: 'DOCX', Ukuran: null },
            ],
        });

    const baris = (w) => w.findAll('.vn-konten .isi-baris').map((b) => b.text().replace(/\s+/g, ' '));

    it('FORM: menyebut contoh pengisian beserta jenis & ukurannya, lalu setiap form kosong', async () => {
        axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: FORM_LENGKAP() } }));
        const w = shallowMount(SopVersiRingkas, {
            props: { idItem: 'hash-item', tipe: 'FORM' },
            global: { mocks: { $vlabel: (v) => `v${v}` } },
        });
        await w.vm.toggle();
        await flushPromises();

        const [utama, kosong] = baris(w);
        expect(utama).toContain('Contoh pengisian');
        expect(utama).toContain('FILE BARU TESTING');
        expect(utama).toContain('PDF');
        expect(utama).toContain('1,2 MB');
        expect(kosong).toContain('Form kosong (2)');
        expect(kosong).toContain('Formulir A');
        expect(kosong).toContain('XLSX');
        expect(kosong).toContain('Formulir B');
    });

    it('FORM tanpa form kosong menyebutnya terus terang', async () => {
        axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: SATU({ Konten_Ext: 'PDF', Lampiran: [] }) } }));
        const w = shallowMount(SopVersiRingkas, {
            props: { idItem: 'hash-item', tipe: 'FORM' },
            global: { mocks: { $vlabel: (v) => `v${v}` } },
        });
        await w.vm.toggle();
        await flushPromises();

        expect(baris(w)[1]).toContain('Belum ada form kosong');
    });

    it('DOKUMEN: hanya berkas utama, tanpa baris form kosong', async () => {
        axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: SATU({ Konten_Ext: 'PDF' }) } }));
        const w = shallowMount(SopVersiRingkas, {
            props: { idItem: 'hash-item', tipe: 'DOKUMEN' },
            global: { mocks: { $vlabel: (v) => `v${v}` } },
        });
        await w.vm.toggle();
        await flushPromises();

        const isi = baris(w);
        expect(isi).toHaveLength(1);
        expect(isi[0]).toContain('Berkas');
        expect(isi[0]).not.toContain('Form kosong');
    });

    it('ringkasan versi berlaku (panel tertutup) menampilkan isi yang sama', async () => {
        axios.get = vi.fn(() => Promise.resolve({ data: { success: true, result: FORM_LENGKAP() } }));
        const w = shallowMount(SopVersiRingkas, {
            props: { idItem: 'hash-item', tipe: 'FORM' },
            global: { mocks: { $vlabel: (v) => `v${v}` } },
        });
        await flushPromises();

        const teks = w.find('.ver-aktif').text();
        expect(teks).toContain('Contoh pengisian');
        expect(teks).toContain('Formulir A');
    });
});
