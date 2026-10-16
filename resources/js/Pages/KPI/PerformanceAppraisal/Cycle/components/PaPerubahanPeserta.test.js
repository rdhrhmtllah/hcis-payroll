import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaPerubahanPeserta from './PaPerubahanPeserta.vue';

/*
 | Menjalankan ulang Langkah 4 tidak pernah menyentuh penugasan yang sudah ada. Panel ini
 | yang menyebut sisanya — dan tiap barisnya harus membawa tindakan yang benar.
 */

const paApiMock = vi.hoisted(() => ({
    perubahanPeserta: vi.fn(),
    segarkanLevelTertahan: vi.fn(),
    tambahPraKecuali: vi.fn(),
}));

vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const stubs = {
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisDialog: { props: ['modelValue', 'title'], template: '<div v-if="modelValue" class="dialog"><slot /><slot name="footer" /></div>' },
    HcisIcon: { template: '<i />' },
};

const data = (ganti = {}) => ({
    dapat_ditindak: true,
    ada_penugasan: true,
    belum_berpenugasan: 0,
    belum_diterapkan: { karyawan_baru: 0, level_berubah: 0 },
    level_tertahan: [],
    resign: [],
    ...ganti,
});

const tertahan = (id, bisa = true) => ({
    participant_id: id,
    nama: `Orang ${id}`,
    kode_karyawan: `K${id}`,
    nama_level_sekarang: 'OFFICER',
    nama_level_seharusnya: 'SENIOR OFFICER',
    sebab: 'Sudah punya penugasan penilai.',
    penugasan_aktif: 6,
    penugasan_diisi: bisa ? 0 : 2,
    bisa_disegarkan: bisa,
});

const pasang = (props = {}) =>
    mount(PaPerubahanPeserta, {
        props: { cycleId: 9, langkah: 'PENUGASAN', dapatDisunting: true, bolehKecualikan: true, ...props },
        global: { stubs },
    });

const tombol = (w, teks) => w.findAll('button').find((b) => b.text().includes(teks));

describe('PaPerubahanPeserta', () => {
    beforeEach(() => {
        Object.values(paApiMock).forEach((fn) => fn.mockReset());
    });

    it('menyatakan "tidak ada perubahan" di Langkah 5, dan diam di Langkah 4', async () => {
        paApiMock.perubahanPeserta.mockResolvedValue(data());
        const l5 = pasang();
        await flushPromises();
        expect(l5.find('[data-uji="perubahan-kosong"]').exists()).toBe(true);

        const l4 = pasang({ langkah: 'PESERTA' });
        await flushPromises();
        expect(l4.find('[data-uji="perubahan-peserta"]').exists()).toBe(false);
    });

    it('membatalkan penugasan & menyegarkan hanya peserta yang bisa, sesudah konfirmasi', async () => {
        paApiMock.perubahanPeserta.mockResolvedValue(data({ level_tertahan: [tertahan(1), tertahan(2, false)] }));
        paApiMock.segarkanLevelTertahan.mockResolvedValue({ disegarkan: 1, penugasan_dibatalkan: 6, dilewati: [] });
        const w = pasang();
        await flushPromises();

        const baris = w.find('[data-uji="perubahan-level-tertahan"]');
        expect(baris.text()).toContain('2 peserta berubah Level PA tetapi tertahan');
        expect(baris.text()).toContain('OFFICER → SENIOR OFFICER');
        expect(baris.text()).toContain('sudah ada yang diisi');

        await tombol(w, 'Batalkan penugasan & segarkan level (1)').trigger('click');
        expect(w.find('[data-uji="perubahan-konfirmasi"]').text()).toContain('6 penugasan milik 1 peserta');
        await w.find('[data-uji="perubahan-konfirmasi-ya"]').trigger('click');
        await flushPromises();

        expect(paApiMock.segarkanLevelTertahan).toHaveBeenCalledWith(9, [1]);
        expect(w.text()).toContain('1 peserta disegarkan, 6 penugasan dibatalkan.');
        expect(w.emitted('berubah')).toHaveLength(1);
    });

    it('mengecualikan peserta yang resign lewat daftar pengecualian', async () => {
        paApiMock.perubahanPeserta.mockResolvedValue(data({
            resign: [{ participant_id: 5, kode_perusahaan: '001', kode_karyawan: 'R5', nama: 'Rina', keterangan: 'Tidak aktif lagi di HRIS', penugasan_aktif: 4, penugasan_diisi: 1 }],
        }));
        paApiMock.tambahPraKecuali.mockResolvedValue({ peserta_dikecualikan: 1 });
        const w = pasang({ langkah: 'PESERTA' });
        await flushPromises();

        await tombol(w, 'Kecualikan yang resign (1)').trigger('click');
        expect(w.find('[data-uji="perubahan-konfirmasi"]').text()).toContain('yang sudah diisi (1 orang) tetap tercatat');
        await w.find('[data-uji="perubahan-konfirmasi-ya"]').trigger('click');
        await flushPromises();

        expect(paApiMock.tambahPraKecuali).toHaveBeenCalledWith(9, [{ kode_perusahaan: '001', kode_karyawan: 'R5' }], expect.stringContaining('Resign'));
    });

    it('peserta tanpa penugasan: Langkah 5 melengkapi, Langkah 4 menunjuk ke Langkah 5', async () => {
        paApiMock.perubahanPeserta.mockResolvedValue(data({ belum_berpenugasan: 3 }));
        const l5 = pasang();
        await flushPromises();
        await tombol(l5, 'Lengkapi penugasan').trigger('click');
        expect(l5.emitted('lengkapi')).toHaveLength(1);

        const l4 = pasang({ langkah: 'PESERTA' });
        await flushPromises();
        await tombol(l4, 'Ke Langkah 5').trigger('click');
        expect(l4.emitted('ke-langkah-5')).toHaveLength(1);
    });

    it('siklus terkunci: tanpa tombol yang mengubah penugasan', async () => {
        paApiMock.perubahanPeserta.mockResolvedValue(data({ dapat_ditindak: false, belum_berpenugasan: 3, level_tertahan: [tertahan(1)] }));
        const w = pasang({ dapatDisunting: false, bolehKecualikan: true });
        await flushPromises();
        expect(tombol(w, 'Lengkapi penugasan')).toBeFalsy();
        expect(tombol(w, 'Batalkan penugasan')).toBeFalsy();
    });
});
