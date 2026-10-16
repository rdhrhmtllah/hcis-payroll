import { afterEach, describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { useElementBridge, _jumlahPemakaiJembatan } from './useElementBridge';

const Pengadopsi = defineComponent({
    setup() {
        useElementBridge();
        return () => h('div');
    },
});

const penanda = () => document.documentElement.getAttribute('data-hcis-el');

afterEach(() => {
    document.documentElement.removeAttribute('data-hcis-el');
});

describe('useElementBridge', () => {
    it('menyalakan penanda di <html>, bukan di akar halaman', () => {
        // Harus di <html> supaya menjangkau elemen yang di-teleport Element ke
        // body — dropdown, popper tanggal, message. Penanda di akar halaman
        // membuat isian tergaya tapi dropdown yang terbuka darinya tidak.
        const w = mount(Pengadopsi);
        expect(penanda()).not.toBeNull();
        w.unmount();
    });

    it('melepas penanda saat komponennya dilepas', () => {
        // Kalau tertinggal, halaman BERIKUTNYA yang belum diperiksa ikut
        // memakai jembatan — perpindahan tak sengaja yang justru dihindari.
        const w = mount(Pengadopsi);
        w.unmount();
        expect(penanda()).toBeNull();
    });

    it('dua pengadopsi: yang pertama dilepas tidak mematikan jembatan', () => {
        const a = mount(Pengadopsi);
        const b = mount(Pengadopsi);

        a.unmount();
        expect(penanda(), 'jembatan mati padahal masih ada pemakai').not.toBeNull();

        b.unmount();
        expect(penanda()).toBeNull();
    });

    it('penghitungnya kembali nol setelah semua dilepas', () => {
        const a = mount(Pengadopsi);
        const b = mount(Pengadopsi);
        a.unmount();
        b.unmount();
        expect(_jumlahPemakaiJembatan()).toBe(0);
    });
});
