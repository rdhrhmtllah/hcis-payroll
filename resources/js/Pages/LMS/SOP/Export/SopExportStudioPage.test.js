import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import SopExportStudioPage from './SopExportStudioPage.vue';

/*
 * Tiruan perakit MENAMPAKKAN baseUrl dan datasetAwal yang diterimanya.
 * Tiruan yang hanya merender <div> kosong membuat uji "meneruskan baseUrl"
 * lulus tanpa pernah memeriksa nilainya — dan justru nilai itulah yang
 * menentukan endpoint mana yang dipanggil setelah sumber data diganti.
 */
vi.mock('@/components/SYSTEM/ReportStudio/ReportStudio.vue', () => ({
    default: {
        name: 'ReportStudio',
        props: ['baseUrl', 'datasetAwal', 'tabUtama', 'tanpaTopBar'],
        template: '<div data-uji="studio" :data-base-url="baseUrl" :data-dataset="datasetAwal" />',
    },
}));

vi.mock('@/components/ui/hcisDialog', () => ({
    hcisConfirm: vi.fn(() => Promise.resolve(true)),
}));

describe('SopExportStudioPage', () => {
    const pasang = () =>
        mount(SopExportStudioPage, {
            props: { baseUrl: '/api/v1/sop/export-studio' },
        });

    it('memakai HcisHeader design system, bukan salinan .shero ke sebelas', () => {
        const w = pasang();
        expect(w.findComponent({ name: 'HcisHeader' }).exists()).toBe(true);
        // Kelas hero tulis tangan tidak boleh lahir lagi di halaman ini.
        expect(w.html()).not.toContain('shero-aurora');
    });

    it('membawa kelas scope SOP supaya sopTheme.css menjangkaunya', () => {
        expect(pasang().find('.sop-scope').exists()).toBe(true);
    });

    it('meneruskan baseUrl ke perakit, LENGKAP dengan dataset aktif di ujungnya', () => {
        const studio = pasang().find('[data-uji="studio"]');

        expect(studio.exists()).toBe(true);
        // Tanpa dataset di ujung alamat, galeri template tidak terscope dan
        // Studio Kepatuhan memuat template Dokumen berikut kolom-kolomnya.
        expect(studio.attributes('data-base-url')).toBe('/api/v1/sop/export-studio/kepatuhan');
        expect(studio.attributes('data-dataset')).toBe('kepatuhan');
    });

    it('menampilkan pemindah sumber data dengan Kepatuhan aktif sejak awal', () => {
        const w = pasang();
        const tombol = w.findAll('.ses-dataset-btn');

        expect(tombol).toHaveLength(2);
        expect(tombol[0].classes()).toContain('is-active');
        expect(tombol[1].classes()).not.toContain('is-active');
    });

    it('mengganti sumber data mengalihkan alamat DAN dataset perakit', async () => {
        const w = pasang();

        await w.findAll('.ses-dataset-btn')[1].trigger('click');
        await w.vm.$nextTick();

        const studio = w.find('[data-uji="studio"]');
        expect(studio.attributes('data-base-url')).toBe('/api/v1/sop/export-studio/dokumen');
        expect(studio.attributes('data-dataset')).toBe('dokumen');
        expect(w.findAll('.ses-dataset-btn')[1].classes()).toContain('is-active');
    });

    it('menyebut sumber yang sedang dibuka di lencana hero', async () => {
        const w = pasang();
        expect(w.text()).toContain('Sumber: Kepatuhan');

        await w.findAll('.ses-dataset-btn')[1].trigger('click');
        await w.vm.$nextTick();

        expect(w.text()).toContain('Sumber: Dokumen');
    });

    it('tidak menyebut jumlah baris sebelum ada pratinjau', () => {
        /* ATURANNYA TETAP, CARANYA BERUBAH. Dulu hero punya chip "Baris" yang
           menulis "—" selama pratinjau belum dijalankan; chip itu pindah ke
           dalam ReportStudio dan seluruh barisnya kini digantung v-if="hasil"
           — tak ada angka sama sekali sampai hasilnya ada.

           Yang dijaga tetap sama, dan itulah alasan uji ini ada: nol terbaca
           sebagai FAKTA ("laporannya kosong") padahal artinya "belum dihitung".
           Menampilkan 0 lebih buruk daripada tak menampilkan apa pun. */
        const teks = pasang().text();

        expect(teks).not.toMatch(/0\s*baris/i);
        expect(teks).not.toContain('Pratinjau terakhir');
    });
});
