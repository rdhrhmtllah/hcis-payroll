import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaLiquidProgressDialog from './PaLiquidProgressDialog.vue';

const stubs = {
    HcisDialog: { template: '<div><slot /><slot name="footer" /></div>' },
    HcisButton: { props: ['label'], template: '<button>{{ label }}</button>' },
    HcisAlert: { template: '<div />' },
    HcisIcon: { template: '<i />' },
};

const pasang = (props) => mount(PaLiquidProgressDialog, { props: { modelValue: true, ...props }, global: { stubs } });

describe('PaLiquidProgressDialog', () => {
    it('persen mengikuti jadwal TERKIRIM, bukan tautan — tidak diam di 8% selama dikirim', () => {
        const w = pasang({ status: 'running', phase: 'jadwal', total: 1000, terjadwal: 0, sisa: 600, antre: 400 });
        expect(w.find('.pa-liquid-num').text()).toBe('40');
    });

    it('sesudah semua terkirim: menunggu HC Learn sebagai tahap tersendiri, akses siap terpisah', () => {
        const w = pasang({ status: 'menunggu', phase: 'jadwal', total: 1140, terjadwal: 0, sisa: 0, antre: 1140, terakhirDicek: '10.15.02' });
        expect(w.find('.pa-liquid-num').text()).toBe('100');
        expect(w.text()).toContain('MENUNGGU HC LEARN');
        expect(w.text()).toContain('3. Respons HC Learn');
        expect(w.text()).toContain('Akses Siap');
        expect(w.text()).toContain('terakhir dicek 10.15.02');
        expect(w.text()).toContain('Cek Sekarang');
    });

    /* Tugas latar: pengirimannya milik server. Dialog yang terkunci selama "Mengirim" memaksa
       admin menatap layar untuk pekerjaan yang tetap berjalan tanpa dirinya. */
    it('tugas latar: bisa ditutup selama mengirim, dan menyebut panel Tugas & Aktivitas', async () => {
        const w = pasang({ status: 'running', phase: 'jadwal', total: 10000, sisa: 6000, antre: 4000, latar: true, ukuranBatch: 450, batchIndex: 9 });
        expect(w.text()).toContain('batch #9');
        expect(w.text()).toContain('450 rater per batch');
        expect(w.text()).toContain('Tugas & Aktivitas');

        const tutup = w.findAll('button').find((b) => b.text().includes('Tutup — Tetap Berjalan'));
        expect(tutup).toBeTruthy();
        await tutup.trigger('click');
        expect(w.emitted('update:modelValue')?.[0]).toEqual([false]);
    });

    it('bukan tugas latar: tidak ada tombol tutup selama mengirim', () => {
        const w = pasang({ status: 'running', phase: 'jadwal', total: 1000, sisa: 600 });
        expect(w.findAll('button').some((b) => b.text().includes('Tetap Berjalan'))).toBe(false);
    });
});
