import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const get = vi.fn();

vi.mock('axios', () => ({ default: { get: (...a) => get(...a) } }));

import PermitDashboard from './PermitDashboard.vue';

/**
 * Tab Dashboard Permit tidak boleh memuat apa pun milik tamu.
 *
 * Penjaga ini me-render komponen SUNGGUHAN (bukan stub), karena cacat yang
 * pernah terjadi justru bersembunyi di situ: prop `domain` hilang saat berkas
 * disunting, `undefined !== 'tamu'` bernilai true, dan blok permit ikut muncul
 * tanpa satu pun uji yang menyala.
 */
const PAPAN_PERMIT = {
    tanggal: '2026-08-25',
    ringkasan: { permit_kedaluwarsa_hari_ini: 1, permit_segera_kedaluwarsa: 2, permit_menunggu_review: 3 },
    permit_kedaluwarsa_hari_ini: [{ No_Transaksi: 'PMT-1', Nama_Pemohon: 'Frans', No_Plat_Kendaraan: 'BG 1102 AA' }],
    permit_segera_kedaluwarsa: [],
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
            return Promise.resolve({ data: { success: true, result: PAPAN_PERMIT } });
        }
        if (url.includes('analitik')) {
            return Promise.resolve({ data: { success: true, result: ANALITIK } });
        }

        return Promise.resolve({ data: { success: true, result: HALAMAN } });
    });
});

async function mountDashboard() {
    const wrapper = mount(PermitDashboard, {
        global: {
            stubs: {
                HcisChart: { name: 'HcisChart', props: ['title'], template: '<div class="hcis-chart-stub">{{ title }}</div>' },
            },
        },
    });
    await flushPromises();

    return wrapper;
}

describe('PermitDashboard', () => {
    it('meminta papan harian khusus domain permit', async () => {
        await mountDashboard();

        const panggilanPapan = get.mock.calls.find(([url]) => url.includes('papan-harian'));

        expect(panggilanPapan, 'papan harian tidak pernah dipanggil').toBeTruthy();
        expect(panggilanPapan[1]).toEqual({ params: { domain: 'permit' } });
    });

    it('tidak merender blok khusus tamu', async () => {
        const wrapper = await mountDashboard();

        expect(wrapper.findComponent({ name: 'DashboardBlokTren' }).exists()).toBe(false);
    });

    it('tidak menyebut ranah tamu sama sekali', async () => {
        // Cermin penjaga di sisi tamu: judul seksi yatim adalah bentuk kebocoran
        // yang paling mudah lolos karena daftarnya sudah kosong.
        const teks = (await mountDashboard()).text().toLowerCase();

        expect(teks).not.toContain('keadaan tamu');
        expect(teks).not.toContain('nama tamu');
    });

    it('tetap merender blok permit', async () => {
        const wrapper = await mountDashboard();

        expect(wrapper.findComponent({ name: 'DashboardBlokPermit' }).exists()).toBe(true);
    });

    it('meminta analitik khusus domain permit', async () => {
        await mountDashboard();

        const panggilan = get.mock.calls.find(([url]) => url.includes('analitik'));

        expect(panggilan, 'analitik tidak pernah dipanggil').toBeTruthy();
        expect(panggilan[1].params).toMatchObject({ domain: 'permit' });
    });

    it('tidak lagi menampilkan daftar "Akan kadaluarsa"', async () => {
        // Isinya sudah ada dua kali: tabel papan harian dan sub-tab All
        // Monitoring dengan saringan 30 hari. Kembalinya daftar itu ke sini
        // berarti satu data yang sama dirawat di tiga tempat.
        const teks = (await mountDashboard()).text().toLowerCase();

        expect(teks).not.toContain('akan kadaluarsa');
        expect(teks).toContain('tren permit');
    });

    it('meminta antrean keputusan khusus domain permit', async () => {
        await mountDashboard();

        const panggilan = get.mock.calls.find(([url]) => url.includes('laporanvisitor-antrian'));

        expect(panggilan, 'antrean approval tidak pernah dipanggil').toBeTruthy();
        expect(panggilan[1].params).toMatchObject({ domain: 'permit' });
    });

    it('tidak mengambil daftar tamu berhalaman sama sekali', async () => {
        // Pada ranah permit daftar itu tidak dirender; kalau tetap dikueri,
        // biayanya nyata sementara hasilnya dibuang.
        await mountDashboard();

        expect(get.mock.calls.some(([url]) => url.includes('papan-tamu'))).toBe(false);
    });
});
