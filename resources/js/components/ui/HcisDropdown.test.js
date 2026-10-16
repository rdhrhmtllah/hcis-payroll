import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisDropdown from './HcisDropdown.vue';

describe('HcisDropdown.vue', () => {
    it('renders trigger button and toggles menu on click', async () => {
        const items = [
            { label: 'Ubah Data', icon: 'pencil-fill' },
            { label: 'Hapus Data', icon: 'trash-fill', variant: 'danger' },
        ];

        const wrapper = mount(HcisDropdown, {
            props: { label: 'Aksi Tabel', items },
        });

        expect(wrapper.find('.hcis-dropdown__menu').exists()).toBe(false);
        await wrapper.find('.hcis-dropdown__trigger').trigger('click');
        expect(wrapper.find('.hcis-dropdown__menu').exists()).toBe(true);
        expect(wrapper.text()).toContain('Ubah Data');
        expect(wrapper.text()).toContain('Hapus Data');
    });

    it('emits select event when item is clicked', async () => {
        const items = [{ label: 'Detail', icon: 'eye' }];
        const wrapper = mount(HcisDropdown, { props: { items } });

        await wrapper.find('.hcis-dropdown__trigger').trigger('click');
        await wrapper.find('.hcis-dropdown__item').trigger('click');
        expect(wrapper.emitted('select')).toBeTruthy();
        expect(wrapper.emitted('select')[0][0].label).toBe('Detail');
    });
});
