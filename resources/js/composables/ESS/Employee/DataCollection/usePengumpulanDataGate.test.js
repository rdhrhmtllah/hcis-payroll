import { beforeEach, describe, expect, it, vi } from 'vitest';

import { nextTick, reactive } from 'vue';

// Halaman dibuat reaktif supaya perpindahan halaman di dalam SPA bisa ditiru —
// itu yang membedakan mode HALAMAN bekerja atau tidak.
const halaman = reactive({ props: { pengumpulanData: null }, url: '/homepage' });
const props = halaman.props;
const routerPalsu = { visit: vi.fn(), reload: vi.fn() };
const post = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => halaman,
    router: routerPalsu,
}));

vi.mock('axios', () => ({
    default: { post: (...a) => post(...a) },
}));

const { usePengumpulanDataGate } = await import('./usePengumpulanDataGate.js');

const dataAktif = (ganti = {}) => ({
    status: { status: 'BELUM', jumlah_tunda: 0, sisa_tunda: 3, terkunci: false },
    pengumuman: { judul: 'J', teks: 'T', label_tombol_isi: 'Isi', label_tombol_tunda: 'Nanti' },
    frekuensi: 'SESI',
    tampilkanModal: true,
    tampilkanBar: true,
    ...ganti,
});

describe('usePengumpulanDataGate', () => {
    beforeEach(() => {
        props.pengumpulanData = null;
        halaman.url = '/homepage';
        vi.clearAllMocks();
        post.mockResolvedValue({ data: { ok: true } });
    });

    it('menutup modal hanya berlaku untuk halaman yang sedang dibuka', async () => {
        props.pengumpulanData = dataAktif();

        const g = usePengumpulanDataGate();
        await g.tunda();
        expect(g.tampilkanModal.value).toBe(false);

        // Berpindah halaman di dalam SPA: AppShell tidak dibuat ulang, jadi
        // tanpa reset penanda lokal mode HALAMAN tak pernah menampilkan
        // modalnya lagi kecuali setelah muat ulang penuh.
        halaman.url = '/sop/saya';
        await nextTick();

        expect(g.tampilkanModal.value).toBe(true);
    });

    it('memuat ulang prop di halaman yang sama tidak membatalkan penutupan', async () => {
        props.pengumpulanData = dataAktif();

        const g = usePengumpulanDataGate();
        await g.tunda();

        // router.reload() tidak mengubah url — modal harus tetap tertutup di
        // halaman ini, kalau tidak menekan "Nanti dulu" terasa tidak berguna.
        props.pengumpulanData = dataAktif({ status: { status: 'BELUM', jumlah_tunda: 1, sisa_tunda: 2, terkunci: false } });
        await nextTick();

        expect(g.tampilkanModal.value).toBe(false);
    });

    it('tidak menampilkan apa pun saat prop kosong (dikecualikan / sudah selesai / kampanye mati)', () => {
        const g = usePengumpulanDataGate();

        expect(g.tampilkanModal.value).toBe(false);
        expect(g.tampilkanBar.value).toBe(false);
    });

    it('menuruti keputusan server, bukan menghitung sendiri', () => {
        props.pengumpulanData = dataAktif({ tampilkanModal: true });
        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(true);

        props.pengumpulanData = dataAktif({ tampilkanModal: false });
        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(false);
    });

    it('tidak menyentuh penyimpanan browser sama sekali', () => {
        props.pengumpulanData = dataAktif();
        const sesi = vi.spyOn(Storage.prototype, 'setItem');

        const g = usePengumpulanDataGate();
        g.catatTampil();
        g.bukaFormulir();

        expect(sesi).not.toHaveBeenCalled();
        sesi.mockRestore();
    });

    it('mencatat kemunculan ke server', () => {
        props.pengumpulanData = dataAktif();

        usePengumpulanDataGate().catatTampil();

        expect(post).toHaveBeenCalledWith('/pengumpulan-data/tampil');
    });

    it('pencatatan yang gagal tidak melempar galat ke pemanggil', () => {
        props.pengumpulanData = dataAktif();
        post.mockRejectedValue(new Error('jaringan mati'));

        expect(() => usePengumpulanDataGate().catatTampil()).not.toThrow();
    });

    it('bar menetap muncul justru ketika modalnya tidak muncul', () => {
        props.pengumpulanData = dataAktif({ tampilkanModal: false });
        expect(usePengumpulanDataGate().tampilkanBar.value).toBe(true);

        props.pengumpulanData = dataAktif({ tampilkanModal: true });
        expect(usePengumpulanDataGate().tampilkanBar.value).toBe(false);
    });

    it('bar bisa dimatikan lewat konfigurasi', () => {
        props.pengumpulanData = dataAktif({ tampilkanModal: false, tampilkanBar: false });

        expect(usePengumpulanDataGate().tampilkanBar.value).toBe(false);
    });

    it('menunda mengirim ke server lalu menyembunyikan modal di layar ini', async () => {
        props.pengumpulanData = dataAktif();

        const g = usePengumpulanDataGate();
        await g.tunda();

        expect(post).toHaveBeenCalledWith('/pengumpulan-data/tunda');
        expect(g.tampilkanModal.value).toBe(false);
        expect(routerPalsu.reload).toHaveBeenCalledWith({ only: ['pengumpulanData'] });
    });

    // ---------------------------------------------------------------------
    // Halaman formulirnya sendiri.
    //
    // Modal dan bar sama-sama mengajak orang MENUJU /pengumpulan-data. Saat
    // orangnya sudah sampai, memunculkan ajakan itu di atasnya menutupi tujuan
    // yang sudah tercapai — dan bagi yang jatah tundanya habis modal tidak
    // punya tombol tutup, tidak menerima Esc, dan tidak menerima klik-luar,
    // sehingga satu-satunya tombolnya ("Isi sekarang") membawa ke halaman yang
    // sedang dibuka. Orangnya terkurung: tak bisa menutup, tak bisa mengisi.
    // ---------------------------------------------------------------------

    it('tidak memunculkan modal di halaman formulirnya sendiri', () => {
        props.pengumpulanData = dataAktif();
        halaman.url = '/pengumpulan-data';

        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(false);
    });

    it('tidak mengurung orang yang jatah tundanya habis di halaman formulir', () => {
        props.pengumpulanData = dataAktif({
            status: { status: 'TERKUNCI', jumlah_tunda: 3, sisa_tunda: 0, terkunci: true },
        });
        halaman.url = '/pengumpulan-data';

        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(false);
    });

    it('bar juga diam di halaman formulir, bukan naik menggantikan modal', () => {
        props.pengumpulanData = dataAktif({ tampilkanModal: false, tampilkanBar: true });
        halaman.url = '/pengumpulan-data';

        expect(usePengumpulanDataGate().tampilkanBar.value).toBe(false);
    });

    it('ringkasan dan query string ikut terhitung halaman formulir', () => {
        props.pengumpulanData = dataAktif();

        halaman.url = '/pengumpulan-data/ringkasan';
        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(false);

        halaman.url = '/pengumpulan-data?dari=modal';
        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(false);
    });

    it('halaman admin yang namanya mirip TIDAK ikut dibebaskan', () => {
        props.pengumpulanData = dataAktif();
        halaman.url = '/admin/pengumpulan-data';

        expect(usePengumpulanDataGate().tampilkanModal.value).toBe(true);
    });

    it('pembebasan itu hanya berlaku selama di halaman formulir', async () => {
        props.pengumpulanData = dataAktif();
        halaman.url = '/pengumpulan-data';

        const g = usePengumpulanDataGate();
        expect(g.tampilkanModal.value).toBe(false);

        halaman.url = '/homepage';
        await nextTick();

        expect(g.tampilkanModal.value).toBe(true);
    });

    it('menekan isi membawa ke halaman formulir', () => {
        props.pengumpulanData = dataAktif();

        usePengumpulanDataGate().bukaFormulir();

        expect(routerPalsu.visit).toHaveBeenCalledWith('/pengumpulan-data');
    });
});
