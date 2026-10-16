import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import KontakBantuanTombol from './KontakBantuanTombol.vue';
import KontakBantuanDaftar from './KontakBantuanDaftar.vue';

const KONTAK = {
    nama: 'Admin Permit SHE',
    keterangan: 'SHE Department',
    no_hp: '0812-3456-7890',
    email: 'permit@contoh.id',
    jam_layanan: 'Senin–Jumat 08.00–16.00',
    tautan_wa: 'https://wa.me/6281234567890',
    tautan_telepon: 'tel:+6281234567890',
    tautan_email: 'mailto:permit@contoh.id',
};

// Dialog di-stub supaya isinya dirender di tempat (bukan teleport ke body).
const stubs = {
    HcisDialog: {
        props: ['modelValue', 'title', 'description'],
        template: '<div v-if="modelValue" data-testid="dialog"><h2>{{ title }}</h2><p>{{ description }}</p><slot /><slot name="footer" /></div>',
    },
};

describe('KontakBantuanTombol', () => {
    it('berupa tombol berjudul + subjudul; dialog baru terbuka saat diklik', async () => {
        const w = mount(KontakBantuanTombol, {
            props: { kontak: [KONTAK], subjudul: 'Hubungi admin permit', judulDialog: 'Bantuan permit' },
            global: { stubs },
        });

        const tombol = w.find('[data-testid="kontak-bantuan-tombol"]');
        expect(tombol.text()).toContain('Butuh bantuan?');
        expect(tombol.text()).toContain('Hubungi admin permit');
        expect(w.find('[data-testid="dialog"]').exists()).toBe(false);

        await tombol.trigger('click');

        expect(w.find('[data-testid="dialog"]').text()).toContain('Bantuan permit');
        expect(w.find('[data-testid="kontak-bantuan-daftar"]').text()).toContain('Admin Permit SHE');
    });

    it('mode ringkas hanya menampilkan judul', () => {
        const w = mount(KontakBantuanTombol, { props: { kontak: [KONTAK], ringkas: true, subjudul: 'Sub' }, global: { stubs } });
        expect(w.find('[data-testid="kontak-bantuan-tombol"]').text()).not.toContain('Sub');
    });

    it('tidak dirender bila belum ada kontak yang bisa dihubungi', () => {
        expect(mount(KontakBantuanTombol, { props: { kontak: [] }, global: { stubs } }).html()).not.toContain('kontak-bantuan-tombol');
        const tanpaSaluran = { ...KONTAK, tautan_wa: null, tautan_telepon: null, tautan_email: null };
        expect(mount(KontakBantuanTombol, { props: { kontak: [tanpaSaluran] }, global: { stubs } }).html()).not.toContain('kontak-bantuan-tombol');
    });
});

describe('KontakBantuanDaftar', () => {
    it('tautan WhatsApp (tab baru, noopener), telepon, dan email', () => {
        const w = mount(KontakBantuanDaftar, { props: { kontak: [KONTAK] } });
        const wa = w.find('a[href="https://wa.me/6281234567890"]');

        expect(wa.attributes('target')).toBe('_blank');
        expect(wa.attributes('rel')).toContain('noopener');
        expect(w.find('a[href="tel:+6281234567890"]').exists()).toBe(true);
        expect(w.find('a[href="mailto:permit@contoh.id"]').exists()).toBe(true);
        expect(w.text()).toContain('Senin–Jumat 08.00–16.00');
    });

    it('kontak hanya-email tanpa tombol WhatsApp', () => {
        const w = mount(KontakBantuanDaftar, { props: { kontak: [{ ...KONTAK, no_hp: null, tautan_wa: null, tautan_telepon: null }] } });
        expect(w.find('a[href^="https://wa.me"]').exists()).toBe(false);
        expect(w.find('a[href="mailto:permit@contoh.id"]').exists()).toBe(true);
    });
});
