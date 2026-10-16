import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PaExportStudioPage from './PaExportStudioPage.vue';

/*
 * Tiruan perakit MENAMPAKKAN baseUrl dan datasetAwal yang diterimanya, persis
 * seperti tiruan pada SopExportStudioPage.test.js: tiruan yang hanya merender
 * <div> kosong membuat uji "meneruskan baseUrl" lulus tanpa pernah memeriksa
 * nilainya, dan justru nilai itulah yang menentukan endpoint yang dipanggil
 * setelah dataset berganti.
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

const uiStubs = {
    HcisHeader: {
        props: ['title', 'kicker', 'subtitle', 'badge'],
        template: '<header><h1>{{ title }}</h1><p>{{ subtitle }}</p><span>{{ badge }}</span><slot name="actions" /><slot name="tabs" /></header>',
    },
    HcisTabs: {
        props: ['modelValue', 'tabs'],
        template: '<div class="tabs-stub"></div>',
    },
};

describe('PaExportStudioPage', () => {
    const pasang = () =>
        mount(PaExportStudioPage, {
            props: { baseUrl: '/api/v1/performance-appraisal/export-studio' },
            global: { stubs: uiStubs },
        });

    it('melahirkan ulang perakit saat dataset berganti lewat tombol pemindah', async () => {
        // Tanpa :key, pilihan dimensi dataset lama bocor ke katalog dataset baru
        // dan pengguna melihat kolom yang tidak ada di dataset yang sedang dibuka.
        //
        // Berpindah lewat KLIK TOMBOL sungguhan, bukan memanggil method lewat
        // wrapper.vm: memanggil method langsung melewati justru bagian yang
        // paling mungkin rusak -- pengikatan tombol ke handler-nya.
        const wrapper = mount(PaExportStudioPage, { global: { stubs: uiStubs } });
        const studioAwal = wrapper.findComponent({ name: 'ReportStudio' });
        const keyAwal = studioAwal.vm.$.vnode.key;

        const tombolPenugasan = wrapper.findAll('.pes-dataset-btn')[1];
        expect(tombolPenugasan.exists()).toBe(true);
        await tombolPenugasan.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'ReportStudio' }).vm.$.vnode.key).not.toBe(keyAwal);
    });

    it('menempelkan dataset aktif ke baseUrl', () => {
        const wrapper = mount(PaExportStudioPage, { global: { stubs: uiStubs } });
        const studio = wrapper.findComponent({ name: 'ReportStudio' });

        expect(studio.props('baseUrl')).toContain('/export-studio/hasil');
    });

    it('meneruskan baseUrl ke perakit, LENGKAP dengan dataset aktif di ujungnya', () => {
        const studio = pasang().find('[data-uji="studio"]');

        expect(studio.exists()).toBe(true);
        expect(studio.attributes('data-base-url')).toBe('/api/v1/performance-appraisal/export-studio/hasil');
        expect(studio.attributes('data-dataset')).toBe('hasil');
    });

    it('mengganti sumber data lewat klik tombol mengalihkan alamat DAN dataset perakit', async () => {
        const w = pasang();

        await w.findAll('.pes-dataset-btn')[1].trigger('click');
        await w.vm.$nextTick();

        const studio = w.find('[data-uji="studio"]');
        expect(studio.attributes('data-base-url')).toBe('/api/v1/performance-appraisal/export-studio/penugasan');
        expect(studio.attributes('data-dataset')).toBe('penugasan');
        expect(w.findAll('.pes-dataset-btn')[1].classes()).toContain('is-active');
    });

    /*
     * JUJUR soal apa yang diperiksa: ini menegaskan bahwa labelDataset()/
     * LABEL_DATASET benar-benar TERPASANG ke lencana hero dan ikut berganti
     * saat tombol pemindah diklik -- BUKAN bahwa isi LABEL_DATASET itu sendiri
     * sesuai dengan PaHasilDataset::label()/PaPenugasanDataset::label() di
     * backend. Kesesuaian ke backend itu dijaga terpisah, dari sisi PHP, oleh
     * PaExportStudioAksesTest::test_label_dataset_di_vue_sinkron_dengan_label_dataset_class()
     * (baca literal LABEL_DATASET dari berkas ini lewat regex, tanpa
     * menjalankan Vue). Nama lama uji ini ("bukan karangan") mengklaim
     * menjaga kesesuaian itu padahal cuma membandingkan dengan konstanta di
     * berkas ini sendiri -- mengubah label() di PHP akan membuat lencana basi
     * TANPA uji ini pernah merah.
     */
    it('memasang label dataset dari LABEL_DATASET ke lencana hero, dan ikut berganti saat dataset dipindah', async () => {
        const w = pasang();
        expect(w.text()).toContain('Hasil Performance Appraisal');

        await w.findAll('.pes-dataset-btn')[1].trigger('click');
        await w.vm.$nextTick();

        expect(w.text()).toContain('Penugasan Penilai Performance Appraisal');
    });

    /*
     * Tahap 2: pemindah kini menawarkan EMPAT sumber data, bukan dua. Kalau
     * tombol kompetensi/peserta tidak dirender (atau LABEL_DATASET/SUBTITLE
     * lupa diperluas), berpindah ke sana jatuh ke label fallback (kunci
     * dataset mentah) dan subtitle default -- tidak ada satu pun galat, cuma
     * lencana/subtitle yang tidak pernah menampilkan nama benar.
     */
    it('berpindah lewat keempat tombol sumber data mengikuti label dan subtitle tiap dataset', async () => {
        const w = pasang();
        const tombol = w.findAll('.pes-dataset-btn');
        expect(tombol).toHaveLength(4);

        const harapan = [
            ['Hasil Performance Appraisal', 'Rakit laporan hasil performance appraisal, lihat hasilnya sebelum diunduh.'],
            ['Penugasan Penilai Performance Appraisal', 'Rakit laporan penugasan penilai performance appraisal, lihat hasilnya sebelum diunduh.'],
            ['Kompetensi Performance Appraisal', 'Rakit laporan kompetensi performance appraisal, lihat hasilnya sebelum diunduh.'],
            ['Peserta Performance Appraisal', 'Rakit laporan peserta performance appraisal, lihat hasilnya sebelum diunduh.'],
        ];

        for (let i = 0; i < harapan.length; i += 1) {
            await tombol[i].trigger('click');
            await w.vm.$nextTick();

            expect(w.text()).toContain(harapan[i][0]);
            expect(w.text()).toContain(harapan[i][1]);
            expect(tombol[i].classes()).toContain('is-active');
        }
    });
});
