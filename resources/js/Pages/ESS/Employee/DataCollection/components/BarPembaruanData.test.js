import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import BarPembaruanData from './BarPembaruanData.vue';

const buat = (status = { terkunci: false, sisa_tunda: 3, jumlah_tunda: 0 }) =>
    mount(BarPembaruanData, { props: { status } });

describe('BarPembaruanData', () => {
    it('dirakit dari HcisAlert, bukan spanduk buatan sendiri', () => {
        const w = buat();

        expect(w.findComponent({ name: 'HcisAlert' }).exists()).toBe(true);
        expect(w.text()).toContain('belum diperbarui');
    });

    it('memancarkan isi saat tombolnya ditekan', async () => {
        const w = buat();

        await w.find('[data-uji="bar-isi"]').trigger('click');

        expect(w.emitted('isi')).toBeTruthy();
    });

    it('tidak lagi punya nada mendesak untuk yang terkunci', () => {
        // Yang terkunci sudah dialihkan server ke formulirnya; ia tidak pernah
        // melihat spanduk ini. Menyimpan nada kedua berarti memelihara cabang
        // yang tak seorang pun bisa capai.
        const w = buat({ terkunci: true, sisa_tunda: 0, jumlah_tunda: 3 });

        expect(w.text()).not.toContain('wajib dilengkapi');
        expect(w.findComponent({ name: 'HcisAlert' }).props('variant')).toBe('info');
    });
});
