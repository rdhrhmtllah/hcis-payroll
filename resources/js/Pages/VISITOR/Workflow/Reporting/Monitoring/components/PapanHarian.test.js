import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({ default: { get: (...a) => get(...a), post: (...a) => post(...a) } }));
vi.mock('element-plus', () => ({ ElMessage: { error: vi.fn(), success: vi.fn() } }));

import PapanHarian from './PapanHarian.vue';

const PAPAN = {
    tanggal: '2026-08-25',
    ringkasan: { harus_datang: 1, sedang_di_dalam: 1, sudah_keluar_hari_ini: 0, menginap: 1 },
    harus_datang: [
        { Id_Transaksi_Tamu: 1, No_Transaksi: 'VSR-1', Nama_Lengkap_Tamu: 'Budi', Waktu_Aktual_Masuk: null },
    ],
    sedang_di_dalam: [
        {
            Id_Transaksi_Tamu: 2,
            No_Transaksi: 'VSR-2',
            Nama_Lengkap_Tamu: 'Sari',
            Waktu_Aktual_Masuk: '2026-08-24 09:00:00',
            Waktu_Aktual_Keluar: null,
        },
    ],
    menginap: [
        {
            Id_Transaksi_Tamu: 2,
            No_Transaksi: 'VSR-2',
            Nama_Lengkap_Tamu: 'Sari',
            Waktu_Aktual_Masuk: '2026-08-24 09:00:00',
            Waktu_Aktual_Keluar: null,
        },
    ],
    sudah_keluar_hari_ini: [],
    permit_berlaku_hari_ini: [],
    permit_kedaluwarsa_hari_ini: [],
    permit_segera_kedaluwarsa: [{ Id_Transaksi_Permit: 9, No_Transaksi: 'PMT-9', Nama_Pemohon: 'Andi' }],
};

async function mountPapan() {
    get.mockResolvedValue({ data: { success: true, result: PAPAN } });

    const wrapper = shallowMount(PapanHarian);
    await flushPromises();

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
});

describe('PapanHarian', () => {
    it('memuat papan dari endpoint papan harian', async () => {
        await mountPapan();

        expect(get).toHaveBeenCalledWith('/laporanvisitor-papan-harian', { params: {} });
    });

    it('meneruskan domain ke server, bukan menyaring saat render', async () => {
        // Menyaring di layar berarti baris milik domain tetangga tetap dikueri
        // dan dikirim, lalu dibuang.
        get.mockResolvedValue({ data: { success: true, result: PAPAN } });
        const wrapper = shallowMount(PapanHarian, { props: { domain: 'tamu' } });
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/laporanvisitor-papan-harian', { params: { domain: 'tamu' } });
        expect(wrapper.vm.domain).toBe('tamu');
    });

    it('membaca isi dari amplop result, bukan dari akar balasan', async () => {
        // ResponseHelper::success() membungkus muatannya di bawah `result`.
        // Membacanya dari akar tetap lolos data.success dan papan sekadar kosong.
        const wrapper = await mountPapan();

        expect(wrapper.vm.papan.harus_datang).toHaveLength(1);
        expect(wrapper.vm.papan.permit_segera_kedaluwarsa).toHaveLength(1);
    });

    it('tidak menyediakan aksi tandai masuk/keluar', async () => {
        // Kehadiran dicatat aplikasi palang di gerbang, bukan HCIS. Route
        // check-in/check-out sengaja dicabut (commit d641d350) dan dijaga
        // VisitorRouteContractTest; tombol di sini akan selalu 404.
        const wrapper = await mountPapan();

        expect(wrapper.vm.tandaiMasuk).toBeUndefined();
        expect(wrapper.vm.tandaiKeluar).toBeUndefined();
        expect(post).not.toHaveBeenCalled();
    });
});

/**
 * Daftar "Tamu hari ini" dipotong DI SERVER.
 *
 * Versi sebelumnya menggabungkan tiga kelompok dari muatan papan harian lalu
 * memotongnya di layar. Yang dijaga di sini adalah bahwa halaman, pencarian,
 * dan pengurutan benar-benar berangkat ke server — kalau penyaringannya
 * diam-diam kembali ke klien, tampilannya sama persis dan hanya penjaga
 * seperti ini yang menyalakannya.
 */
const HALAMAN_TAMU = {
    baris: [
        {
            Id_Transaksi_Tamu: 1,
            No_Transaksi: 'VSR-1',
            Nama_Lengkap_Tamu: 'Budi',
            Status_Kehadiran: 'Belum datang',
            Waktu_Aktual_Masuk: null,
            Waktu_Aktual_Keluar: null,
        },
    ],
    paginasi: { total: 57, current_page: 1, per_page: 10, total_pages: 6 },
};

function mockDuaEndpoint() {
    get.mockImplementation((url) => Promise.resolve({
        data: {
            success: true,
            result: url.includes('papan-tamu') ? HALAMAN_TAMU : PAPAN,
        },
    }));
}

function paramTerakhirTamu() {
    const panggilan = get.mock.calls.filter(([url]) => url.includes('papan-tamu'));

    return panggilan[panggilan.length - 1][1].params;
}

async function pasangPapan(props = {}) {
    mockDuaEndpoint();
    const wrapper = shallowMount(PapanHarian, { props });
    await flushPromises();

    return wrapper;
}

describe('PapanHarian — daftar tamu berhalaman', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    it('meminta satu halaman ke endpoint tersendiri', async () => {
        await pasangPapan();

        expect(paramTerakhirTamu()).toMatchObject({ page: 1, limit: 10, urut: 'status', arah: 'asc' });
    });

    it('memakai total dari server, bukan jumlah baris yang tampil', async () => {
        const wrapper = await pasangPapan();

        expect(wrapper.vm.paginasi.total).toBe(57);
        expect(wrapper.vm.barisTamu).toHaveLength(1);
    });

    it('tidak mengambil daftar tamu pada ranah permit', async () => {
        await pasangPapan({ domain: 'permit' });

        expect(get.mock.calls.some(([url]) => url.includes('papan-tamu'))).toBe(false);
    });

    it('menunda pencarian dan mengulang dari halaman pertama', async () => {
        const wrapper = await pasangPapan();
        const sebelum = get.mock.calls.length;
        wrapper.vm.paginasi.current_page = 4;

        wrapper.vm.cari = 'Budi';
        await flushPromises();
        expect(get.mock.calls.length).toBe(sebelum); // masih tertahan debounce

        vi.advanceTimersByTime(400);
        await flushPromises();

        expect(paramTerakhirTamu()).toMatchObject({ cari: 'Budi', page: 1 });
    });

    it('meneruskan pengurutan kolom ke server', async () => {
        const wrapper = await pasangPapan();

        wrapper.vm.gantiUrutan({ key: 'masuk', order: 'desc' });
        await flushPromises();

        expect(paramTerakhirTamu()).toMatchObject({ urut: 'masuk', arah: 'desc', page: 1 });
    });

    it('setiap kolom yang bisa diurutkan memakai kunci yang dikenal server', async () => {
        // Daftar putih di VisitorPapanTamuService::URUT. Kunci di luar daftar
        // itu diam-diam jatuh ke urutan baku, sehingga klik pada kolomnya
        // seolah tidak melakukan apa pun.
        const dikenal = ['status', 'nama', 'instansi', 'pic', 'jumlah', 'masuk', 'keluar', 'tanggal'];
        const wrapper = await pasangPapan();

        wrapper.vm.KOLOM_TAMU.filter((k) => k.sortable).forEach((k) => {
            expect(dikenal, `kolom ${k.key} tidak ada di daftar putih server`).toContain(k.key);
        });
    });
});

describe('PapanHarian — sebutan status dan penanda berpindah halaman', () => {
    beforeEach(() => {
        // window.location tidak bisa ditulis di jsdom; diganti objek biasa
        // supaya perpindahan halaman bisa diperiksa tanpa benar-benar pindah.
        Object.defineProperty(window, 'location', {
            value: { href: '' },
            writable: true,
            configurable: true,
        });
    });

    it('mengenali sebutan status yang dikirim server', async () => {
        // Daftar ini cermin statusSql() di VisitorPapanTamuService. Sebutan
        // yang tidak dikenal jatuh ke warna baku tanpa error, jadi "Menginap"
        // yang tertinggal akan tampak normal padahal salah.
        const wrapper = await mountPapan();

        expect(wrapper.vm.warnaStatus('Belum selesai')).toBe('danger');
        expect(wrapper.vm.warnaStatus('Di dalam')).toBe('success');
        expect(wrapper.vm.warnaStatus('Belum datang')).toBe('brand');
    });

    it('tidak menyebut "Menginap" lagi di kartu ringkas', async () => {
        const wrapper = await mountPapan();

        expect(wrapper.vm.kartuRingkas.map((k) => k.label)).toContain('Belum selesai');
        expect(wrapper.vm.kartuRingkas.map((k) => k.label)).not.toContain('Menginap');
    });

    /**
     * Detail TIDAK lagi berpindah halaman.
     *
     * Dua uji sebelumnya di sini menjaga desain lama: bukaDetail(klik, baris)
     * menavigasi ke /verifikator-visitor sambil memasang penanda memuat.
     * Pembuka detail sekarang membuka modal di tempat dan sudah dijaga di
     * blok bawah, jadi keduanya dihapus alih-alih ditambal — uji yang
     * menggambarkan desain yang tidak ada lagi bukan penjaga, melainkan
     * pekerjaan sia-sia bagi siapa pun yang membacanya nanti.
     *
     * Yang TIDAK boleh ikut hilang adalah penanda memuatnya: kartu ringkas
     * masih berpindah halaman lewat bukaTautan(), dan penandanya satu-satunya
     * tanda bahwa klik itu diterima.
     */
    it('memasang penanda memuat pada perpindahan halaman sungguhan', async () => {
        const wrapper = await mountPapan();

        expect(wrapper.vm.menuju).toBe('');

        wrapper.vm.bukaTautan('/verifikator-visitor?detail=abc');

        expect(wrapper.vm.menuju).toBe('/verifikator-visitor?detail=abc');
    });
});

/**
 * DDL-2026-121: halaman Monitoring Tamu Regular / B2B menyuntikkan endpoint
 * lewat 'visitorTamuEndpoints'. Tanpa suntikan, endpoint Regular.
 */
describe('PapanHarian — endpoint per tipe tamu', () => {
    const ENDPOINT_B2B = {
        laporan: '/laporanvisitor-b2b-tamu',
        detail: '/laporanvisitor-b2b-detail-tamu',
        papan_harian: '/laporanvisitor-b2b-papan-harian',
        papan_tamu: '/laporanvisitor-b2b-papan-tamu',
        antrian: '/laporanvisitor-b2b-antrian',
        verifikator: '/verifikator-visitor/b2b',
    };
    const PAPAN_TERTAHAN = { ...PAPAN, ringkasan: { ...PAPAN.ringkasan, menunggu_approval_hari_ini: 2 } };

    function mockTertahan() {
        get.mockImplementation((url) => Promise.resolve({
            data: { success: true, result: url.includes('papan-tamu') ? HALAMAN_TAMU : PAPAN_TERTAHAN },
        }));
    }

    it('tanpa suntikan memakai papan Regular dan meja tamu Regular', async () => {
        mockTertahan();
        const wrapper = shallowMount(PapanHarian, { props: { domain: 'tamu', needsApproval: true } });
        await flushPromises();

        const url = get.mock.calls.map(([u]) => u);
        expect(url).toContain('/laporanvisitor-papan-harian');
        expect(url).toContain('/laporanvisitor-papan-tamu');

        // Dulu '/verifikator-visitor?jenis=tamu' (pengalihan meja gabungan).
        const kartu = wrapper.vm.kartuRingkas.find((k) => k.label === 'Tertahan approval');
        expect(kartu.tautan).toBe('/verifikator-visitor/tamu');
    });

    it('halaman B2B memakai endpoint dan meja B2B yang disuntikkan', async () => {
        mockTertahan();
        const wrapper = shallowMount(PapanHarian, {
            props: { domain: 'tamu', needsApproval: true },
            global: { provide: { visitorTamuEndpoints: ENDPOINT_B2B } },
        });
        await flushPromises();

        const url = get.mock.calls.map(([u]) => u);
        expect(url).toContain('/laporanvisitor-b2b-papan-harian');
        expect(url).toContain('/laporanvisitor-b2b-papan-tamu');
        expect(url).not.toContain('/laporanvisitor-papan-harian');
        expect(url).not.toContain('/laporanvisitor-papan-tamu');

        const kartu = wrapper.vm.kartuRingkas.find((k) => k.label === 'Tertahan approval');
        expect(kartu.tautan).toBe('/verifikator-visitor/b2b');
        expect(wrapper.vm.endpointTamu.detail).toBe('/laporanvisitor-b2b-detail-tamu');
    });
});

/**
 * HcisTable memancarkan { event, row, index } — BUKAN barisnya langsung.
 *
 * Pembuka detail dipanggil dari dua tempat dengan bentuk argumen yang berbeda:
 * tombol "Detail" mengirim barisnya, tabel mengirim pembungkusnya. Menerima satu
 * bentuk saja membuat klik pada BARIS membuka modal kosong — dan tidak ada galat
 * apa pun, karena null adalah nilai yang sah bagi prop id-nya.
 */
describe('Papan Harian — pembuka detail menerima dua bentuk argumen', () => {
    const pasang = () => shallowMount(PapanHarian, { props: { domain: 'tamu' } });

    it('membuka detail tamu dari bentuk tabel { row }', () => {
        const wrapper = pasang();

        wrapper.vm.bukaDetailTamu({ event: {}, index: 0, row: { Id_Transaksi_Tamu: 77 } });

        expect(wrapper.vm.idTamuDetail).toBe(77);
        expect(wrapper.vm.dialogTamuDetail).toBe(true);
    });

    it('tetap membuka detail tamu dari barisnya langsung', () => {
        const wrapper = pasang();

        wrapper.vm.bukaDetailTamu({ Id_Transaksi_Tamu: 88 });

        expect(wrapper.vm.idTamuDetail).toBe(88);
    });

    it('membuka detail permit dari kedua bentuk', () => {
        const wrapper = pasang();

        wrapper.vm.bukaDetailPermit({ event: {}, index: 1, row: { Id_Transaksi_Permit: 12 } });
        expect(wrapper.vm.idPermitDetail).toBe(12);

        wrapper.vm.bukaDetailPermit({ Id_Transaksi_Permit: 34 });
        expect(wrapper.vm.idPermitDetail).toBe(34);
    });

    it('argumen kosong tidak membuka modal tanpa isi', () => {
        const wrapper = pasang();

        wrapper.vm.bukaDetailTamu(null);

        expect(wrapper.vm.dialogTamuDetail).toBe(false);
    });
});
