import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SopTugasCard from './SopTugasCard.vue';

describe('SopTugasCard.vue', () => {
    const dummyTugas = {
        Id_SOP_Paket: 101,
        Nama_Paket: 'Prosedur Keamanan Data',
        Prioritas: 'KRITIS',
        Persen: 75,
        Item_Selesai: 3,
        Total_Item: 4,
        Tenggat: '2026-12-31',
        Is_Terlambat: false,
        Is_Diperbarui: false,
        Cover_Url: 'https://example.com/cover.jpg',
    };

    const dept = (id, nama) => ({ id, nama, kode: null, level: null });

    function pasang(props = {}) {
        return mount(SopTugasCard, {
            props: {
                tugas: dummyTugas,
                ...props,
            },
            global: {
                stubs: {
                    HcisButton: {
                        props: ['label', 'href', 'variant', 'size', 'block'],
                        template: '<a class="stub-btn" :href="href">{{ label }}</a>',
                    },
                    HcisProgress: {
                        props: ['value', 'label', 'sentiment', 'size'],
                        template: '<div class="stub-progress" :data-val="value" :data-sentiment="sentiment">{{ label }}</div>',
                    },
                    StatusPill: true,
                },
            },
        });
    }

    it('merender judul paket dengan benar', () => {
        const w = pasang();
        expect(w.text()).toContain('Prosedur Keamanan Data');
    });

    it('merender HcisProgress dengan nilai dan label item', () => {
        const w = pasang();
        const prog = w.find('.stub-progress');
        expect(prog.exists()).toBe(true);
        expect(prog.attributes('data-val')).toBe('75');
        expect(prog.text()).toContain('3/4 item selesai');
    });

    it('merender HcisButton dengan tautan detail bawaan', () => {
        const w = pasang();
        const btn = w.find('.stub-btn');
        expect(btn.exists()).toBe(true);
        expect(btn.attributes('href')).toBe('/sop/101');
    });

    it('mendukung custom href dan actionLabel untuk mode admin', () => {
        const w = pasang({
            href: '/sop/admin/monitoring/karyawan/tok/paket/101',
            actionLabel: 'Detail',
            mode: 'admin',
        });
        const btn = w.find('.stub-btn');
        expect(btn.attributes('href')).toBe('/sop/admin/monitoring/karyawan/tok/paket/101');
        expect(btn.text()).toBe('Detail');
    });

    it('menampilkan badge revisi jika ada item perlu ulang', () => {
        const w = pasang({
            tugas: { ...dummyTugas, Item_Perlu_Ulang: 2 },
        });
        expect(w.text()).toContain('2 Revisi');
    });

    it('memancarkan peristiwa open saat kartu diklik', async () => {
        const w = pasang();
        await w.find('.tg-card').trigger('click');
        expect(w.emitted('open')).toBeTruthy();
        expect(w.emitted('open')[0][0]).toEqual(dummyTugas);
    });

    /*
     * Lencana kepemilikan. Kartu ini dipakai DUA halaman: SOP Saya (yang
     * mengirim medan Departemen) dan Monitoring Karyawan (yang tidak). Karena
     * itu yang diuji bukan hanya "muncul saat ada", tetapi juga "tidak muncul
     * saat medannya memang tak dikirim" — sebuah chip berisi nilai cadangan di
     * halaman kedua akan menyatakan sesuatu yang tak pernah diperiksa siapa pun.
     */
    describe('lencana departemen pemilik', () => {
        it('menyebut pemiliknya bila paket memuat dokumen bertuan', () => {
            const w = pasang({ tugas: { ...dummyTugas, Departemen: [dept(1, 'HSE')] } });

            expect(w.find('.tg-chip-dept').text()).toContain('HSE');
        });

        it('memendekkan jadi "+n" dan menyebut semuanya di tooltip', () => {
            const w = pasang({ tugas: { ...dummyTugas, Departemen: [dept(1, 'HSE'), dept(2, 'HR')] } });

            expect(w.find('.tg-chip-dept').text()).toContain('+1');
            expect(w.find('.tg-chip-dept').attributes('title')).toContain('HR');
        });

        it('tidak muncul sama sekali bila pemanggil tak mengirim medannya', () => {
            expect(pasang().find('.tg-chip-dept').exists()).toBe(false);
        });
    });
});
