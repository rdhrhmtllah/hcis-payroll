import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PaDaftarPerilaku from './PaDaftarPerilaku.vue';
import { pecahPerilaku } from '../utils/perilaku.js';

const stubs = { HcisIcon: { template: '<i />' } };

describe('pecahPerilaku', () => {
    it('satu perilaku per baris; penanda butir dan baris kosong dibuang', () => {
        expect(pecahPerilaku('- Satu\r\n\r\n2. Dua\n•  Tiga \n  ')).toEqual(['Satu', 'Dua', 'Tiga']);
    });

    it('kosong atau bukan teks menjadi daftar kosong', () => {
        expect(pecahPerilaku(null)).toEqual([]);
        expect(pecahPerilaku('')).toEqual([]);
        expect(pecahPerilaku(' \n - ')).toEqual([]);
    });
});

describe('PaDaftarPerilaku', () => {
    it('merender butir dan judul', () => {
        const w = mount(PaDaftarPerilaku, { props: { teks: 'Satu\nDua' }, global: { stubs } });
        expect(w.findAll('li').map((l) => l.text())).toEqual(['Satu', 'Dua']);
        expect(w.text()).toContain('Expected behaviour');
    });

    it('tanpa isi tidak merender apa pun, termasuk judulnya', () => {
        const w = mount(PaDaftarPerilaku, { props: { teks: '' }, global: { stubs } });
        expect(w.find('[data-uji="daftar-perilaku"]').exists()).toBe(false);
    });
});
