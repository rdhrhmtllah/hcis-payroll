import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisDialog from './HcisDialog.vue';

/**
 * Dialog di atas dialog.
 *
 * Seluruh overlay memakai token `--hcis-z-overlay` yang sama, sehingga dua dialog yang
 * terbuka bersamaan dulu diadu oleh URUTAN DOM belaka — dan urutan itu ditentukan posisi
 * komponennya di berkas induk, bukan oleh yang mana dibuka lebih dulu. Pada halaman Siklus
 * PA akibatnya nyata: dialog "Penugasan Penilai" ditulis di atas "Daftar Peserta" di
 * template, jadi ia terbit DI BELAKANG — layarnya gelap, isinya ada, tetapi tidak terlihat
 * sama sekali, dan terbaca seperti halaman yang menggantung.
 */
function pasang(props = {}) {
    return mount(HcisDialog, {
        props: { modelValue: true, title: 'Uji', ...props },
        global: {
            stubs: {
                HcisIcon: { template: '<i />' },
                HcisBadge: { template: '<span />' },
                Teleport: true,
            },
        },
        attachTo: document.body,
    });
}

function lapisan(wrapper) {
    const gaya = wrapper.find('.hcis-dialog-overlay').attributes('style') || '';
    const cocok = gaya.match(/z-index:\s*(\d+)/);

    return cocok ? Number(cocok[1]) : null;
}

describe('HcisDialog — tumpukan', () => {
    it('dialog kedua berada DI ATAS yang pertama', () => {
        const pertama = pasang({ title: 'Daftar Peserta' });
        const kedua = pasang({ title: 'Penugasan Penilai' });

        expect(lapisan(pertama)).not.toBeNull();
        expect(lapisan(kedua)).toBeGreaterThan(lapisan(pertama));

        pertama.unmount();
        kedua.unmount();
    });

    it('yang tertutup tidak lagi dihitung, jadi lapisannya tidak menumpuk selamanya', async () => {
        const a = pasang();
        const tinggiA = lapisan(a);

        await a.setProps({ modelValue: false });

        /* Dibuka lagi sesudah yang lama ditutup harus kembali ke tingkat yang sama, bukan
           terus meninggi — tumpukan yang hanya naik membuat dialog ke sekian melampaui
           setiap overlay lain di aplikasi. */
        const b = pasang();

        expect(lapisan(b)).toBe(tinggiA);

        a.unmount();
        b.unmount();
    });

    it('dialog yang dibongkar selagi terbuka tidak meninggalkan jejak', () => {
        const a = pasang();
        const tinggiA = lapisan(a);

        // Dibongkar TANPA ditutup lebih dulu — lazim ketika halamannya berpindah.
        a.unmount();

        const b = pasang();

        expect(lapisan(b)).toBe(tinggiA);

        b.unmount();
    });
});
