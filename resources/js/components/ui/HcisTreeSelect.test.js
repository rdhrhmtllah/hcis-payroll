import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisTreeSelect from './HcisTreeSelect.vue';

describe('HcisTreeSelect.vue', () => {
    const mockTree = [
        {
            value: 'dim_manajerial',
            label: 'Kompetensi Manajerial',
            selectable: false,
            badge: 'MANAJERIAL',
            children: [
                { value: '11', label: 'Analytical Thinking', code: 'K01' },
                { value: '12', label: 'Problem Solving', code: 'K02' },
            ],
        },
        {
            value: 'dim_core',
            label: 'Core Values',
            selectable: false,
            children: [
                { value: '21', label: 'Integrity', code: 'C01' },
            ],
        },
    ];

    it('renders trigger shell displaying selected node label and formatted code', () => {
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '11', data: mockTree },
        });

        expect(wrapper.find('.hcis-tree-select__trigger').exists()).toBe(true);
        expect(wrapper.text()).toContain('[K01] Analytical Thinking');
    });

    it('simpul selectable:false tidak pernah menjadi nilai terpilih', async () => {
        // Simpul pengelompokan bukan jawaban yang sah. Membiarkannya dipilih
        // menutup popover dan menyimpan id KELOMPOK sebagai id pilihan -
        // pemakai menyangka sudah memilih, padahal isiannya tidak sah.
        const pohon = [
            {
                value: 'jenis-3',
                label: 'Mobil Penumpang',
                selectable: false,
                children: [{ value: '3', label: 'Toyota Avanza' }],
            },
        ];

        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '', data: pohon, teleportTo: false },
        });

        await wrapper.find('.hcis-tree-select__trigger').trigger('click');
        await wrapper.findAll('.hcis-tree-node')[0].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        // Popovernya tetap terbuka: yang terjadi adalah kelompoknya membuka,
        // bukan pilihan tersimpan.
        expect(wrapper.find('.hcis-tree-select__popover').exists()).toBe(true);

        const anak = wrapper.findAll('.hcis-tree-node');
        await anak[anak.length - 1].trigger('click');

        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['3']);
    });

    it('opens popover tree view when trigger is clicked', async () => {
        // Terbuka-semua diminta EKSPLISIT: bawaan komponen ini tertutup, dan
        // yang diuji di sini adalah render anak, bukan bawaannya.
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, teleportTo: false, defaultExpandAll: true },
        });

        expect(wrapper.find('.hcis-tree-select__popover').exists()).toBe(false);
        await wrapper.find('.hcis-tree-select__trigger').trigger('click');
        expect(wrapper.find('.hcis-tree-select__popover').exists()).toBe(true);
        expect(wrapper.text()).toContain('Kompetensi Manajerial');
        expect(wrapper.text()).toContain('Analytical Thinking');
    });

    it('pohon tertutup secara bawaan: anak tidak terlihat sebelum grupnya dibuka', async () => {
        // Penjaga bawaan itu sendiri. Bawaannya pernah diam-diam dibalik menjadi
        // terbuka-semua, dan tidak ada satu uji pun yang menangkapnya — padahal
        // perubahan itu membuat klik pertama pada grup `selectable: false` justru
        // MENUTUPNYA, sehingga anaknya tak pernah bisa dipilih.
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, teleportTo: false },
        });

        await wrapper.find('.hcis-tree-select__trigger').trigger('click');
        expect(wrapper.text()).toContain('Kompetensi Manajerial');
        expect(wrapper.text()).not.toContain('Analytical Thinking');
    });

    it('emits update:modelValue and change when leaf node is selected', async () => {
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, teleportTo: false, defaultExpandAll: true },
        });

        await wrapper.find('.hcis-tree-select__trigger').trigger('click');
        const leafNodes = wrapper.findAll('.hcis-tree-node.is-leaf');
        expect(leafNodes.length).toBeGreaterThan(0);

        await leafNodes[0].trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['11']);
        expect(wrapper.emitted('change')).toBeTruthy();
        expect(wrapper.emitted('change')[0]).toEqual(['11']);
    });

    it('filters tree nodes when search query is entered', async () => {
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, filterable: true, teleportTo: false },
        });

        await wrapper.find('.hcis-tree-select__trigger').trigger('click');
        const searchInput = wrapper.find('.hcis-search-input');
        expect(searchInput.exists()).toBe(true);

        await searchInput.setValue('Integrity');
        expect(wrapper.text()).toContain('Integrity');
        expect(wrapper.text()).not.toContain('Analytical Thinking');
    });

    it('shows empty state when search finds no results and can reset', async () => {
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, filterable: true, teleportTo: false },
        });

        await wrapper.find('.hcis-tree-select__trigger').trigger('click');
        const searchInput = wrapper.find('.hcis-search-input');
        await searchInput.setValue('non_existent_query_xyz');

        expect(wrapper.find('.hcis-tree-empty-state').exists()).toBe(true);
        expect(wrapper.text()).toContain('Tidak ada simpul ditemukan');

        const resetBtn = wrapper.find('.hcis-tree-empty-reset');
        expect(resetBtn.exists()).toBe(true);
        await resetBtn.trigger('click');
        expect(wrapper.find('.hcis-tree-empty-state').exists()).toBe(false);
    });

    it('clears selection when clear button is clicked', async () => {
        const wrapper = mount(HcisTreeSelect, {
            props: { modelValue: '11', data: mockTree, clearable: true, teleportTo: false },
        });

        const clearBtn = wrapper.find('.hcis-tree-select__clear');
        expect(clearBtn.exists()).toBe(true);
        await clearBtn.trigger('click');

        expect(wrapper.emitted('update:modelValue')).toBeTruthy();
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['']);
        expect(wrapper.emitted('clear')).toBeTruthy();
    });

    it('applies size modifiers correctly', () => {
        const wrapperSm = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, size: 'sm' },
        });
        expect(wrapperSm.classes()).toContain('hcis-tree-select--size-sm');

        const wrapperLg = mount(HcisTreeSelect, {
            props: { modelValue: '', data: mockTree, size: 'lg' },
        });
        expect(wrapperLg.classes()).toContain('hcis-tree-select--size-lg');
    });
});
