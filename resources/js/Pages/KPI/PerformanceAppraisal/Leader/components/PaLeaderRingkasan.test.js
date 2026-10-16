import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Ringkasan from './PaLeaderRingkasan.vue';
import paApi from '../../api/paApi.js';

vi.mock('../../api/paApi.js', () => ({ default: { kemajuanPengirimanSaya: vi.fn() } }));

const stubs = {
    HcisIcon: { template: '<i />' },
    HcisButton: { props: ['label'], emits: ['click'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
};

const orang = (id, o = {}) => ({
    participant_id: id, nama: `Orang ${id}`, status_peserta: 'DIIKUTKAN', status_hasil: 'DIHITUNG',
    penugasan_total: 4, penugasan_selesai: 4, penugasan_terlambat: 0, progres_persen: 100,
    nilai_akhir: null, kode_grade: null, kode_kuadran: null, terbit: false, dirilis_ke_leader: false,
    alasan_tertahan: null, nilai_sebelum: null, grade_sebelum: null, ...o,
});

const tim = (anggota, tambahan = {}) => ({
    cycle: { cycle_id: 9 },
    anggota,
    tren: { cycle: { cycle_id: 8, kode_cycle: 'PA-LALU', nama_cycle: 'Siklus Lalu' }, rata_rata_sebelum: 75 },
    kamus_kuadran: [{ kode: 'STRIVING_STAR', nama: 'Striving Star', nomor_box: 1 }],
    ...tambahan,
});

const pasang = (t) => mount(Ringkasan, { props: { tim: t }, global: { stubs } });

describe('PaLeaderRingkasan', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.kemajuanPengirimanSaya.mockResolvedValue([{ cycle_id: 9, sisa: 0 }]);
    });

    it('angka hanya dari hasil yang terlihat leader; yang belum dirilis tidak ikut dihitung', async () => {
        const w = pasang(tim([
            orang(1, { terbit: true, nilai_akhir: 90, kode_grade: 'A', kode_kuadran: 'STRIVING_STAR', nilai_sebelum: 80 }),
            orang(2, { dirilis_ke_leader: true, nilai_akhir: 70, kode_grade: 'C', nilai_sebelum: 72 }),
            orang(3),
        ]));
        await flushPromises();
        expect(w.find('[data-uji="rata-tim"]').text()).toBe('80,00');
        expect(w.find('[data-uji="delta-rata"]').text()).toContain('+5,00');
        expect(w.text()).toContain('1 anggota lain belum dirilis');
        expect(w.find('[data-uji="hav-1"]').text()).toContain('1');
        // Tren: satu naik, satu turun; terlemah di atas.
        expect(w.text()).toContain('1 naik');
        expect(w.text()).toContain('1 turun');
    });

    it('tanpa kartu Perlu Perhatian; kartu ke-4 menghitung kompetensi di bawah KKM', async () => {
        const w = pasang(tim([orang(1, { terbit: true, nilai_akhir: 70, kode_grade: 'C' })], {
            kompetensi_tim: [
                { kode_kompetensi: 'K1', nama_kompetensi: 'Integritas', rata_rata: 88, nilai_ambang: 80, jumlah_orang: 1, anggota: [] },
                { kode_kompetensi: 'K2', nama_kompetensi: 'Analisis', rata_rata: 70, nilai_ambang: 80, jumlah_orang: 1, anggota: [] },
                { kode_kompetensi: 'K3', nama_kompetensi: 'Inovasi', rata_rata: 60, nilai_ambang: null, jumlah_orang: 1, anggota: [] },
            ],
        }));
        await flushPromises();
        expect(w.text()).not.toContain('Perlu Perhatian');
        expect(w.find('[data-uji^="perhatian-"]').exists()).toBe(false);
        // KKM belum ditetapkan tidak dihitung "di bawah KKM".
        expect(w.find('[data-uji="jumlah-bawah-kkm"]').text()).toMatch(/^1\s*\/\s*3$/);
        expect(paApi.kemajuanPengirimanSaya).not.toHaveBeenCalled();
    });

    it('rata-rata KPI & kompetensi tim hanya dari hasil yang terlihat', async () => {
        const w = pasang(tim([
            orang(1, { terbit: true, nilai_akhir: 80, kode_grade: 'B', nilai_kpi: 90, nilai_kompetensi: 70 }),
            orang(2, { dirilis_ke_leader: true, nilai_akhir: 84, kode_grade: 'B', nilai_kpi: 80, nilai_kompetensi: 76 }),
            orang(3, { nilai_kpi: null, nilai_kompetensi: null }),
        ]));
        await flushPromises();
        expect(w.find('[data-uji="rata-kpi"]').text()).toBe('85,00');
        expect(w.find('[data-uji="rata-kompetensi"]').text()).toBe('73,00');
    });

    it('tanpa kompetensi terlihat, bagian kompetensi menjelaskan sebabnya', async () => {
        const w = pasang(tim([orang(1)]));
        await flushPromises();
        expect(w.find('[data-uji="kompetensi-tim"]').text()).toContain('Belum ada nilai kompetensi yang terlihat');
    });

    it('tanpa siklus sebelumnya, tren menjelaskan sebabnya', async () => {
        const w = pasang(tim([orang(1, { terbit: true, nilai_akhir: 85, kode_grade: 'B' })], { tren: { cycle: null, rata_rata_sebelum: null } }));
        await flushPromises();
        expect(w.text()).toContain('Belum ada siklus sebelumnya');
    });
});
