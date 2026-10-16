import { readFileSync } from 'node:fs';
import path from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisDialog from './HcisDialog.vue';

describe('HcisDialog owned primitive component', () => {
    it('renders when modelValue is true', () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: true,
                title: 'Judul Modal Dialog Test',
                teleportTo: false,
            },
        });
        expect(wrapper.text()).toContain('Judul Modal Dialog Test');
    });

    it('does not render content when modelValue is false', () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: false,
                title: 'Judul Modal Hidden',
                teleportTo: false,
            },
        });
        expect(wrapper.find('.hcis-dialog').exists()).toBe(false);
    });

    it('emits close and update:modelValue on close button click', async () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: true,
                title: 'Test Close',
                teleportTo: false,
            },
        });
        const closeBtn = wrapper.find('.hcis-dialog__close');
        await closeBtn.trigger('click');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    it('applies sentiment icon class correctly', () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: true,
                title: 'Danger Dialog',
                sentiment: 'danger',
                teleportTo: false,
            },
        });
        expect(wrapper.find('.hcis-dialog__icon--danger').exists()).toBe(true);
    });

    it('renders kicker, badge, and variant correctly', () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: true,
                kicker: 'MODUL EVALUASI',
                title: 'Judul Modal',
                badge: 'Prioritas',
                variant: 'hero',
                teleportTo: false,
            },
        });
        expect(wrapper.text()).toContain('MODUL EVALUASI');
        expect(wrapper.text()).toContain('Prioritas');
        expect(wrapper.find('.hcis-dialog--variant-hero').exists()).toBe(true);
    });

    it('renders loading overlay when loading prop is true', () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: true,
                title: 'Loading Dialog',
                loading: true,
                loadingText: 'Menyimpan perubahan...',
                teleportTo: false,
            },
        });
        // Lapisan penuh diganti bilah tipis di kepala dialog.
        expect(wrapper.find('.hcis-dialog__loading-bar').exists()).toBe(true);
        // 'loadingText' BUKAN prop HcisDialog -- ia milik HcisChart dan
        // HcisDualPaneDialog. Dialog ini menandai keadaan memuat lewat bilah
        // ber-role progressbar, dan itulah yang pantas dituntut di sini.
        expect(wrapper.find('.hcis-dialog__loading-bar').attributes('role')).toBe('progressbar');
        expect(wrapper.find('.hcis-dialog').classes()).toContain('hcis-dialog--loading');
    });

    it('applies sticky footer class when stickyFooter is true', () => {
        const wrapper = mount(HcisDialog, {
            props: {
                modelValue: true,
                title: 'Sticky Dialog',
                stickyFooter: true,
                teleportTo: false,
            },
        });
        expect(wrapper.find('.hcis-dialog--sticky-footer').exists()).toBe(true);
    });
    /**
     * BADAN DIALOG HARUS BOLEH MENYUSUT.
     *
     * Panelnya `overflow: hidden` dengan `max-height: calc(90vh - 32px)`, dan badannya anak
     * flex kolom ber-`flex: 1`. `min-height` bawaan anak flex adalah `auto`: ia menolak
     * menyusut di bawah tinggi isinya, sehingga `overflow-y: auto` di badan tidak pernah
     * menyala dan panel MEMOTONG isinya begitu saja — tanpa gulungan, tanpa tanda apa pun.
     * Gejalanya menyamar jadi "kontennya tidak ikut ter-render", dan dikejar ke komponen
     * isinya, bukan ke sini.
     *
     * Diperiksa dari SUMBER karena jsdom tidak menata letak: ia tidak menghitung tinggi,
     * jadi memasang dialog lalu mengukur isinya akan hijau apa pun aturannya. Yang bisa
     * dijaga di sini adalah aturannya masih tertulis.
     *
     * CARA MEMBUATNYA MERAH: hapus `min-height: 0` dari `.hcis-dialog__body`.
     */
    it('badan dialog menyusut supaya isinya digulung, bukan dipotong', () => {
        /* `__dirname`, bukan `import.meta.url`: konfigurasi vitest berkas ini tidak
           memulangkan URL berskema file, dan `fileURLToPath` melemparnya. Cara yang sama
           dipakai designSystemGuards.test.js. */
        const sumber = readFileSync(path.join(__dirname, 'HcisDialog.vue'), 'utf8');

        /* Diikat ke AWAL BARIS. Tanpa itu `.hcis-dialog--variant-obsidian
           .hcis-dialog__body` yang muncul lebih dulu ikut tertangkap, dan yang diperiksa
           jadi blok tema — bukan aturan dasarnya. */
        const blok = sumber.match(/^\.hcis-dialog__body\s*\{([^}]*)\}/m);

        expect(blok, 'Aturan .hcis-dialog__body tidak ditemukan.').not.toBeNull();

        /* KOMENTAR DIBUANG DULU. Penjelasan di dalam blok ini menyebut `min-height: 0`
           untuk menerangkan kenapa aturannya ada — dan tanpa pembuangan ini, penjelasan
           itulah yang tertangkap. Penjaganya akan tetap hijau sesudah deklarasinya
           benar-benar dihapus: sudah dibuktikan, bukan dikhawatirkan. */
        const deklarasi = blok[1].replace(/\/\*[\s\S]*?\*\//g, '');

        expect(deklarasi).toMatch(/min-height:\s*0/);
    });
});
