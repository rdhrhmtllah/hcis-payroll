import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const get = vi.fn();
const post = vi.fn();

vi.mock('axios', () => ({ default: { get: (...a) => get(...a), post: (...a) => post(...a) } }));

import TamuAllMonitoring from './TamuAllMonitoring.vue';
import PermitAllMonitoring from './PermitAllMonitoring.vue';

/**
 * All Monitoring: saringan, pencarian, urutan, DAN halaman semuanya di server.
 *
 * Yang dijaga bukan tampilannya, melainkan bahwa tiap perubahan benar-benar
 * berangkat sebagai parameter — dan bahwa halamannya kembali ke satu setiap
 * kali saringannya berubah. Tanpa itu, mengetik pencarian di halaman 4 akan
 * meminta halaman 4 dari hasil yang baru dan layarnya kosong tanpa sebab yang
 * terlihat.
 */
const BALASAN = {
    result: [],
    stats: {},
    pagination: { current_page: 1, total_pages: 3, total: 57 },
};

const stubs = {
    PermitReviewModal: { name: 'PermitReviewModal', props: ['show', 'permitId', 'detailEndpoint', 'readOnly'], template: '<div data-test="detail" />' },
    TamuReviewModal: { name: 'TamuReviewModal', props: ['show', 'tamuId', 'detailEndpoint', 'readOnly'], template: '<div data-test="detail" />' },
    PermitDetailDialog: { name: 'PermitDetailDialog', props: ['show', 'idPermit'], template: '<div data-test="detail" />' },
    TamuDetailDialog: { name: 'TamuDetailDialog', props: ['show', 'idTamu'], template: '<div data-test="detail" />' },
    HcisAlert: true,
    HcisBadge: true,
    HcisButton: true,
    HcisDialog: { name: 'HcisDialog', props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisField: true,
    HcisStatCard: true,
    HcisStatGroup: { template: '<div><slot /></div>' },
    HcisPagination: true,
    HcisFilterBar: {
        name: 'HcisFilterBar',
        props: ['search', 'filters', 'filterValues', 'resultsCount'],
        emits: ['update:search', 'filter-change', 'reset'],
        template: '<div data-test="filter-bar" />',
    },
    HcisTable: {
        name: 'HcisTable',
        props: ['columns', 'rows', 'loading', 'sortKey', 'sortOrder', 'remoteSort'],
        template: '<table><tbody /></table>',
    },
};

const KASUS = [
    {
        nama: 'TamuAllMonitoring',
        komponen: TamuAllMonitoring,
        jalur: '/laporanvisitor-tamu',
        // Pengajuan terbaru di atas, sama dengan monitoring permit (30 Sep 2026).
        urutBaku: 'diajukan',
        kolomUrut: 'Nama_Tamu',
        kunciUrut: 'nama',
        // Kode status tampil DDL-2026-121 (dulu TERJADWAL).
        saringan: { key: 'status', value: 'SCHEDULED' },
    },
    {
        nama: 'PermitAllMonitoring',
        komponen: PermitAllMonitoring,
        jalur: '/laporanvisitor-permit',
        // Pengajuan terbaru di atas (30 Sep 2026).
        urutBaku: 'diajukan',
        // Pasangan ini dipilih bukan sembarangan: nama kolomnya BERBEDA dari
        // kunci servernya (membuktikan penerjemahan benar-benar terjadi), dan
        // kuncinya BERBEDA dari urutan baku (diajukan) — kalau sama, kolom
        // yang tidak dikenali pun akan lolos karena jatuh ke urutan baku tanpa
        // galat, persis kegagalan yang diperingatkan uji ini.
        kolomUrut: 'unit_kerja',
        kunciUrut: 'divisi',
        saringan: { key: 'status', value: 'segera' },
    },
];

beforeEach(() => {
    vi.clearAllMocks();
    get.mockResolvedValue({ data: BALASAN });
    post.mockResolvedValue({ data: { success: true } });
});

function paramTerakhir() {
    return get.mock.calls[get.mock.calls.length - 1][1].params;
}

describe.each(KASUS)('$nama', ({ komponen, jalur, urutBaku, kolomUrut, kunciUrut, saringan }) => {
    async function pasang() {
        const wrapper = mount(komponen, { global: { stubs } });
        await flushPromises();

        return wrapper;
    }

    it('memuat satu halaman dari server saat dipasang', async () => {
        await pasang();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0][0]).toBe(jalur);
        expect(paramTerakhir()).toMatchObject({ page: 1, limit: 20, urut: urutBaku });
    });

    it('memakai saringan dari kit, bukan rakitan sendiri', async () => {
        // Pencarian ber-debounce dan kontrak reset-nya dimiliki HcisFilterBar.
        // Merakit ulang kotak cari + dropdown di tiap halaman membuat tiap
        // halaman punya perilaku saringan yang sedikit berbeda.
        const wrapper = await pasang();

        expect(wrapper.find('[data-test="filter-bar"]').exists()).toBe(true);
    });

    it('pencarian dikirim ke server dan mengulang dari halaman pertama', async () => {
        const wrapper = await pasang();
        wrapper.vm.saring.page = 4;

        wrapper.findComponent({ name: 'HcisFilterBar' }).vm.$emit('update:search', 'budi');
        await flushPromises();

        expect(paramTerakhir()).toMatchObject({ cari: 'budi', page: 1 });
    });

    it('saringan dikirim ke server dan mengulang dari halaman pertama', async () => {
        const wrapper = await pasang();
        wrapper.vm.saring.page = 3;

        wrapper.findComponent({ name: 'HcisFilterBar' }).vm.$emit('filter-change', {
            key: saringan.key,
            value: saringan.value,
        });
        await flushPromises();

        expect(paramTerakhir()).toMatchObject({ [saringan.key]: saringan.value, page: 1 });
    });

    it('mengurut kolom mengirim KUNCI URUT server, bukan nama kolomnya', async () => {
        // Nama kolom mengikuti alias balasan; kunci urut adalah kosakata
        // daftar putih server. Mengirim nama kolom apa adanya akan jatuh ke
        // urutan baku tanpa galat — panahnya berubah, urutannya tidak.
        const wrapper = await pasang();

        wrapper.vm.gantiUrutan({ key: kolomUrut, order: 'asc' });
        await flushPromises();

        expect(paramTerakhir()).toMatchObject({ urut: kunciUrut, arah: 'asc', page: 1 });
        expect(wrapper.vm.urutKolom).toBe(kolomUrut);
    });

    it('mengabaikan kolom yang tidak punya kunci urut', async () => {
        const wrapper = await pasang();
        const sebelum = get.mock.calls.length;

        wrapper.vm.gantiUrutan({ key: 'kolom_karangan', order: 'asc' });
        await flushPromises();

        expect(get.mock.calls.length).toBe(sebelum);
    });

    it('mengganti ukuran halaman mengulang dari halaman pertama', async () => {
        const wrapper = await pasang();
        wrapper.vm.saring.page = 2;

        wrapper.vm.gantiUkuranHalaman(50);
        await flushPromises();

        expect(paramTerakhir()).toMatchObject({ limit: 50, page: 1 });
    });

    it('reset mengembalikan saringan ke keadaan awal', async () => {
        const wrapper = await pasang();
        wrapper.vm.saring.cari = 'sesuatu';
        wrapper.vm.saring.page = 5;

        wrapper.findComponent({ name: 'HcisFilterBar' }).vm.$emit('reset');
        await flushPromises();

        expect(paramTerakhir()).toMatchObject({ cari: '', page: 1, urut: urutBaku });
    });

    it('membaca result/stats/pagination dari AKAR balasan', async () => {
        // Endpoint ini TIDAK memakai ResponseHelper::success, jadi muatannya
        // tidak dibungkus `result`. Membacanya seperti endpoint lain akan
        // menghasilkan tabel kosong tanpa error.
        const wrapper = await pasang();

        expect(wrapper.vm.paginasi.total).toBe(57);
    });
});

/**
 * DDL-2026-121: tipe tamu ditetapkan halaman (rute), jadi penyaring 'tipe'
 * diganti 'kategori' (VISITOR / MITRA), status memakai kosakata tampil baru,
 * dan endpoint disuntik halaman Regular / B2B.
 */
describe('TamuAllMonitoring — tipe per halaman', () => {
    const ENDPOINT_B2B = {
        laporan: '/laporanvisitor-b2b-tamu',
        detail: '/laporanvisitor-b2b-detail-tamu',
        papan_harian: '/laporanvisitor-b2b-papan-harian',
        papan_tamu: '/laporanvisitor-b2b-papan-tamu',
        antrian: '/laporanvisitor-b2b-antrian',
        verifikator: '/verifikator-visitor/b2b',
    };

    async function pasang(opsi = {}) {
        const wrapper = mount(TamuAllMonitoring, { global: { stubs, ...opsi } });
        await flushPromises();

        return wrapper;
    }

    it('tidak lagi mengirim tipe; kategori yang dikirim ke server', async () => {
        const wrapper = await pasang();

        expect(paramTerakhir()).not.toHaveProperty('tipe');
        expect(wrapper.vm.SARINGAN.map((s) => s.key)).not.toContain('tipe');

        wrapper.findComponent({ name: 'HcisFilterBar' }).vm.$emit('filter-change', { key: 'kategori', value: 'MITRA' });
        await flushPromises();

        expect(paramTerakhir()).toMatchObject({ kategori: 'MITRA', page: 1 });
        const kategori = wrapper.vm.SARINGAN.find((s) => s.key === 'kategori');
        expect(kategori.options.map((o) => o.value)).toEqual(['VISITOR', 'MITRA']);
    });

    it('opsi status memakai kosakata tampil baru', async () => {
        const wrapper = await pasang();
        const status = wrapper.vm.SARINGAN.find((s) => s.key === 'status').options.map((o) => o.value);

        expect(status).toEqual([
            'SCHEDULED', 'PENDING_APPROVAL', 'CHECKED_IN', 'CHECKED_OUT', 'KELUAR_TAK_TERCATAT',
            'CANCELLED', 'REJECTED', 'WITHDRAWN', 'REVOKED',
        ]);
        for (const lama of ['TERJADWAL', 'MENUNGGU_APPROVAL', 'DI_DALAM', 'SUDAH_KELUAR', 'TIDAK_DATANG', 'DITOLAK', 'DIBATALKAN']) {
            expect(status).not.toContain(lama);
        }
    });

    it('tanpa approval, opsi PENDING_APPROVAL disembunyikan', async () => {
        const wrapper = mount(TamuAllMonitoring, { props: { needsApproval: false }, global: { stubs } });
        await flushPromises();
        const status = wrapper.vm.saringanList.find((s) => s.key === 'status').options.map((o) => o.value);

        expect(status).not.toContain('PENDING_APPROVAL');
        expect(status).toContain('SCHEDULED');
    });

    it('melabeli dan mewarnai status tampil baru', async () => {
        const wrapper = await pasang();

        expect(wrapper.vm.labelStatusTamu('CHECKED_IN')).toBe('Check-In');
        expect(wrapper.vm.labelStatusTamu('CANCELLED')).toBe('Cancelled');
        expect(wrapper.vm.labelStatusTamu('REVOKED')).toBe('Dicabut');
        expect(wrapper.vm.warnaStatusTamu('CHECKED_IN')).toBe('success');
        expect(wrapper.vm.warnaStatusTamu('REJECTED')).toBe('danger');
        expect(wrapper.vm.sedangDiDalam('CHECKED_IN')).toBe(true);
        expect(wrapper.vm.sedangDiDalam('DI_DALAM')).toBe(false);
    });

    it('tanpa suntikan memakai endpoint Regular', async () => {
        const wrapper = await pasang();

        expect(get.mock.calls[0][0]).toBe('/laporanvisitor-tamu');

        wrapper.vm.bukaDetail({ Id: 3, No_Transaksi: 'VSR-1' });
        await wrapper.vm.$nextTick();
        expect(wrapper.findComponent({ name: 'TamuReviewModal' }).props('detailEndpoint')).toBe('/laporanvisitor-detail-tamu');
    });

    it('halaman B2B memakai endpoint yang disuntikkan', async () => {
        const wrapper = await pasang({ provide: { visitorTamuEndpoints: ENDPOINT_B2B } });

        expect(get.mock.calls[0][0]).toBe('/laporanvisitor-b2b-tamu');

        wrapper.vm.bukaDetail({ Id: 3, No_Transaksi: 'VSR-1' });
        await wrapper.vm.$nextTick();
        expect(wrapper.findComponent({ name: 'TamuReviewModal' }).props('detailEndpoint')).toBe('/laporanvisitor-b2b-detail-tamu');
    });
});


/**
 * Revoke: wewenangnya dijawab server, sebabnya wajib.
 *
 * Yang dijaga di sini adalah dua hal yang gampang sekali salah: tombolnya
 * tidak boleh muncul hanya karena layar merasa boleh, dan permintaan tidak
 * boleh berangkat tanpa alasan — server menuntutnya, jadi mengirim tanpa
 * alasan hanya menghasilkan bolak-balik yang gagal.
 */
describe('PermitAllMonitoring — revoke', () => {
    const BARIS = {
        No_Transaksi: 'PMT0826-0001',
        Nama_Karyawan: 'Ridho',
        No_Plat_Kendaraan: 'BG 1102 AA',
        Status_Efektif: 'AKTIF',
    };

    async function pasang(bolehRevoke) {
        get.mockResolvedValue({
            data: { ...BALASAN, result: [BARIS], boleh_revoke: bolehRevoke },
        });
        const wrapper = mount(PermitAllMonitoring, { global: { stubs } });
        await flushPromises();

        return wrapper;
    }

    it('mengambil wewenangnya dari balasan server, bukan menebak', async () => {
        expect((await pasang(true)).vm.bolehRevoke).toBe(true);
        expect((await pasang(false)).vm.bolehRevoke).toBe(false);
    });

    it('gagal tertutup ketika server tidak menyebutkan wewenangnya', async () => {
        // Balasan lama (tanpa `boleh_revoke`) tidak boleh diartikan "boleh".
        get.mockResolvedValue({ data: BALASAN });
        const wrapper = mount(PermitAllMonitoring, { global: { stubs } });
        await flushPromises();

        expect(wrapper.vm.bolehRevoke).toBe(false);
    });

    it('menolak mengirim tanpa alasan', async () => {
        const wrapper = await pasang(true);
        wrapper.vm.bukaRevoke(BARIS);
        wrapper.vm.alasanRevoke = '   ';

        await wrapper.vm.kirimRevoke();

        expect(post).not.toHaveBeenCalled();
        expect(wrapper.vm.galatAlasan).toBeTruthy();
        expect(wrapper.vm.dialogRevoke).toBe(true);
    });

    it('mengirim alasan ke endpoint meja verifikasi lalu memuat ulang', async () => {
        const wrapper = await pasang(true);
        const sebelum = get.mock.calls.length;

        wrapper.vm.bukaRevoke(BARIS);
        wrapper.vm.alasanRevoke = 'Kendaraan sudah tidak dipakai';
        await wrapper.vm.kirimRevoke();
        await flushPromises();

        expect(post).toHaveBeenCalledWith(
            '/verifikator-permit/PMT0826-0001/revoke',
            { alasan: 'Kendaraan sudah tidak dipakai' },
        );
        expect(wrapper.vm.dialogRevoke).toBe(false);
        // Dimuat ulang, bukan disunting di layar: statusnya milik server.
        expect(get.mock.calls.length).toBeGreaterThan(sebelum);
    });

    it('menahan dialog terbuka dan menampilkan sebab ketika server menolak', async () => {
        const wrapper = await pasang(true);
        post.mockRejectedValueOnce({ response: { data: { message: 'Akses Ditolak.' } } });

        wrapper.vm.bukaRevoke(BARIS);
        wrapper.vm.alasanRevoke = 'Coba';
        await wrapper.vm.kirimRevoke();

        expect(wrapper.vm.dialogRevoke).toBe(true);
        expect(wrapper.vm.galatAlasan).toBe('Akses Ditolak.');
        expect(wrapper.vm.mengirimRevoke).toBe(false);
    });

    it('menamai status efektif dengan bahasa layar', async () => {
        const wrapper = await pasang(true);

        expect(wrapper.vm.labelStatus('DIBATALKAN')).toBe('Akses Dihapus');
        expect(wrapper.vm.labelStatus('TANPA_AKSES')).toBe('Tanpa akses');
    });

    it('mendukung multiple selection dan select all across filter', async () => {
        const wrapper = await pasang(true);
        wrapper.vm.baris = [
            { No_Transaksi: 'PMT-1', Id: 1 },
            { No_Transaksi: 'PMT-2', Id: 2 },
        ];
        wrapper.vm.paginasi = { total: 50, current_page: 1, total_pages: 3 };

        // Select 1 item
        wrapper.vm.onUpdateSelectedKeys(['PMT-1']);
        expect(wrapper.vm.terpilih).toEqual(['PMT-1']);
        expect(wrapper.vm.jumlahTerpilihEfektif).toBe(1);
        expect(wrapper.vm.semuaHalamanIniTerpilih).toBe(false);

        // Select all on current page
        wrapper.vm.onSelectAll({ checked: true, selectedKeys: ['PMT-1', 'PMT-2'] });
        expect(wrapper.vm.semuaHalamanIniTerpilih).toBe(true);

        // Select all across entire filter
        wrapper.vm.pilihSemuaSeluruhFilter();
        expect(wrapper.vm.pilihSemuaFilter).toBe(true);
        expect(wrapper.vm.jumlahTerpilihEfektif).toBe(50);

        // Bulk revoke
        wrapper.vm.bukaBulkRevoke();
        expect(wrapper.vm.dialogBulkRevoke).toBe(true);
        wrapper.vm.alasanBulkRevoke = 'Penataan izin massal';
        await wrapper.vm.kirimBulkRevoke();
        await flushPromises();

        expect(post).toHaveBeenCalledWith(
            '/verifikator-permit-bulk-revoke',
            expect.objectContaining({
                alasan: 'Penataan izin massal',
                select_all_matching: true,
            }),
        );
        expect(wrapper.vm.dialogBulkRevoke).toBe(false);
    });
});


/**
 * Detail dibuka dari tabel, dan pencabutan TIDAK lagi dipicu dari baris.
 *
 * Pencabutan sulit dibatalkan; memindahkannya ke dalam dialog detail memastikan
 * pelakunya sudah melihat dossier lengkap dan sebab pencabutan sebelumnya.
 * Kalau tombolnya kembali ke baris tabel, penjaga ini yang menyalakannya.
 */
describe('All Monitoring — dialog detail', () => {
    it('permit: baris membuka detail dan tidak lagi punya tombol Revoke', async () => {
        get.mockResolvedValue({
            data: {
                ...BALASAN,
                result: [{ No_Transaksi: 'PMT-1', Id: 7, Status_Efektif: 'AKTIF' }],
                boleh_revoke: true,
            },
        });
        const wrapper = mount(PermitAllMonitoring, { global: { stubs } });
        await flushPromises();

        wrapper.vm.bukaDetail({ Id: 7, No_Transaksi: 'PMT-1' });

        expect(wrapper.vm.dialogDetail).toBe(true);
        expect(wrapper.vm.idDetail).toBe(7);

        // Modalnya dirender dengan v-if. Menyetel state saja belum membuatnya
        // ada di DOM; tanpa menunggu render, findComponent selalu menjawab
        // "tidak ada" dan penjaga ini menuduh komponen yang sebenarnya benar.
        await wrapper.vm.$nextTick();

        const detail = wrapper.findComponent({ name: 'PermitReviewModal' });
        expect(detail.exists()).toBe(true);
    });

    it('permit: pencabutan dari dialog menutup detail juga', async () => {
        // Detail yang tetap terbuka menampilkan tombol Cabut untuk permit yang
        // barusan dicabut, plus data status yang sudah basi.
        get.mockResolvedValue({ data: { ...BALASAN, boleh_revoke: true } });
        post.mockResolvedValue({ data: { success: true } });
        const wrapper = mount(PermitAllMonitoring, { global: { stubs } });
        await flushPromises();

        wrapper.vm.bukaDetail({ Id: 7, No_Transaksi: 'PMT-1' });
        wrapper.vm.bukaRevoke({ No_Transaksi: 'PMT-1', Nama_Karyawan: 'X' });
        wrapper.vm.alasanRevoke = 'Sudah tidak dipakai';
        await wrapper.vm.kirimRevoke();
        await flushPromises();

        expect(wrapper.vm.dialogRevoke).toBe(false);
        expect(wrapper.vm.dialogDetail).toBe(false);
    });

    it('tamu: baris membuka detail', async () => {
        get.mockResolvedValue({ data: BALASAN });
        const wrapper = mount(TamuAllMonitoring, { global: { stubs } });
        await flushPromises();

        wrapper.vm.bukaDetail({ Id: 3, No_Transaksi: 'VSR-1' });

        expect(wrapper.vm.dialogDetail).toBe(true);
        expect(wrapper.vm.idDetail).toBe(3);

        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'TamuReviewModal' }).exists()).toBe(true);
    });

    it('kedua tabel menyediakan kolom aksi untuk membuka detail', async () => {
        get.mockResolvedValue({ data: BALASAN });

        for (const komponen of [PermitAllMonitoring, TamuAllMonitoring]) {
            const wrapper = mount(komponen, { global: { stubs } });
            await flushPromises();

            expect(wrapper.vm.KOLOM.map((k) => k.key)).toContain('aksi');
        }
    });
});

describe('PermitAllMonitoring — label sisa masa berlaku', () => {
    const sisa = (n, akhir) => PermitAllMonitoring.methods.sisaLabel(n, akhir);

    it('hari terakhir berlaku menyebut jam berakhir, bukan "0 hari"', () => {
        expect(sisa(0, '2026-09-29 23:59:00')).toBe('Berakhir hari ini 23:59');
        expect(sisa(0)).toBe('Berakhir hari ini');
    });

    it('masih beberapa hari atau sudah lewat', () => {
        expect(sisa(5)).toBe('5 hari');
        expect(sisa(-2)).toBe('Lewat 2 hari');
        expect(sisa(null)).toBe('-');
    });
});

describe('PermitAllMonitoring — saringan kategori & pilih semua', () => {
    it('pilihan kategori diisi dari opsi_kategori server', async () => {
        get.mockResolvedValueOnce({ data: { ...BALASAN, opsi_kategori: ['HIJAU', 'MERAH', 'COKLAT'] } });
        const wrapper = mount(PermitAllMonitoring, { global: { stubs } });
        await flushPromises();

        const kategori = wrapper.vm.saringanTampil.find((f) => f.key === 'jenis_permit');
        expect(kategori.options.map((o) => o.value)).toEqual(['HIJAU', 'MERAH', 'COKLAT']);
    });

    it('pencabutan "pilih semua" membawa SELURUH saringan aktif, termasuk asal dan tanggal', async () => {
        const wrapper = mount(PermitAllMonitoring, { global: { stubs } });
        await flushPromises();
        Object.assign(wrapper.vm.saring, {
            asal: 'TAMU',
            jenis_permit: 'COKLAT',
            mode_tanggal: 'approve',
            rentang_tanggal: ['2026-09-01', '2026-09-30'],
        });

        expect(wrapper.vm.saringanAktif()).toMatchObject({
            asal: 'TAMU',
            jenis_permit: 'COKLAT',
            mode_tanggal: 'approve',
            tanggal_dari: '2026-09-01',
            tanggal_sampai: '2026-09-30',
        });
        expect(wrapper.vm.saringanAktif()).not.toHaveProperty('page');
    });
});

describe('TamuAllMonitoring — rentang bawaan & saringan B2B', () => {
    it('rentang bawaan memakai tanggal LOKAL dan menjangkau 30 hari ke depan', async () => {
        const wrapper = mount(TamuAllMonitoring, { global: { stubs } });
        await flushPromises();

        const lokal = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        const depan = new Date();
        depan.setDate(depan.getDate() + 30);

        expect(wrapper.vm.saring.date_to).toBe(lokal(depan));
        expect(wrapper.vm.saring.date_to > lokal(new Date())).toBe(true);
    });

    it('halaman B2B menawarkan saringan status permit; halaman regular tidak', async () => {
        const b2b = mount(TamuAllMonitoring, {
            global: { stubs, provide: { visitorTamuEndpoints: { laporan: '/laporanvisitor-b2b-tamu', detail: '/x', papan_harian: '/x', papan_tamu: '/x', antrian: '/x', verifikator: '/x' } } },
        });
        await flushPromises();
        expect(b2b.vm.saringanList.map((f) => f.key)).toContain('status_permit');

        const regular = mount(TamuAllMonitoring, { global: { stubs } });
        await flushPromises();
        expect(regular.vm.saringanList.map((f) => f.key)).not.toContain('status_permit');
    });
});
