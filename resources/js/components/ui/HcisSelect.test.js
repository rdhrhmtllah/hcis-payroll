import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisSelect from './HcisSelect.vue';
import HcisSelectOption from './HcisSelectOption.vue';

describe('HcisSelect.vue & HcisSelectOption.vue', () => {
    it('renders select trigger shell displaying placeholder and selected value', async () => {
        const wrapper = mount(HcisSelect, {
            props: { modelValue: 'id' },
            slots: {
                default: `
                    <HcisSelectOption value="id" label="Indonesia" />
                    <HcisSelectOption value="en" label="English" />
                `,
            },
            global: {
                components: { HcisSelectOption },
            },
        });

        expect(wrapper.find('.hcis-select__trigger').exists()).toBe(true);
    });

    it('opens popover panel when trigger is clicked', async () => {
        const wrapper = mount(HcisSelect, {
            props: { modelValue: '', teleportTo: false },
            slots: {
                default: `
                    <HcisSelectOption value="id" label="Indonesia" />
                    <HcisSelectOption value="en" label="English" />
                `,
            },
            global: {
                components: { HcisSelectOption },
            },
        });

        // Popover-nya dipasang v-show, bukan v-if: elemennya SELALU ada di DOM
        // dan yang berubah keterlihatannya, jadi exists() tidak bisa
        // membedakan terbuka dari tertutup. isVisible() juga tidak dipakai --
        // elemennya berada di dalam <Teleport> yang tidak ter-attach ke
        // dokumen saat diuji, sehingga selalu terbaca tersembunyi.
        //
        // Yang dibaca: gaya sebaris pada popover-nya, plus penanda milik
        // komponennya sendiri pada pemicunya.
        const popover = () => wrapper.find('.hcis-select__popover');
        const pemicu = () => wrapper.find('.hcis-select__trigger');

        expect(popover().attributes('style')).toContain('display: none');
        expect(pemicu().classes()).not.toContain('hcis-select__trigger--open');

        await pemicu().trigger('click');

        expect(popover().attributes('style')).not.toContain('display: none');
        expect(pemicu().classes()).toContain('hcis-select__trigger--open');
    });

    it('exposes combobox semantics and supports keyboard selection', async () => {
        const wrapper = mount(HcisSelect, {
            props: { modelValue: '', ariaLabel: 'Pilih bahasa', teleportTo: false, filterable: false },
            slots: {
                default: `
                    <HcisSelectOption value="id" label="Indonesia" />
                    <HcisSelectOption value="en" label="English" />
                `,
            },
            global: {
                components: { HcisSelectOption },
            },
        });

        const trigger = wrapper.find('.hcis-select__trigger');
        expect(trigger.attributes()).toMatchObject({
            role: 'combobox',
            tabindex: '0',
            'aria-label': 'Pilih bahasa',
            'aria-expanded': 'false',
        });

        await trigger.trigger('keydown', { key: 'Enter' });
        expect(trigger.attributes('aria-expanded')).toBe('true');

        const option = wrapper.findAll('.hcis-select-option')[1];
        await option.trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['en']);
    });

    it('keeps a selected option icon color in the trigger display', async () => {
        const wrapper = mount(HcisSelect, {
            props: {
                modelValue: 'red',
                selectedIcon: 'circle-fill',
                selectedIconColor: '#dc2626',
                teleportTo: false,
            },
            slots: {
                default: `
                    <HcisSelectOption value="red" label="Merah" icon="circle-fill" iconColor="#dc2626" />
                    <HcisSelectOption value="green" label="Hijau" icon="circle-fill" iconColor="#16a34a" />
                `,
            },
            global: {
                components: { HcisSelectOption },
            },
        });

        await wrapper.vm.$nextTick();
        const icon = wrapper.find('.hcis-display-text .hcis-icon');
        expect(icon.exists()).toBe(true);
        expect(icon.attributes('style')).toContain('color');
    });

    it('displays selected label on mobile mode on initial load and after selection', async () => {
        // Mock mobile window width
        const originalInnerWidth = window.innerWidth;
        window.innerWidth = 400;

        const wrapper = mount(HcisSelect, {
            props: {
                modelValue: 'ktp',
                teleportTo: false,
            },
            slots: {
                default: `
                    <HcisSelectOption value="ktp" label="Kartu Tanda Penduduk" />
                    <HcisSelectOption value="sim" label="Surat Izin Mengemudi" />
                `,
            },
            global: {
                components: { HcisSelectOption },
            },
        });

        /* Opsi mendaftar lewat queueMicrotask, dan saat mount komponen pindah
           dari cabang desktop ke cabang mobile (isMobile dihitung di onMounted).
           Satu nextTick belum cukup; antrean microtask harus dikosongkan —
           yang di peramban selesai sebelum layar sempat dirender. */
        await flushPromises();

        // Selected label must be visible on mobile immediately
        expect(wrapper.find('.hcis-display-text').text()).toContain('Kartu Tanda Penduduk');

        // Select the second option
        const options = wrapper.findAll('.hcis-select-option');
        expect(options.length).toBe(2);
        await options[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['sim']);

        window.innerWidth = originalInnerWidth;
    });
});
