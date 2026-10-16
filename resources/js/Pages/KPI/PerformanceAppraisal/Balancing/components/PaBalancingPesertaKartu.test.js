import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Kartu from './PaBalancingPesertaKartu.vue';

const stubs = {
    HcisBadge: { props: ['label'], template: '<span class="badge">{{ label }}</span>' },
    HcisIcon: { template: '<i />' },
    HcisRichText: { props: ['modelValue', 'label'], emits: ['update:modelValue'], template: '<textarea class="rt" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
};

const peserta = () => ({
    participant_id: 7, nama: 'Andi', kode_karyawan: 'K7', level: 'Staff', status_tinjau: 'BELUM', alasan: null,
    sebelum: { nilai_kpi: 90, nilai_kompetensi: 77.5, final: 83.75, grade: 'B', kuadran_id: 4 },
    kompetensi: [
        { competency_id: 1, nama: 'Integritas', deskripsi: 'Jujur', bobot: 0.5, sebelum: 70, sesudah: 70, alasan: null },
        { competency_id: 2, nama: 'Kerja sama', deskripsi: '', bobot: 0.5, sebelum: 85, sesudah: 85, alasan: null },
    ],
    kualitatif: [{ competency_id: 21, nama: 'Strength', sebelum: '<p>Teliti</p>', sesudah: '<p>Teliti</p>' }],
});

describe('PaBalancingPesertaKartu', () => {
    it('menampilkan bobot sebagai persen dan Before', () => {
        const w = mount(Kartu, { props: { peserta: peserta(), bisaUbah: true, terbuka: true }, global: { stubs } });
        expect(w.text()).toContain('Integritas');
        expect(w.text()).toContain('50%');
        expect(w.text()).toContain('70,00');
    });

    it('mengubah After mewajibkan SATU alasan per orang dan memancarkan ubah', async () => {
        const p = peserta();
        const w = mount(Kartu, { props: { peserta: p, bisaUbah: true, terbuka: true }, global: { stubs } });
        await w.find('[data-uji="after-1"]').setValue('80');
        expect(p.kompetensi[0].sesudah).toBe(80);
        // Tidak ada lagi kotak alasan per kompetensi.
        expect(w.find('[data-uji="alasan-1"]').exists()).toBe(false);
        expect(w.find('.pa-bal-alasan-orang').classes()).toContain('is-wajib');
        expect(w.text()).toContain('alasan perubahan wajib diisi');

        await w.find('[data-uji="alasan-peserta"]').setValue('Kesepakatan forum');
        expect(p.alasan).toBe('Kesepakatan forum');
        expect(w.find('.pa-bal-alasan-orang').classes()).not.toContain('is-wajib');
        expect(w.emitted('ubah')).toBeTruthy();
    });

    it('tanpa perubahan bisa ditandai "Tanpa perubahan"', async () => {
        const p = peserta();
        const w = mount(Kartu, { props: { peserta: p, bisaUbah: true, terbuka: true }, global: { stubs } });
        await w.find('[data-uji="tanpa-perubahan"]').setValue(true);
        expect(p.status_tinjau).toBe('TANPA_PERUBAHAN');
    });

    it('baca-saja tanpa input', () => {
        const w = mount(Kartu, { props: { peserta: peserta(), bisaUbah: false, terbuka: true }, global: { stubs } });
        expect(w.find('input[type="number"]').exists()).toBe(false);
        expect(w.find('.rt').exists()).toBe(false);
    });
});
