import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaStepHeader from './PaStepHeader.vue';

const stubs = {
    HcisIcon: { props: ['name'], template: '<i :data-icon="name" />' },
};

describe('PaStepHeader', () => {
    it('menampilkan penanda langkah, judul, dan keterangannya', () => {
        const wrapper = mount(PaStepHeader, {
            props: {
                kicker: 'LANGKAH 1 DARI 5 · DISTRIBUSI BOBOT',
                title: 'Bobot Penilaian Pilar',
                description: 'Tetapkan proporsi KPI lawan Kompetensi.',
                icon: 'sliders2',
            },
            global: { stubs },
        });

        /* Penandanya yang paling dijaga. Sebelum komponen ini ada, dua langkah
           mengirimkannya sebagai atribut `kicker` ke `HcisCard` — yang tidak punya prop
           itu, sehingga Vue membuangnya DIAM-DIAM dan penandanya hilang tanpa satu pun
           galat. Uji ini menegaskan ia benar-benar sampai ke DOM. */
        expect(wrapper.find('.pa-step-kicker').text()).toContain('LANGKAH 1 DARI 5');
        expect(wrapper.find('.pa-step-title').text()).toContain('Bobot Penilaian Pilar');
        expect(wrapper.find('.pa-step-description').text()).toContain('proporsi KPI');
    });

    it('menyediakan tempat aksi dan menghilangkan keterangan yang kosong', () => {
        const wrapper = mount(PaStepHeader, {
            props: { kicker: 'LANGKAH 5 DARI 5', title: 'Penugasan Penilai' },
            slots: { actions: '<button class="aksi-uji">Lihat Master</button>' },
            global: { stubs },
        });

        expect(wrapper.find('.aksi-uji').exists()).toBe(true);

        // Keterangan kosong tidak menyisakan paragraf kosong yang menambah jarak.
        expect(wrapper.find('.pa-step-description').exists()).toBe(false);
    });
});
