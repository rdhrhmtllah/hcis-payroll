import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Panel from './PaBalancingSimulasiPanel.vue';

const stubs = {
    HcisBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
    HcisSkeleton: { template: '<div class="sk" />' },
    HcisIcon: { template: '<i />' },
    HcisAvatar: { template: '<i />' },
    HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
    HcisCard: { template: '<div class="kartu"><slot name="title" /><slot name="badge" /><slot /></div>' },
};
const baris = (pid, nama, gradeLama, gradeBaru, sebab, kLama = 'K4', kBaru = 'K4', fLama = 80, fBaru = 85) => ({
    participant_id: pid, nama,
    sebelum: { final: fLama, grade: gradeLama, kode_kuadran: kLama, nilai_kompetensi: 77.5 },
    sesudah: { final: fBaru, grade: gradeBaru, kode_kuadran: kBaru, nilai_kompetensi: 82.5 }, sebab,
});
const kuadran = [1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => ({ Nomor_Box: n, Kode_Kuadran: `K${n}`, Nama_Kuadran: `Kotak ${n}`, Tingkat_Kompetensi: 'Tinggi', Tingkat_Performa: 'Tinggi' }));
const pita = (rata) => [
    { kode: 'A', nilai_dari: rata + 15, nilai_sampai: 1000, batas_z: 1.5 },
    { kode: 'B', nilai_dari: rata, nilai_sampai: rata + 15, batas_z: 0 },
    { kode: 'C', nilai_dari: 0, nilai_sampai: rata, batas_z: null },
];
const hasil = () => ({
    metode: 'KURVA',
    peserta: [baris(1, 'Andi', 'B', 'A', 'BALANCING', 'K4', 'K1')],
    terdampak: [baris(2, 'Budi', 'B', 'C', 'KURVA', 'K4', 'K4', 80, 80)],
    grade: [{ kode: 'A', nama: 'A' }, { kode: 'B', nama: 'B' }, { kode: 'C', nama: 'C' }],
    semua: [
        { participant_id: 1, nama: 'Andi', sesi: true, final_sebelum: 80, final_sesudah: 85, grade_sebelum: 'B', grade_sesudah: 'A', kuadran_sebelum: 'K4', kuadran_sesudah: 'K1' },
        { participant_id: 2, nama: 'Budi', sesi: false, final_sebelum: 80, final_sesudah: 80, grade_sebelum: 'B', grade_sesudah: 'C', kuadran_sebelum: 'K4', kuadran_sesudah: 'K4' },
    ],
    kurva: [{
        id: 9, kode: 'LV4', nama: 'Officer', level: ['OFFICER'], proporsi: { A: 6.68, B: 43.32, C: 50 },
        sebelum: { n: 2, rata: 80, sd: 5, pita: pita(80), sebaran: { A: 0, B: 2, C: 0 } },
        sesudah: { n: 2, rata: 82.5, sd: 4, pita: pita(82.5), sebaran: { A: 1, B: 0, C: 1 } },
        anggota: [
            { participant_id: 1, nama: 'Andi', sesi: true, sebelum: 80, sesudah: 85, grade_sebelum: 'B', grade_sesudah: 'A' },
            { participant_id: 2, nama: 'Budi', sesi: false, sebelum: 80, sesudah: 80, grade_sebelum: 'B', grade_sesudah: 'C' },
        ],
    }],
});
const pasang = (h = hasil()) => mount(Panel, { props: { hasil: h, kuadran, memuat: false }, global: { stubs } });

describe('PaBalancingSimulasiPanel', () => {
    it('ringkasan, perubahan peserta, dan yang terdampak di luar sesi', () => {
        const w = pasang();
        expect(w.text()).toContain('SIMULASI — belum tersimpan');
        expect(w.text()).toContain('Andi');
        expect(w.text()).toContain('Penyesuaian');
        expect(w.text()).toContain('Di luar sesi, ikut bergeser (1)');
        expect(w.text()).toContain('Budi');
        // Rata-rata Final sesi 80 → 85 dengan selisih +5,00.
        expect(w.text()).toContain('+5,00');
    });

    it('HAV map memuat seluruh peserta siklus, menandai yang pindah kotak', async () => {
        const w = pasang();
        await w.findAll('.pa-bal-sim-tab button')[1].trigger('click');
        const kotak = w.findAll('.pa-bal-hv__kotak');
        expect(kotak).toHaveLength(9);
        // Sesudah: Andi di Kotak 1 (pindah dari 4), Budi tetap di Kotak 4.
        expect(w.find('.pa-bal-hv__daftar li.is-pindah').text()).toContain('dari 4');
        await w.find('.pa-bal-sim-centang input').setValue(true);
        expect(w.find('.pa-bal-hv__grid').text()).not.toContain('Budi');
        expect(w.find('.pa-bal-hv__grid').text()).toContain('Andi');
    });

    it('kurva normal menjelaskan pergeseran μ, batas grade, dan siapa yang bergeser', async () => {
        const w = pasang();
        await w.findAll('.pa-bal-sim-tab button')[2].trigger('click');
        expect(w.find('svg.pa-bal-kv__grafik').exists()).toBe(true);
        const teks = w.text();
        // μ dan batas grade sebelum → sesudah, dan kedua orang yang grade-nya bergeser beserta sebabnya.
        expect(teks).toContain('82,50');
        expect(teks).toContain('80,00');
        expect(teks).toContain('Nilai diubah di sesi');
        expect(teks).toContain('Terdampak pergeseran batas kurva grup');
    });

    it('metode pita menampilkan sebaran grade, bukan kurva', async () => {
        const w = pasang({ ...hasil(), metode: 'PITA', kurva: [] });
        await w.findAll('.pa-bal-sim-tab button')[2].trigger('click');
        expect(w.find('svg.pa-bal-kv__grafik').exists()).toBe(false);
        expect(w.findAll('.pa-bal-dist__baris')).toHaveLength(3);
    });
});
