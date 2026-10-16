import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisStatCard from './HcisStatCard.vue';
import HcisSkeleton from './HcisSkeleton.vue';
import HcisTable from './HcisTable.vue';
import HcisTabs from './HcisTabs.vue';
import HcisBreadcrumb from './HcisBreadcrumb.vue';

describe('HcisStatCard', () => {
    it('memisahkan arah tren dari warnanya', () => {
        const wrapper = mount(HcisStatCard, {
            props: { label: 'Keterlambatan', value: 12, delta: '12%', trend: 'up', sentiment: 'bad' },
        });

        // Arah dan warna dibawa dua hal yang berbeda: panah menyatakan arah
        // (naik), varian lencana menyatakan penilaian (buruk). Angka yang naik
        // tidak selalu kabar baik — keterlambatan yang naik justru merah.
        const lencana = wrapper.find('.hcis-stat__delta-badge');

        expect(lencana.classes()).toContain('hcis-badge--danger');
        expect(lencana.text()).toContain('↑');
        expect(wrapper.find('.hcis-stat__value').text()).toBe('12');
    });

    it('menyembunyikan angka dan menampilkan skeleton saat loading, label tetap ada', () => {
        const wrapper = mount(HcisStatCard, { props: { label: 'Menunggu', value: 5, loading: true } });

        expect(wrapper.find('.hcis-stat__label').text()).toBe('Menunggu');
        expect(wrapper.find('.hcis-stat__value').exists()).toBe(false);
        expect(wrapper.findComponent(HcisSkeleton).exists()).toBe(true);
    });
});

describe('HcisSkeleton', () => {
    it('merender beberapa baris sesuai prop lines', () => {
        const wrapper = mount(HcisSkeleton, { props: { lines: 3 } });
        expect(wrapper.findAll('.hcis-skeleton__line')).toHaveLength(3);
    });
});

describe('HcisTable', () => {
    const columns = [{ key: 'nama', label: 'Nama' }, { key: 'status', label: 'Status' }];

    it('merender kolom dan baris', () => {
        const rows = [{ id: 1, nama: 'Anggota A', status: 'Aktif' }];
        const wrapper = mount(HcisTable, { props: { columns, rows } });

        expect(wrapper.findAll('th')).toHaveLength(2);
        expect(wrapper.text()).toContain('Anggota A');
    });

    it('menampilkan keadaan kosong saat rows kosong', () => {
        const wrapper = mount(HcisTable, { props: { columns, rows: [] } });
        expect(wrapper.find('.hcis-empty-state').exists()).toBe(true);
    });

    it('merender skeleton saat loading', () => {
        const wrapper = mount(HcisTable, { props: { columns, rows: [], loading: true } });
        expect(wrapper.find('.hcis-skeleton').exists()).toBe(true);
        expect(wrapper.find('.hcis-empty-state').exists()).toBe(false);
    });
});

describe('HcisTabs', () => {
    it('menandai tab aktif dan memancarkan perubahan', async () => {
        const tabs = [{ key: 'a', label: 'Antrian' }, { key: 'b', label: 'Riwayat' }];
        const wrapper = mount(HcisTabs, { props: { modelValue: 'a', tabs } });

        expect(wrapper.findAll('.hcis-tabs__tab')[0].classes()).toContain('is-active');

        await wrapper.findAll('.hcis-tabs__tab')[1].trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([['b']]);
    });
});

describe('HcisBreadcrumb', () => {
    it('merender item terakhir tanpa tautan dan menandainya sebagai halaman saat ini', () => {
        const wrapper = mount(HcisBreadcrumb, {
            props: { items: [{ label: 'Beranda', href: '/' }, { label: 'SOP' }] },
        });

        expect(wrapper.findAll('a')).toHaveLength(1);
        expect(wrapper.find('[aria-current="page"]').text()).toBe('SOP');
    });
});
