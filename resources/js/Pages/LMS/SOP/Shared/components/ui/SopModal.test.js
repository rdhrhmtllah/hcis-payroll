import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopModal from './SopModal.vue';

/**
 * Modal SOP harus mendarat di <body>, membawa temanya sendiri.
 *
 * `position: fixed` berhenti mengacu ke viewport begitu SALAH SATU leluhurnya
 * punya transform / filter / backdrop-filter yang bukan `none`. Di modul ini
 * pemicunya ada di mana-mana, dan yang paling licin `.sop-fade-up`: animasinya
 * memakai `both`, jadi `transform: translateY(0)` menempel selamanya setelah
 * animasi selesai. Gejalanya bukan modal yang rusak — modalnya tampak baik —
 * melainkan LATAR GELAPNYA yang berhenti di kotak leluhur itu: sidebar dan
 * topbar tetap terang dan pinggirannya menyisakan celah seperti padding.
 *
 * Tak ada galat, tak ada peringatan. Karena itu penjaganya harus di sini.
 */

const cariBackdrop = () => document.body.querySelector('.sop-modal-backdrop');

afterEach(() => {
    document.body.innerHTML = '';
    document.body.style.overflow = '';
    document.__sopModalKunci = 0;
});

describe('SopModal', () => {
    it('memasang backdrop sebagai anak langsung <body>', async () => {
        const w = mount(SopModal, { props: { show: false } });
        await w.setProps({ show: true });

        const bd = cariBackdrop();
        expect(bd, 'backdrop tak ditemukan di body').toBeTruthy();

        /* Yang menentukan bukan "anak keberapa" — <transition> di-stub jadi satu
           elemen pembungkus di lingkungan uji — melainkan bahwa ia sudah KELUAR
           dari pohon komponennya. Di sanalah leluhur ber-transform memerangkap
           position: fixed. */
        expect(w.element.contains?.(bd) ?? false).toBe(false);

        // Dan tak ada leluhur lain antara body dan pembungkusnya.
        expect(bd.parentElement.parentElement).toBe(document.body);
    });

    it('membawa .sop-scope ikut berpindah', async () => {
        // Seluruh tema modul bersandar pada leluhur ini (.sop-scope .stitch-btn,
        // .sop-field, dst). Tanpa kelasnya, setiap tombol di dalam modal
        // kehilangan gayanya begitu dipindahkan ke body — persis cacat yang
        // baru saja diperbaiki di pemilih dokumen.
        const w = mount(SopModal, { props: { show: false } });
        await w.setProps({ show: true });

        expect(cariBackdrop().classList.contains('sop-scope')).toBe(true);
    });

    it('membersihkan dirinya saat ditutup', async () => {
        const w = mount(SopModal, { props: { show: true } });
        expect(cariBackdrop()).toBeTruthy();

        await w.setProps({ show: false });
        expect(cariBackdrop()).toBe(null);
    });

    it('melepas kunci gulir badan saat dilepas dalam keadaan terbuka', async () => {
        // Unmount selagi terkunci pernah membekukan halaman secara permanen:
        // penghitungnya tak pernah turun dan body.overflow tetap hidden.
        const w = mount(SopModal, { props: { show: true } });
        expect(document.body.style.overflow).toBe('hidden');

        w.unmount();
        expect(document.body.style.overflow).toBe('');
    });

    it('isi slot ikut sampai ke body', async () => {
        const w = mount(SopModal, {
            props: { show: false },
            slots: { default: '<p class="isi-uji">halo</p>', footer: '<button class="aksi-uji">Oke</button>' },
        });
        await w.setProps({ show: true });

        expect(document.body.querySelector('.isi-uji')).toBeTruthy();
        expect(document.body.querySelector('.sop-modal-foot .aksi-uji')).toBeTruthy();
    });
});
