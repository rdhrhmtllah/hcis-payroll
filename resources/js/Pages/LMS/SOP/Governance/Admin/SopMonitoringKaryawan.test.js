import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopMonitoringKaryawan from './SopMonitoringKaryawan.vue';

vi.mock('axios');
vi.mock('element-plus', async (importOriginal) => ({
    ...(await importOriginal()),
    ElMessage: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
}));

/**
 * Halaman audit kepatuhan satu karyawan.
 *
 * Seluruh grafik di halaman ini DITURUNKAN di sisi klien dari endpoint yang
 * sudah ada. Turunan adalah tempat kesalahan diam-diam bersembunyi: salah indeks
 * hari membuat peta waktu menuding hari yang keliru, dan salah urutan membuat
 * "sepuluh tercepat" memperlihatkan yang terlama. Karena itu yang diuji di sini
 * adalah ANGKA hasil turunannya, bukan sekadar bahwa komponennya ter-render.
 */
const RINGKAS = {
    Kode_Karyawan: 'A1',
    Nama: 'Budi Santoso',
    No_Induk_Karyawan: '9001',
    Jabatan: 'Operator',
    Departemen: 'HSE',
    Lokasi: 'Pusat',
    total_sop: 4,
    selesai: 1,
    progress: 2,
    belum: 1,
    terlambat: 2,
    sop_kritis_belum: 1,
    persen: 25,
    tenggat_terdekat: '2026-08-25',
    hari_tersisa: 5,
    terakhir_aktif: '2026-08-19 10:00',
};

const PAKET = [
    { Id_SOP_Paket: 'p1', Nama_Paket: 'K3 Dasar', Kategori: 'Keselamatan', Prioritas: 'KRITIS', Status_Paket: 'PROGRESS', Total_Item: 4, Item_Selesai: 1, Item_Perlu_Ulang: 2, Persen: 25, Tenggat: '2026-08-25', Is_Terlambat: false },
    { Id_SOP_Paket: 'p2', Nama_Paket: 'SOP Perjalanan Dinas', Kategori: 'Umum', Prioritas: 'NORMAL', Status_Paket: 'PROGRESS', Total_Item: 5, Item_Selesai: 3, Item_Perlu_Ulang: 0, Persen: 60, Tenggat: '2026-08-10', Is_Terlambat: true },
    { Id_SOP_Paket: 'p3', Nama_Paket: 'Kode Etik', Kategori: 'Umum', Prioritas: 'NORMAL', Status_Paket: 'SELESAI', Total_Item: 3, Item_Selesai: 3, Item_Perlu_Ulang: 0, Persen: 100, Tenggat: null, Is_Terlambat: false },
];

// Senin 17 Agu 2026 pukul 09, dan Minggu 16 Agu pukul 02 (di luar jam kerja).
const REKAM = {
    item: [
        { Judul_Item: 'Kebijakan Umum', Tipe_Item: 'DOKUMEN', Status: 'DIKONFIRMASI', Waktu_Buka: '2026-08-17 09:00', Waktu_Konfirmasi: '2026-08-17 09:05', Durasi_Aktif_Detik: 300 },
        { Judul_Item: 'Alat Pelindung Diri', Tipe_Item: 'DOKUMEN', Status: 'DIKONFIRMASI', Waktu_Buka: '2026-08-17 09:30', Waktu_Konfirmasi: '2026-08-17 09:31', Durasi_Aktif_Detik: 8 },
        { Judul_Item: 'Prosedur Darurat', Tipe_Item: 'VIDEO', Status: 'DIKONFIRMASI', Waktu_Buka: '2026-08-16 02:00', Waktu_Konfirmasi: '2026-08-16 02:00', Durasi_Aktif_Detik: 3 },
        { Judul_Item: 'Belum disentuh', Tipe_Item: 'DOKUMEN', Status: 'BELUM', Waktu_Buka: null, Waktu_Konfirmasi: null, Durasi_Aktif_Detik: 0 },
    ],
    komposisi: { status: [{ label: 'DIKONFIRMASI', jumlah: 3 }, { label: 'BELUM', jumlah: 1 }], tipe: [] },
    ringkas: { total_rekam: 4, total_detik: 311, item_unik: 4, versi_unik: 3, pernah_dibuka: 3, dikonfirmasi: 3 },
    jejak_versi: [],
};

const RIWAYAT = {
    baris: [
        { Tanggal: '2026-08-14 15:32', Nama_Paket: 'K3 Dasar', Nama_Kategori: 'Keselamatan', Peristiwa: 'DICABUT', Alasan: null, Sebab: 'PINDAH_UNIT', Catatan: 'Pindah ke QC', Aktor: 'Rani', Persen: 40 },
    ],
    tersembunyi: 7,
};

function pasang(over = {}) {
    const data = { ringkas: RINGKAS, paket: PAKET, rekam: REKAM, riwayat: RIWAYAT, absensi: [], ...over };
    axios.get = vi.fn((url) => {
        const balas = (result) => Promise.resolve({ data: { success: true, result } });
        if (url.endsWith('/ringkas')) return balas({ karyawan: data.ringkas });
        if (url.endsWith('/paket')) return balas({ items: data.paket });
        if (url.endsWith('/rekam-baca')) return balas(data.rekam);
        if (url.endsWith('/riwayat')) return balas(data.riwayat);

        return balas(data.absensi);
    });
    axios.post = vi.fn(() => Promise.resolve({ data: { success: true, message: 'ok' } }));

    return mount(SopMonitoringKaryawan, {
        props: { kode: 'A1', token: 'tok' },
        global: {
            stubs: {
                HcisBreadcrumb: true,
                HcisButton: true,
                HcisDialog: true,
                HcisFilterBar: true,
                HcisHeader: { template: '<div class="s-header"><slot name="actions" /></div>' },
                HcisSelect: true,
                HcisSelectOption: true,
                HcisContextBanner: { props: ['title', 'subtitle'], template: '<div class="s-banner">{{ title }}|{{ subtitle }}</div>' },
                HcisStatGroup: { props: ['stats'], template: '<div class="s-stats" />' },
                HcisTabs: { props: ['tabs'], template: '<div class="s-tabs" />' },
                HcisChart: { props: ['type'], template: '<div class="s-chart" :data-type="type" />' },
                HcisTimeline: { props: ['items'], template: '<div class="s-timeline" />' },
                HcisCard: { template: '<div class="s-card"><slot /></div>' },
                HcisEmptyState: { props: ['title'], template: '<div class="s-empty">{{ title }}</div>' },
                HcisSkeleton: true,
                ProgressBar: true,
                StatusPill: true,
                SopTugasCard: true,
            },
        },
    });
}

describe('SopMonitoringKaryawan', () => {
    it('merender halaman dan memuat sumber datanya', async () => {
        const w = pasang();
        await flushPromises();

        const dipanggil = axios.get.mock.calls.map((c) => String(c[0]));
        ['/ringkas', '/paket', '/riwayat', '/rekam-baca'].forEach((sufiks) => {
            expect(dipanggil.some((u) => u.endsWith(sufiks))).toBe(true);
        });
        expect(w.find('.s-header').exists()).toBe(true);
    });

    it('menyusun corong dari item, bukan dari paket', async () => {
        const w = pasang();
        await flushPromises();

        // Corong per-paket akan berbunyi 4/1; yang benar per-item: 4 → 3 → 3.
        expect(w.vm.corong.map((c) => c.value)).toEqual([4, 3, 3]);
        expect(w.vm.corongPersen).toBe('75%');
    });

    it('mengurutkan konfirmasi tercepat dari yang TERSINGKAT', async () => {
        const w = pasang();
        await flushPromises();

        expect(w.vm.tercepat.nilai).toEqual([3, 8, 300]);
        // Dua item di bawah 15 detik; sisanya tidak.
        expect(w.vm.tercepat.jumlahMencurigakan).toBe(2);
        expect(w.vm.tercepat.insight).toContain('2 item');
    });

    it('menempatkan pembacaan pada hari dan jam yang benar', async () => {
        const w = pasang();
        await flushPromises();

        const hari = w.vm.peta.hari;
        const senin = hari.indexOf('Senin');
        const minggu = hari.indexOf('Minggu');

        // 17 Agu 2026 = Senin: dua item pada jam 09.
        expect(w.vm.peta.data).toContainEqual([9, senin, 2]);
        // 16 Agu 2026 = Minggu: satu item pada jam 02.
        expect(w.vm.peta.data).toContainEqual([2, minggu, 1]);
        expect(w.vm.peta.puncak).toBe('09:00');
    });

    it('menyebut pembacaan di luar jam kerja tanpa menuduh', async () => {
        const w = pasang();
        await flushPromises();

        // Satu pembacaan pukul 02.00 — disebut sebagai pola, bukan pelanggaran.
        expect(w.vm.peta.insight).toContain('1 dari 3');
        expect(w.vm.peta.insight).toContain('layak ditanyakan');
    });

    it('menyebut ketiga sinyal tindakan sekaligus, bukan hanya yang pertama', async () => {
        const w = pasang();
        await flushPromises();

        const teks = w.find('.s-banner').text();
        expect(teks).toContain('2 paket lewat tenggat');
        expect(teks).toContain('1 paket prioritas kritis');
        expect(teks).toContain('2 item perlu dibaca ulang');
    });

    it('menyembunyikan spanduk saat tak ada yang perlu ditangani', async () => {
        const w = pasang({
            ringkas: { ...RINGKAS, terlambat: 0, sop_kritis_belum: 0 },
            paket: PAKET.map((p) => ({ ...p, Item_Perlu_Ulang: 0, Is_Terlambat: false })),
        });
        await flushPromises();

        expect(w.find('.s-banner').exists()).toBe(false);
    });

    it('mendahulukan paket yang terlambat lalu yang paling tertinggal', async () => {
        // Fixture sengaja dibuat MEMBEDAKAN: paket yang terlambat justru punya
        // persentase LEBIH TINGGI (60%) daripada yang tidak terlambat (25%).
        // Tanpa itu, urutan berdasarkan persentase saja akan lolos secara kebetulan.
        const w = pasang();
        await flushPromises();

        expect(w.vm.paketTerurut.map((p) => p.Nama_Paket)).toEqual([
            'SOP Perjalanan Dinas', // terlambat
            'K3 Dasar', // 25%
            'Kode Etik', // 100%
        ]);
    });

    it('membiarkan keterlambatan mengalahkan persentase pada lencana', async () => {
        const w = pasang({ ringkas: { ...RINGKAS, persen: 95, terlambat: 3 } });
        await flushPromises();

        // 95% terdengar sehat; tiga paket lewat tenggat tidak boleh tertutup olehnya.
        expect(w.vm.lencana).toEqual({ label: '3 terlambat', variant: 'danger' });
    });

    it('menyesuaikan satuan durasi agar terbaca manusia', async () => {
        const menit = pasang({ rekam: { ...REKAM, ringkas: { ...REKAM.ringkas, total_detik: 311 } } });
        await flushPromises();
        expect(menit.vm.durasiTotal).toEqual({ nilai: 5, satuan: 'menit' });

        const jam = pasang({ rekam: { ...REKAM, ringkas: { ...REKAM.ringkas, total_detik: 7200 } } });
        await flushPromises();
        expect(jam.vm.durasiTotal).toEqual({ nilai: '2.0', satuan: 'jam' });
    });

    it('meneruskan riwayat ke timeline lengkap dengan sebab dan pelakunya', async () => {
        const w = pasang();
        await flushPromises();

        const [j] = w.vm.jejakWaktu;
        expect(j.title).toBe('Akses dicabut');
        expect(j.name).toBe('K3 Dasar');
        expect(j.role).toBe('Rani');
        expect(j.meta).toContainEqual({ label: 'Sebab', value: 'Karyawan pindah unit atau jabatan' });
        expect(j.meta).toContainEqual({ label: 'Catatan', value: 'Pindah ke QC' });
    });

    it('menyatakan entri riwayat yang disembunyikan', async () => {
        const w = pasang();
        w.vm.tab = 'riwayat';
        await flushPromises();

        expect(w.find('.mk-nota').text()).toContain('7 entri lama disembunyikan');
    });

    /*
     * Tiga penjaga di bawah PINDAH dari KaryawanDrawer.test.js.
     *
     * Drawer itu dulu yang memegang linimasa riwayat; sejak monitoring karyawan
     * jadi halaman sendiri, drawer-nya tak punya tab maupun linimasa lagi —
     * berkas ujinya menguji rancangan yang tak ada, dan lima ujinya merah
     * selama berminggu-minggu tanpa menjaga apa pun.
     *
     * Perilakunya sendiri MASIH ADA, di sini. Karena itu ujinya dipindah, bukan
     * dibuang: menghapus berkas lama tanpa memindahkan ini akan mengurangi
     * perlindungan diam-diam, dan tepat pada dua hal yang paling mudah rusak —
     * riwayat cabut WAJIB menyebut sebab dan pelakunya.
     */
    it('memakai alasan mesin sebagai cadangan saat sebab manusia kosong', async () => {
        const w = pasang({
            riwayat: {
                baris: [{
                    Tanggal: '2026-08-14 15:32', Nama_Paket: 'K3 Dasar', Peristiwa: 'DICABUT',
                    Sebab: null, Alasan: 'ATURAN_OTOMATIS', Catatan: null, Aktor: 'Rani',
                }],
                tersembunyi: 0,
            },
        });
        await flushPromises();

        const [j] = w.vm.jejakWaktu;

        expect(j.meta).toContainEqual({ label: 'Sebab', value: 'Dicabut mesin aturan penugasan' });
    });

    it('menyebut "Tercatat sistem" saat peristiwanya bukan tindakan manusia', async () => {
        /* Kolom pelaku yang kosong tak boleh tampil kosong: pembaca tak bisa
           membedakan "tak ada yang bertanggung jawab" dari "belum termuat". */
        const w = pasang({
            riwayat: {
                baris: [{
                    Tanggal: '2026-08-14 15:32', Nama_Paket: 'K3 Dasar', Peristiwa: 'DICABUT',
                    Sebab: 'PINDAH_UNIT', Catatan: null, Aktor: null,
                }],
                tersembunyi: 0,
            },
        });
        await flushPromises();

        expect(w.vm.jejakWaktu[0].role).toBe('Tercatat sistem');
    });

    it('tidak memasang nota saat tak ada yang disembunyikan', async () => {
        /* Sisi negatifnya ikut dijaga: nota yang selalu tampil sama tak
           berartinya dengan nota yang tak pernah tampil. */
        const w = pasang({ riwayat: { baris: [], tersembunyi: 0 } });
        w.vm.tab = 'riwayat';
        await flushPromises();

        expect(w.find('.mk-nota').exists()).toBe(false);
    });

    it('bertahan saat seluruh endpoint gagal', async () => {
        axios.get = vi.fn(() => Promise.reject(new Error('putus')));
        const w = mount(SopMonitoringKaryawan, {
            props: { kode: 'A1', token: 'tok' },
            global: { stubs: { HcisBreadcrumb: true, HcisButton: true, HcisDialog: true, HcisFilterBar: true, HcisHeader: true, HcisSelect: true, HcisSelectOption: true, HcisContextBanner: true, HcisStatGroup: true, HcisTabs: true, HcisChart: true, HcisTimeline: true, HcisCard: true, HcisEmptyState: true, HcisSkeleton: true, ProgressBar: true, StatusPill: true, SopTugasCard: true } },
        });
        await flushPromises();

        // Halaman yang meledak saat data gagal membuat admin mengira karyawannya
        // yang tak punya data, bukan jaringannya yang putus.
        expect(w.vm.paket).toEqual([]);
        expect(w.vm.peta.ada).toBe(false);
        expect(w.vm.tercepat.ada).toBe(false);
        expect(Object.values(w.vm.loading).every((v) => v === false)).toBe(true);
    });
});
