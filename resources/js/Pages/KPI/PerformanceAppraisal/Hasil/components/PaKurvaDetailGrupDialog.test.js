import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Dialog from './PaKurvaDetailGrupDialog.vue';
import paApi from '../../api/paApi.js';

vi.mock('../../api/paApi.js', () => ({ default: { kurvaGrupDetail: vi.fn() } }));

const stubs = {
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue"><h2>{{ title }}</h2><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
    HcisIcon: { template: '<i />' },
    HcisSkeleton: { template: '<div class="sk" />' },
};

const detail = {
    grup: { id: 7, kode: 'STAFF', nama: 'Staff', level: ['STAFF', 'SENIOR STAFF'] },
    metode: 'KURVA', terkunci: false, peringatan: [], min_grup: 30,
    langkah: { n: 3, jumlah: 240, rata_mentah: 80, jumlah_kuadrat: 50, varians: 25, sd_mentah: 5, rata: 80, sd: 5, n_kurva: 3 },
    pita: [
        { kode: 'B_PLUS', nama: 'Sangat Baik', batas_z: 1, nilai_dari: 85, nilai_sampai: 999.99, jumlah: 1, batas_mentah: 85 },
        { kode: 'B', nama: 'Baik', batas_z: 0, nilai_dari: 80, nilai_sampai: 85, jumlah: 1, batas_mentah: 80 },
        { kode: 'C', nama: 'Kurang', batas_z: null, nilai_dari: 0, nilai_sampai: 80, jumlah: 1, batas_mentah: null },
    ],
    proporsi: { B_PLUS: 15.87, B: 34.13, C: 50 },
    anggota: [
        { participant_id: 1, nama: 'Andi', kode_karyawan: 'K1', level: 'STAFF', final: 85, selisih: 5, kuadrat: 25, z: 1, grade: 'B_PLUS' },
        { participant_id: 2, nama: 'Budi', kode_karyawan: 'K2', level: 'STAFF', final: 80, selisih: 0, kuadrat: 0, z: 0, grade: 'B' },
        { participant_id: 3, nama: 'Citra', kode_karyawan: 'K3', level: 'STAFF', final: 75, selisih: -5, kuadrat: 25, z: -1, grade: 'C' },
    ],
};

describe('PaKurvaDetailGrupDialog', () => {
    it('menampilkan simbol, langkah μ dan σ dengan angkanya, batas grade, dan anggota', async () => {
        paApi.kurvaGrupDetail.mockResolvedValue(detail);
        const w = mount(Dialog, { props: { modelValue: true, cycleId: 5, grupId: 7 }, global: { stubs } });
        await flushPromises();
        expect(paApi.kurvaGrupDetail).toHaveBeenCalledWith(5, 7);
        const t = w.text();
        expect(t).toContain('Keterangan Simbol');
        expect(t).toContain('Simpangan Baku Sampel');
        expect(t).toContain('μ = Σx ÷ n = 240,00 ÷ 3 = 80,00');
        expect(t).toContain('√25,00 = 5,00');
        expect(t).toContain('80,00 + 1,00 × 5,00 = 85,00');
        expect(t).toContain('B+');
        expect(t).toContain('Andi');
        expect(t).toContain('−5');
        expect(t).toContain('Jumlah Anggota Terbatas');
    });

    it('menampilkan grade berlaku hasil kalibrasi dan menyebut grade pita bila berbeda', async () => {
        const kalibrasi = {
            ...detail,
            anggota: [
                { ...detail.anggota[2], grade: 'B', grade_sistem: 'C', grade_final: 'B' },
                { ...detail.anggota[1], grade: 'B', grade_sistem: 'B', grade_final: 'B' },
            ],
        };
        paApi.kurvaGrupDetail.mockResolvedValue(kalibrasi);
        const w = mount(Dialog, { props: { modelValue: true, cycleId: 5, grupId: 7 }, global: { stubs } });
        await flushPromises();

        const catatan = w.findAll('[data-uji="grade-sistem"]');
        expect(catatan).toHaveLength(1);
        expect(catatan[0].text()).toContain('pita');
    });

    it('pencarian anggota menyaring daftar', async () => {
        paApi.kurvaGrupDetail.mockResolvedValue(detail);
        const w = mount(Dialog, { props: { modelValue: true, cycleId: 5, grupId: 7 }, global: { stubs } });
        await flushPromises();
        await w.find('input').setValue('citra');
        expect(w.text()).toContain('Citra');
        expect(w.text()).not.toContain('Budi');
    });
});
