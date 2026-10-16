import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopDokumenAlur from './SopDokumenAlur.vue';

/**
 * Popover node peta alur.
 *
 * Peta ini menangani pan/pinch lewat pointer event di viewport, dan popovernya
 * hidup DI DALAM viewport itu. Artinya setiap tekanan pada tombol popover ikut
 * menggelembung ke penangan peta — dan kalau penangan itu menutup popover, DOM
 * tombolnya lenyap sebelum browser sempat mengirim `click`. Gejalanya di layar:
 * "tombolnya tidak bisa diklik", padahal yang terjadi tombolnya sudah tidak ada.
 *
 * Karena itu yang diuji di sini adalah URUTAN PERISTIWA SUNGGUHAN — tekan node,
 * lalu tekan tombol — bukan pemanggilan pilihAksi() langsung. Memanggil
 * metodenya akan lulus bahkan ketika popovernya tak pernah bisa disentuh.
 */

const VERSI_TERBIT = {
    Id_SOP_Item_Versi: 11,
    Versi_Ke: 2,
    Status: 'PUBLISHED',
    Punya_Berkas: true,
    Bisa_Pratinjau: true,
};

const VERSI_DRAFT = {
    Id_SOP_Item_Versi: 12,
    Versi_Ke: 3,
    Status: 'DRAFT',
    Punya_Berkas: true,
    Bisa_Pratinjau: true,
};

function pasang(props = {}) {
    return mount(SopDokumenAlur, {
        props: {
            dokumen: { Judul: 'SOP Uji', Nomor_SOP_Resmi: 'SOP/001', Tipe: 'DOKUMEN' },
            versi: [VERSI_TERBIT],
            paket: [],
            ...props,
        },
        attachTo: document.body,
    });
}

/* jsdom tak mengimplementasikan PointerEvent. MouseEvent dengan nama tipe yang
   sama sudah cukup: yang didengar penangan adalah nama peristiwanya, dan
   pointerId ditempelkan sendiri. */
function peristiwaPointer(nama) {
    const e = new window.MouseEvent(nama, { clientX: 40, clientY: 40, bubbles: true });
    Object.defineProperty(e, 'pointerId', { value: 1 });

    return e;
}

/** Tekan-lepas pada sebuah elemen, lengkap dengan gelembung ke viewport. */
async function tekan(w, el) {
    el.element.dispatchEvent(peristiwaPointer('pointerdown'));
    el.element.dispatchEvent(peristiwaPointer('pointerup'));
    await w.vm.$nextTick();
}

async function bukaPopover(w) {
    await tekan(w, w.find('[data-node="v11"]'));

    return w.find('.alur-pop');
}

describe('SopDokumenAlur — popover node', () => {
    it('terbuka saat node versi ditekan', async () => {
        const w = pasang();
        const pop = await bukaPopover(w);

        expect(pop.exists()).toBe(true);
    });

    it('tetap terbuka saat tombol di dalamnya ditekan', async () => {
        const w = pasang();
        await bukaPopover(w);

        /* Inti bug: pointerup pada tombol menggelembung ke viewport. Bila
           viewport menganggapnya "menekan latar", popovernya ditutup di sini
           juga — dan tombolnya hilang sebelum click-nya sampai. */
        const tombol = w.find('.alur-pop .ap-aksi');
        expect(tombol.exists()).toBe(true);
        await tekan(w, tombol);

        expect(w.find('.alur-pop').exists()).toBe(true);
    });

    it('meneruskan aksi lihat ketika tombolnya diklik', async () => {
        const w = pasang();
        await bukaPopover(w);

        const tombol = w.find('.alur-pop .ap-aksi');
        await tekan(w, tombol);
        await tombol.trigger('click');

        expect(w.emitted('lihat-versi')).toBeTruthy();
        expect(w.emitted('lihat-versi')[0][0]).toMatchObject({ Id_SOP_Item_Versi: 11 });
    });

    it('meneruskan pratinjau terbit untuk versi draft', async () => {
        const w = pasang({ versi: [VERSI_DRAFT] });
        await tekan(w, w.find('[data-node="v12"]'));

        const terbit = w.findAll('.alur-pop .ap-aksi').find((b) => b.text().includes('Pratinjau terbit'));
        expect(terbit).toBeTruthy();
        await tekan(w, terbit);
        await terbit.trigger('click');

        expect(w.emitted('terbit-versi')).toBeTruthy();
    });

    it('memperlihatkan tombolnya sedang bekerja selama induk memuat', async () => {
        const w = pasang();
        await bukaPopover(w);

        const tombol = w.find('.alur-pop .ap-aksi');
        await tekan(w, tombol);
        await tombol.trigger('click');

        // Induk melaporkan dirinya sibuk untuk versi ini.
        await w.setProps({ sibuk: 11 });

        /* Popover HARUS masih ada: kalau ia tertutup saat kliknya diterima,
           tak ada tempat pun yang memberi tahu bahwa sesuatu sedang berjalan,
           dan itulah yang membuatnya terasa nge-lag. */
        expect(w.find('.alur-pop').exists()).toBe(true);
        expect(w.find('.alur-pop .ap-aksi').text()).toContain('Membuka');
        expect(w.find('.alur-pop .ap-aksi.menunggu').exists()).toBe(true);
    });

    it('menutup popover setelah induk selesai memuat', async () => {
        const w = pasang();
        await bukaPopover(w);

        const tombol = w.find('.alur-pop .ap-aksi');
        await tekan(w, tombol);
        await tombol.trigger('click');
        await w.setProps({ sibuk: 11 });
        await w.setProps({ sibuk: null });

        expect(w.find('.alur-pop').exists()).toBe(false);
    });

    it('menutup popover seketika untuk pratinjau terbit — modalnya menutupi peta', async () => {
        const w = pasang({ versi: [VERSI_DRAFT] });
        await tekan(w, w.find('[data-node="v12"]'));

        const terbit = w.findAll('.alur-pop .ap-aksi').find((b) => b.text().includes('Pratinjau terbit'));
        await tekan(w, terbit);
        await terbit.trigger('click');

        expect(w.find('.alur-pop').exists()).toBe(false);
    });

    it('menekan latar tetap menutup popover', async () => {
        const w = pasang();
        await bukaPopover(w);

        await tekan(w, w.find('.alur-kanvas'));

        expect(w.find('.alur-pop').exists()).toBe(false);
    });
});
