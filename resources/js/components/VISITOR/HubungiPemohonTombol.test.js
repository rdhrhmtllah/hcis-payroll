import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import HubungiPemohonTombol from './HubungiPemohonTombol.vue';

const KONTAK = [
    { nama: 'Jaka', keterangan: 'Tamu · yang datang ke pos', no_hp: '+62 853', tautan_wa: 'https://wa.me/62853?text=Halo%20Jaka', tautan_telepon: 'tel:+62853' },
    { nama: 'Ridho', keterangan: 'Karyawan PIC / pengaju', no_hp: '0852', tautan_wa: 'https://wa.me/62852?text=Halo%20Ridho', tautan_telepon: 'tel:+62852' },
];

/** Tombol "Hubungi" untuk SHE: pemohon/tamu yang terlambat ke jadwal inspeksi. */
describe('HubungiPemohonTombol', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('tanpa kontak yang bisa dihubungi tidak dirender', () => {
        const w = mount(HubungiPemohonTombol, { props: { kontak: [{ nama: 'X', tautan_wa: null, tautan_telepon: null }] } });
        expect(w.find('[data-testid="hubungi-pemohon"]').exists()).toBe(false);
    });

    it('membuka dialog berisi kartu kontak dengan tautan WhatsApp berpesan', async () => {
        const w = mount(HubungiPemohonTombol, { props: { kontak: KONTAK, judul: 'Hubungi tamu' }, attachTo: document.body });
        const tombol = w.find('[data-testid="hubungi-pemohon"]');
        expect(tombol.attributes('aria-label')).toContain('Jaka, Ridho');

        await tombol.trigger('click');
        const dialog = w.findComponent({ name: 'HcisDialog' });
        expect(dialog.props('modelValue')).toBe(true);
        expect(dialog.props('title')).toBe('Hubungi tamu');
        expect(w.findComponent({ name: 'KontakBantuanDaftar' }).props('kontak').map((k) => k.nama)).toEqual(['Jaka', 'Ridho']);
        w.unmount();
    });
});
