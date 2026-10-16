import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PaRevisiPesertaDialog from './PaRevisiPesertaDialog.vue';

/*
 | Revisi peserta sesudah dikunci: server yang memutuskan tindakan mana yang boleh. Yang dijaga
 | di sini: tindakan yang tidak boleh MATI beserta sebabnya, alasan wajib, dan hasilnya disebut.
 */

const paApiMock = vi.hoisted(() => ({
    cariKaryawanPraKecuali: vi.fn(),
    pratinjauRevisiPeserta: vi.fn(),
    jalankanRevisiPeserta: vi.fn(),
}));
vi.mock('../../api/paApi.js', () => ({ default: paApiMock }));

const stubs = {
    HcisDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /><slot name="footer" /></div>' },
    HcisButton: {
        props: ['label', 'disabled', 'loading'],
        emits: ['click'],
        template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}</button>',
    },
    HcisField: { props: ['modelValue'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    HcisTextarea: { props: ['modelValue'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    HcisAlert: { props: ['title', 'description'], template: '<div>{{ title }} {{ description }}<slot /></div>' },
    HcisIcon: { template: '<i />' },
};

const pratinjau = (ganti = {}) => ({
    status_siklus: 'LOCKED',
    peserta: { id: 9, nama: 'Iqbal', status: 'DIIKUTKAN', id_level: 4, nama_level: 'OFFICER' },
    karyawan: { nama: 'Iqbal', id_level: 5, nama_level: 'SENIOR OFFICER' },
    dampak: { penugasan_aktif: 6, penugasan_diisi: 0, jadwal_hclearn: 6 },
    tindakan: {
        TERAPKAN_LEVEL: { boleh: true, sebab: null },
        IKUTKAN_KEMBALI: { boleh: false, sebab: 'Peserta ini tidak sedang dikecualikan.' },
        TAMBAH_SUSULAN: { boleh: false, sebab: 'Karyawan ini sudah menjadi peserta (DIIKUTKAN).' },
        KECUALIKAN: { boleh: true, sebab: null },
    },
    ...ganti,
});

describe('PaRevisiPesertaDialog', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        Object.values(paApiMock).forEach((f) => f.mockReset());
        paApiMock.cariKaryawanPraKecuali.mockResolvedValue({ baris: [{ kode_perusahaan: '001', kode_karyawan: 'K32', nama: 'Iqbal' }] });
        paApiMock.pratinjauRevisiPeserta.mockResolvedValue(pratinjau());
        paApiMock.jalankanRevisiPeserta.mockResolvedValue({ dibatalkan: 6, dibentuk: 6, penjadwalan: 'pa-jdw-1', catatan: 'Penugasan dibentuk; penjadwalan ke HC Learn berjalan di latar (Tugas & Aktivitas).' });
    });

    it('cari → pratinjau → terapkan level beralasan → hasil disebut', async () => {
        const w = mount(PaRevisiPesertaDialog, { props: { modelValue: true, cycleId: 77 }, global: { stubs } });

        await w.find('input').setValue('Iqbal');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="revisi-pilih-K32"]').trigger('click');
        await flushPromises();

        expect(paApiMock.pratinjauRevisiPeserta).toHaveBeenCalledWith(77, '001', 'K32');
        expect(w.find('[data-uji="revisi-keadaan"]').text()).toContain('Level PA terbaru: SENIOR OFFICER');

        /* Yang tidak boleh MATI dengan sebabnya; satu-satunya yang boleh langsung terpilih. */
        const ikut = w.find('[data-uji="revisi-opsi-IKUTKAN_KEMBALI"]');
        expect(ikut.attributes('disabled')).toBeDefined();
        expect(ikut.text()).toContain('tidak sedang dikecualikan');
        expect(w.find('[data-uji="revisi-opsi-TAMBAH_SUSULAN"]').exists()).toBe(false);

        const jalan = () => w.findAll('button').find((b) => b.text() === 'Jalankan Revisi');
        expect(jalan().attributes('disabled')).toBeDefined();
        await w.find('textarea').setValue('Koreksi level dari HR');
        expect(jalan().attributes('disabled')).toBeUndefined();

        await jalan().trigger('click');
        await flushPromises();

        expect(paApiMock.jalankanRevisiPeserta).toHaveBeenCalledWith(77, {
            tindakan: 'TERAPKAN_LEVEL', kode_perusahaan: '001', kode_karyawan: 'K32', alasan: 'Koreksi level dari HR',
        });
        expect(w.find('[data-uji="revisi-hasil"]').text()).toContain('6 penugasan dibatalkan · 6 penugasan dibentuk');
        expect(w.emitted('selesai')).toBeTruthy();
        vi.useRealTimers();
    });

    /* Kecualikan tersedia di dialog yang sama, tetapi TIDAK terpilih otomatis. */
    it('menawarkan Kecualikan tanpa memilihnya otomatis, dan menyebut dampaknya', async () => {
        paApiMock.pratinjauRevisiPeserta.mockResolvedValue(pratinjau({
            tindakan: {
                TERAPKAN_LEVEL: { boleh: false, sebab: 'Level PA-nya sudah sesuai (OFFICER).' },
                IKUTKAN_KEMBALI: { boleh: false, sebab: 'Peserta ini tidak sedang dikecualikan.' },
                TAMBAH_SUSULAN: { boleh: false, sebab: 'sudah peserta' },
                KECUALIKAN: { boleh: true, sebab: null },
            },
            dampak: { penugasan_aktif: 6, penugasan_diisi: 2, jadwal_hclearn: 4 },
        }));
        paApiMock.jalankanRevisiPeserta.mockResolvedValue({ tindakan: 'KECUALIKAN', dibatalkan: 4, dibentuk: 0, penjadwalan: null, catatan: 'Peserta dikecualikan.' });
        const w = mount(PaRevisiPesertaDialog, { props: { modelValue: true, cycleId: 77 }, global: { stubs } });

        await w.find('input').setValue('Dimas');
        await vi.advanceTimersByTimeAsync(400);
        await flushPromises();
        await w.find('[data-uji="revisi-pilih-K32"]').trigger('click');
        await flushPromises();

        const kecuali = w.find('[data-uji="revisi-opsi-KECUALIKAN"]');
        expect(kecuali.text()).toContain('4 penugasan yang belum diisi dibatalkan');
        expect(kecuali.text()).toContain('2 yang sudah diisi tetap tercatat');
        expect(w.find('textarea').exists()).toBe(false);

        await kecuali.trigger('click');
        await w.find('textarea').setValue('Resign per 1 Okt');
        await w.findAll('button').find((b) => b.text() === 'Jalankan Revisi').trigger('click');
        await flushPromises();

        expect(paApiMock.jalankanRevisiPeserta.mock.calls[0][1].tindakan).toBe('KECUALIKAN');
        expect(w.find('[data-uji="revisi-hasil"]').text()).toContain('4 penugasan dibatalkan');
        expect(w.find('[data-uji="revisi-hasil"]').text()).not.toContain('dibentuk');
        vi.useRealTimers();
    });
});
