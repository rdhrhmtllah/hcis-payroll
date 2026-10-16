import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const get = vi.fn();

vi.mock('axios', () => ({ default: { get: (...a) => get(...a) } }));

import TamuDashboard from './TamuDashboard.vue';

/**
 * Tab Dashboard Tamu tidak boleh memuat apa pun milik permit.
 *
 * Penjaga ini me-render komponen SUNGGUHAN (bukan stub), karena cacat yang
 * pernah terjadi justru bersembunyi di situ: prop `domain` hilang saat berkas
 * disunting, `undefined !== 'tamu'` bernilai true, dan blok permit ikut muncul
 * tanpa satu pun uji yang menyala.
 */
const PAPAN_TAMU = {
    tanggal: '2026-08-25',
    ringkasan: { harus_datang: 1, sedang_di_dalam: 1, menginap: 0, menunggu_approval_hari_ini: 0 },
    harus_datang: [{ No_Transaksi: 'VSR-1', Nama_Lengkap_Tamu: 'Budi' }],
    menunggu_approval_hari_ini: [],
    sedang_di_dalam: [],
    menginap: [],
};

const ANALITIK = {
    tren: [],
    sebaran_jam: [],
    kesehatan_data: { total: 0, sudah_masuk: 0, tanpa_waktu_keluar: 0, tanpa_identitas: 0, tanpa_hp: 0 },
    // Server masih boleh mengirimnya; yang dijaga di sini adalah layarnya.
    permit_kategori: [{ kategori: 'HIJAU', jumlah: 6 }],
    permit_tren: [{ tanggal: '2026-08-24', diajukan: 2, disetujui: 1, ditolak: 0 }],
};

const HALAMAN = { baris: [], paginasi: { total: 0, current_page: 1, per_page: 10, total_pages: 1 } };

beforeEach(() => {
    vi.clearAllMocks();
    get.mockImplementation((url) => {
        if (url.includes('papan-harian')) {
            return Promise.resolve({ data: { success: true, result: PAPAN_TAMU } });
        }
        if (url.includes('analitik')) {
            return Promise.resolve({ data: { success: true, result: ANALITIK } });
        }

        return Promise.resolve({ data: { success: true, result: HALAMAN } });
    });
});

/**
 * @param {{needsApproval?: boolean}} props
 *
 * needsApproval sengaja bisa diatur: seksi Antrean Keputusan berada di balik
 * `v-if`, dan pada environment yang approval tamunya dimatikan ia memang TIDAK
 * dirender. Memasang dashboard tanpa menyalakannya lalu menuntut blok itu ada
 * bukan menguji komponen, melainkan menguntungkan diri sendiri.
 */
async function mountDashboard(props = {}) {
    const wrapper = mount(TamuDashboard, {
        props,
        global: {
            stubs: {
                HcisChart: { name: 'HcisChart', props: ['title'], template: '<div class="hcis-chart-stub">{{ title }}</div>' },
            },
        },
    });
    await flushPromises();

    return wrapper;
}

describe('TamuDashboard', () => {
    it('meminta papan harian khusus domain tamu', async () => {
        await mountDashboard();

        const panggilanPapan = get.mock.calls.find(([url]) => url.includes('papan-harian'));

        expect(panggilanPapan, 'papan harian tidak pernah dipanggil').toBeTruthy();
        expect(panggilanPapan[1]).toEqual({ params: { domain: 'tamu' } });
    });

    it('tidak merender blok permit apa pun', async () => {
        const wrapper = await mountDashboard();

        expect(wrapper.findComponent({ name: 'DashboardBlokPermit' }).exists()).toBe(false);
    });

    it('tidak menyebut kata "permit" sama sekali', async () => {
        // Versi pertama penjaga ini melarang FRASA tertentu ('Permit per
        // kategori', 'kedaluwarsa', ...) dan lolos, padahal judul seksi
        // "Keadaan Permit Kendaraan" tetap dirender. Melarang KATA-nya membuat
        // kebocoran sekecil apa pun tidak bisa lewat.
        const teks = (await mountDashboard()).text();

        expect(teks.toLowerCase()).not.toContain('permit');
    });

    it('tetap merender blok tamu', async () => {
        const wrapper = await mountDashboard({ needsApproval: true });

        expect(wrapper.findComponent({ name: 'DashboardBlokTren' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'DashboardAntrianKeputusan' }).exists()).toBe(true);
    });

    it('meminta analitik khusus domain tamu', async () => {
        // Ranah tetangga tidak boleh ikut dihitung server: analitik permit dan
        // tamu sama-sama menyapu tabel transaksi.
        await mountDashboard();

        const panggilan = get.mock.calls.find(([url]) => url.includes('analitik'));

        expect(panggilan, 'analitik tidak pernah dipanggil').toBeTruthy();
        expect(panggilan[1].params).toMatchObject({ domain: 'tamu' });
    });

    /**
     * Sisi sebaliknya, dan inilah keadaan yang benar-benar berjalan hari ini:
     * VISITOR_TAMU_NEEDS_APPROVAL mati, sehingga antrean keputusan memang
     * tidak boleh muncul — DAN endpointnya tidak boleh ikut dipanggil.
     *
     * Tanpa penjaga ini, uji di atas hijau sendirian dan seksi yang seharusnya
     * dorman bisa diam-diam dirender untuk semua orang tanpa ada yang tahu.
     */
    it('menyembunyikan antrean keputusan ketika approval tamu dimatikan', async () => {
        const wrapper = await mountDashboard({ needsApproval: false });

        expect(wrapper.findComponent({ name: 'DashboardAntrianKeputusan' }).exists()).toBe(false);
        expect(get.mock.calls.find(([url]) => url.includes('laporanvisitor-antrian'))).toBeUndefined();
    });

    it('meminta antrean keputusan khusus domain tamu', async () => {
        await mountDashboard({ needsApproval: true });

        const panggilan = get.mock.calls.find(([url]) => url.includes('laporanvisitor-antrian'));

        expect(panggilan, 'antrean approval tidak pernah dipanggil').toBeTruthy();
        expect(panggilan[1].params).toMatchObject({ domain: 'tamu' });
    });

    it('memotong daftar tamu di server, bukan di layar', async () => {
        await mountDashboard();

        const panggilan = get.mock.calls.find(([url]) => url.includes('papan-tamu'));

        expect(panggilan, 'daftar tamu berhalaman tidak pernah dipanggil').toBeTruthy();
        expect(panggilan[1].params).toMatchObject({ page: 1, limit: 10 });
    });

    /**
     * DDL-2026-121: halaman Monitoring Tamu B2B menyuntikkan endpoint-nya
     * lewat 'visitorTamuEndpoints'. Papan, daftar, dan antrean anak-anaknya
     * harus mengikuti suntikan itu — satu panggilan yang tertinggal ke
     * endpoint Regular berujung 403 bagi pemegang kunci B2B saja.
     */
    it('pada halaman B2B memanggil endpoint B2B yang disuntikkan', async () => {
        const wrapper = mount(TamuDashboard, {
            props: { needsApproval: true },
            global: {
                stubs: { HcisChart: { name: 'HcisChart', props: ['title'], template: '<div />' } },
                provide: {
                    visitorTamuEndpoints: {
                        laporan: '/laporanvisitor-b2b-tamu',
                        detail: '/laporanvisitor-b2b-detail-tamu',
                        papan_harian: '/laporanvisitor-b2b-papan-harian',
                        papan_tamu: '/laporanvisitor-b2b-papan-tamu',
                        antrian: '/laporanvisitor-b2b-antrian',
                        verifikator: '/verifikator-visitor/b2b',
                    },
                },
            },
        });
        await flushPromises();

        const url = get.mock.calls.map(([u]) => u);
        expect(url).toContain('/laporanvisitor-b2b-papan-harian');
        expect(url).toContain('/laporanvisitor-b2b-papan-tamu');
        expect(url).toContain('/laporanvisitor-b2b-antrian');
        for (const reguler of ['/laporanvisitor-papan-harian', '/laporanvisitor-papan-tamu', '/laporanvisitor-antrian']) {
            expect(url).not.toContain(reguler);
        }
        expect(wrapper.exists()).toBe(true);
    });
});
