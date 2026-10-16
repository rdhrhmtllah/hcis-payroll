import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const get = vi.fn();

vi.mock('axios', () => ({ default: { get: (...a) => get(...a) } }));

import DashboardAntrianKeputusan from './DashboardAntrianKeputusan.vue';

/**
 * Antrean approval dipotong DI SERVER.
 *
 * Yang dijaga di sini bukan tampilannya, melainkan bahwa halaman, pencarian,
 * dan pengurutan benar-benar dikirim ke server. Versi sebelumnya menerima
 * delapan baris teratas sebagai prop dan memotongnya di layar; kalau komponen
 * ini diam-diam kembali menyaring di klien, jumlah permintaannya tidak berubah
 * dan hanya penjaga seperti inilah yang menyalakannya.
 */
const HALAMAN = {
    baris: [
        {
            no_transaksi: 'VSR0826-0001',
            judul: 'Budi Santoso',
            jenis: 'TAMU',
            pemohon: 'Andi',
            approver: 'HRD',
            umur_menit: 90,
        },
    ],
    paginasi: { total: 42, current_page: 1, per_page: 10, total_pages: 5 },
};

const stubs = {
    HcisCard: { template: '<section><slot name="actions" /><slot /><slot name="footer" /></section>' },
    HcisAvatar: true,
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisIcon: true,
    HcisField: {
        props: ['modelValue'],
        emits: ['update:modelValue'],
        template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
    },
    HcisPagination: { props: ['modelValue', 'pageSize', 'total'], template: '<nav data-test="paginasi" />' },
    HcisTable: {
        props: ['columns', 'rows', 'loading', 'sortKey', 'sortOrder', 'remoteSort'],
        template: '<table><tbody><tr v-for="r in rows" :key="r.no_transaksi"><td>{{ r.judul }}</td></tr></tbody></table>',
    },
};

function panggilanTerakhir() {
    return get.mock.calls[get.mock.calls.length - 1][1].params;
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    get.mockResolvedValue({ data: { success: true, result: HALAMAN } });
});

async function pasang(props = {}) {
    const wrapper = mount(DashboardAntrianKeputusan, { props, global: { stubs } });
    await flushPromises();

    return wrapper;
}

describe('DashboardAntrianKeputusan', () => {
    it('meminta satu halaman ke server, bukan seluruh antrean', async () => {
        await pasang({ domain: 'tamu' });

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0][0]).toBe('/laporanvisitor-antrian');
        expect(panggilanTerakhir()).toMatchObject({
            page: 1,
            limit: 10,
            urut: 'menunggu',
            arah: 'asc',
            domain: 'tamu',
        });
    });

    it('memakai total dari server, bukan jumlah baris yang tampil', async () => {
        const wrapper = await pasang();

        expect(wrapper.vm.paginasi.total).toBe(42);
        expect(wrapper.vm.baris).toHaveLength(1);
    });

    it('menunda pencarian dan mengulang dari halaman pertama', async () => {
        const wrapper = await pasang();
        wrapper.vm.paginasi.current_page = 3;

        await wrapper.find('input').setValue('Budi');
        expect(get).toHaveBeenCalledTimes(1); // masih tertahan debounce

        vi.advanceTimersByTime(400);
        await flushPromises();

        expect(get).toHaveBeenCalledTimes(2);
        expect(panggilanTerakhir()).toMatchObject({ cari: 'Budi', page: 1 });
    });

    it('mengetik beruntun hanya menghasilkan satu permintaan', async () => {
        const wrapper = await pasang();

        for (const teks of ['B', 'Bu', 'Bud', 'Budi']) {
            await wrapper.find('input').setValue(teks);
            vi.advanceTimersByTime(100);
        }

        vi.advanceTimersByTime(400);
        await flushPromises();

        expect(get).toHaveBeenCalledTimes(2);
        expect(panggilanTerakhir().cari).toBe('Budi');
    });

    it('meneruskan pengurutan kolom ke server', async () => {
        const wrapper = await pasang();

        wrapper.vm.gantiUrutan({ key: 'pemohon', order: 'desc' });
        await flushPromises();

        expect(panggilanTerakhir()).toMatchObject({ urut: 'pemohon', arah: 'desc', page: 1 });
    });

    it('setiap kolom yang bisa diurutkan memakai kunci yang dikenal server', async () => {
        // Daftar putih di VisitorDashboardOperasionalService::URUT_ANTRIAN.
        // Kunci di luar daftar itu diam-diam jatuh ke urutan baku, sehingga
        // klik pada kolomnya seolah tidak melakukan apa pun.
        const dikenal = ['menunggu', 'nomor', 'jenis', 'judul', 'pemohon', 'approver'];
        const wrapper = await pasang();

        wrapper.vm.KOLOM.filter((k) => k.sortable).forEach((k) => {
            expect(dikenal, `kolom ${k.key} tidak ada di daftar putih server`).toContain(k.key);
        });
    });
});

describe('DashboardAntrianKeputusan — penanda berpindah halaman', () => {
    beforeEach(() => {
        Object.defineProperty(window, 'location', {
            value: { href: '' },
            writable: true,
            configurable: true,
        });
    });

    it('menyalakan penanda memuat saat review dibuka', async () => {
        const wrapper = await pasang();
        const baris = { no_transaksi: 'VSR0826-0001', jenis: 'TAMU' };
        const klik = { metaKey: false, ctrlKey: false, shiftKey: false, altKey: false, preventDefault: vi.fn() };

        wrapper.vm.bukaReview(klik, baris);

        expect(klik.preventDefault).toHaveBeenCalled();
        expect(wrapper.vm.menuju).toBe('VSR0826-0001');
        expect(window.location.href).toContain('VSR0826-0001');
    });

    it('klik dengan modifier dibiarkan membuka tab baru tanpa penanda', async () => {
        const wrapper = await pasang();
        const klik = { metaKey: true, ctrlKey: false, shiftKey: false, altKey: false, preventDefault: vi.fn() };

        wrapper.vm.bukaReview(klik, { no_transaksi: 'VSR0826-0001', jenis: 'TAMU' });

        expect(klik.preventDefault).not.toHaveBeenCalled();
        expect(wrapper.vm.menuju).toBe('');
        expect(window.location.href).toBe('');
    });

    it('tautan review membawa ranah yang benar', async () => {
        const wrapper = await pasang();

        expect(wrapper.vm.tautanAksi({ no_transaksi: 'PMT-9', jenis: 'PERMIT' }))
            .toBe('/verifikator-visitor?jenis=permit&search=PMT-9');
        // DDL-2026-121: baris tamu langsung ke meja tamu bertipe halaman ini
        // (bawaan Regular), bukan lagi lewat pengalihan '?jenis=tamu'.
        expect(wrapper.vm.tautanAksi({ no_transaksi: 'VSR-1', jenis: 'TAMU' }))
            .toBe('/verifikator-visitor/tamu?search=VSR-1');
    });
});

describe('DashboardAntrianKeputusan — halaman B2B', () => {
    const ENDPOINT_B2B = {
        laporan: '/laporanvisitor-b2b-tamu',
        detail: '/laporanvisitor-b2b-detail-tamu',
        papan_harian: '/laporanvisitor-b2b-papan-harian',
        papan_tamu: '/laporanvisitor-b2b-papan-tamu',
        antrian: '/laporanvisitor-b2b-antrian',
        verifikator: '/verifikator-visitor/b2b',
    };

    async function pasangB2b() {
        const wrapper = mount(DashboardAntrianKeputusan, {
            props: { domain: 'tamu' },
            global: { stubs, provide: { visitorTamuEndpoints: ENDPOINT_B2B } },
        });
        await flushPromises();

        return wrapper;
    }

    it('memuat antrean dari endpoint B2B yang disuntikkan', async () => {
        await pasangB2b();

        expect(get).toHaveBeenCalledTimes(1);
        expect(get.mock.calls[0][0]).toBe('/laporanvisitor-b2b-antrian');
        expect(panggilanTerakhir()).toMatchObject({ domain: 'tamu', page: 1 });
    });

    it('baris tamu menuju meja B2B, permit tetap ke meja permit', async () => {
        const wrapper = await pasangB2b();

        expect(wrapper.vm.tautanAksi({ no_transaksi: 'VSR 1/2', jenis: 'TAMU' }))
            .toBe('/verifikator-visitor/b2b?search=VSR%201%2F2');
        expect(wrapper.vm.tautanAksi({ no_transaksi: 'PMT-9', jenis: 'PERMIT' }))
            .toBe('/verifikator-visitor?jenis=permit&search=PMT-9');
    });
});
