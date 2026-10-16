import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import axios from 'axios';
import JadwalKedatanganPage from './JadwalKedatanganPage.vue';

vi.mock('axios');

const WorkspaceStub = {
    name: 'VisitorWorkspace',
    props: ['tabs', 'stats', 'modelValue'],
    template: '<div><slot name="context" /><slot /></div>',
};

function baris(ubah = {}) {
    return {
        No_Transaksi: 'VSR0926-0001',
        Estimasi_Masuk: '2026-09-20 08:00',
        Estimasi_Keluar: '2026-09-20 12:00',
        Nama_Tamu: 'Budi',
        Instansi: 'PT Mitra',
        Jumlah_Tamu: 1,
        Nama_PIC: 'Andi',
        No_Plat_Kendaraan: 'BG 1234 UJ',
        Jenis_Kendaraan: 'Truk',
        Kategori: 'B2B_PROCUREMENT',
        Status_Kunjungan: 'SCHEDULED',
        Status_Permit_Coklat: 'AKTIF',
        Tanggal_Kunjungan: '2026-09-20',
        ...ubah,
    };
}

function mountPage(props = {}) {
    return mount(JadwalKedatanganPage, {
        props: { serverDate: '2026-09-20', ...props },
        global: {
            stubs: {
                VisitorWorkspace: WorkspaceStub,
                HcisDatePicker: true,
                HcisField: true,
                HcisSkeleton: true,
                HcisIcon: true,
            },
        },
    });
}

/* Agenda dimuat TamuHistory dari endpoint daftar ber-paging (bentuk
   ResponseHelper::successWithPaginationV2: result = array baris). */
function balasDaftar(daftar) {
    axios.get.mockImplementation((url) => {
        if (url === '/jadwal-kedatangan-tamu-list') {
            return Promise.resolve({
                data: {
                    success: true,
                    result: daftar,
                    pagination: { total: daftar.length, total_pages: 1 },
                },
            });
        }
        return Promise.resolve({ data: { success: true, result: [] } });
    });
}

beforeEach(() => {
    axios.get.mockReset();
    // Ringkasan stat memakai fetch, bukan axios.
    vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true, result: { sedang_di_area: 0, total: 0 } }),
    })));
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('JadwalKedatanganPage (DDL-2026-122)', () => {
    it('tanpa kategori dari matriks: keadaan kosong dan tidak memanggil server', async () => {
        const w = mountPage({ kategori: [] });
        await flushPromises();

        expect(axios.get).not.toHaveBeenCalled();
        expect(w.text()).toContain('Departemen Anda belum terdaftar');
    });

    it('menunjukkan departemen pengguna beserta hak lihat & ajukannya', async () => {
        balasDaftar([]);

        const w = mountPage({
            kategori: [{ kode: 'B2B_PROCUREMENT', label: 'B2B Procurement' }],
            departemen: [{ nama: 'Procurement', lihat: ['B2B Procurement'], ajukan: ['B2B Procurement'] }],
        });
        await flushPromises();

        expect(w.text()).toContain('Akses Departemen');
        expect(w.text()).toContain('Procurement');
        expect(w.find('.jk-card-scopes').text()).toContain('Boleh Mengajukan');
    });

    // Hak dari beberapa role DIGABUNG (keputusan user 2026-09-18): tanpa tab,
    // seluruh jenis tamu tampil dalam satu agenda.
    it('dua role departemen: semua jenis tamu tampil bersama tanpa tab', async () => {
        balasDaftar([
            baris({ No_Transaksi: 'VSR-A', Nama_Tamu: 'Tamu Regular Uji', Kategori: 'REGULAR' }),
            baris({ No_Transaksi: 'VSR-B', Nama_Tamu: 'Tamu Logistic Uji', Kategori: 'B2B_LOGISTIC' }),
        ]);

        const w = mountPage({
            kategori: [
                { kode: 'REGULAR', label: 'Tamu Regular' },
                { kode: 'B2B_LOGISTIC', label: 'B2B Logistic' },
            ],
            departemen: [
                { nama: 'GA', lihat: ['Tamu Regular'], ajukan: [] },
                { nama: 'QC FG', lihat: ['B2B Logistic'], ajukan: [] },
            ],
        });
        await flushPromises();

        expect(w.findComponent(WorkspaceStub).props('tabs')).toBeUndefined();
        expect(w.text()).toContain('Tamu Regular Uji');
        expect(w.text()).toContain('Tamu Logistic Uji');

        const gabungan = w.find('.jk-card-scopes');
        expect(gabungan.text()).toContain('Tamu Regular');
        expect(gabungan.text()).toContain('B2B Logistic');
    });

    it('memuat agenda hari ini dari endpoint jadwal, lengkap dengan plat kendaraan', async () => {
        balasDaftar([baris()]);

        const w = mountPage({ kategori: [{ kode: 'B2B_PROCUREMENT', label: 'B2B Procurement' }] });
        await flushPromises();

        const panggilan = axios.get.mock.calls.find(([url]) => url === '/jadwal-kedatangan-tamu-list');
        expect(panggilan).toBeDefined();
        const params = panggilan[1].params;
        expect(params.date_from).toBe(params.date_to);
        expect(params.date_from).toMatch(/^\d{4}-\d{2}-\d{2}$/);

        expect(w.text()).toContain('Budi');
        expect(w.text()).toContain('BG 1234 UJ');
    });
});
