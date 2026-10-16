import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import WizardPenjadwalan from './WizardPenjadwalan.vue';

function buatPaket(jumlah) {
    return Array.from({ length: jumlah }, (_, i) => ({
        Id_SOP_Paket: `paket-${i}`,
        Nama_Paket: `Paket ${String(i).padStart(3, '0')}`,
        Mode_Distribusi: 'PUBLIK',
        Prioritas: 'NORMAL',
        Nama_Kategori: 'HSE',
        Versi_Terakhir: 1,
    }));
}

function pasang(props = {}) {
    return mount(WizardPenjadwalan, {
        props: { modelValue: true, paketOptions: buatPaket(200), ...props },
        global: {
            stubs: { 'el-drawer': { template: '<div><slot /></div>' } },
        },
    });
}

describe('WizardPenjadwalan langkah 1', () => {
    it('membatasi jumlah kartu yang dirender walau opsi paket jauh lebih banyak', () => {
        const wrapper = pasang();

        expect(wrapper.findAll('.wz-paket')).toHaveLength(60);
    });

    it('memberi tahu berapa paket yang belum ditampilkan', () => {
        const wrapper = pasang();

        const catatan = wrapper.find('.wz-render-note');
        expect(catatan.exists()).toBe(true);
        expect(catatan.text()).toContain('60');
        expect(catatan.text()).toContain('200');
    });

    it('tidak menampilkan catatan saat semua paket muat dirender', () => {
        const wrapper = pasang({ paketOptions: buatPaket(10) });

        expect(wrapper.findAll('.wz-paket')).toHaveLength(10);
        expect(wrapper.find('.wz-render-note').exists()).toBe(false);
    });

    it('mempersempit kartu mengikuti pencarian sebelum batas render diterapkan', async () => {
        const wrapper = pasang();

        await wrapper.setData({ cariPaket: 'Paket 01' });

        // Paket 010..019 => 10 hasil, jauh di bawah batas render.
        expect(wrapper.findAll('.wz-paket')).toHaveLength(10);
        expect(wrapper.find('.wz-render-note').exists()).toBe(false);
    });

    it('memperingatkan saat server memotong daftar paket', () => {
        const wrapper = pasang({ paketOptions: buatPaket(10), paketTerpotong: true });

        expect(wrapper.find('.wz-server-note').exists()).toBe(true);
    });

    it('tidak memperingatkan saat server mengirim daftar utuh', () => {
        const wrapper = pasang({ paketOptions: buatPaket(10), paketTerpotong: false });

        expect(wrapper.find('.wz-server-note').exists()).toBe(false);
    });
});
