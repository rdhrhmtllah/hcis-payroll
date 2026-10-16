import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisAutocomplete from './HcisAutocomplete.vue';

describe('HcisAutocomplete.vue', () => {
    const sampleSuggestions = [
        { value: 'Software Engineer', dept: 'IT' },
        { value: 'HR Manager', dept: 'HR' },
        { value: 'Finance Specialist', dept: 'FIN' },
    ];

    it('renders display string when modelValue is provided', async () => {
        const wrapper = mount(HcisAutocomplete, {
            props: {
                modelValue: 'Software Engineer',
                teleportTo: false,
            },
        });

        expect(wrapper.find('input').element.value).toBe('Software Engineer');
    });

    it('displays suggestions when focused', async () => {
        const wrapper = mount(HcisAutocomplete, {
            props: {
                modelValue: '',
                suggestionsList: sampleSuggestions,
                triggerOnFocus: true,
                teleportTo: false,
            },
        });

        await wrapper.find('input').trigger('focus');
        expect(wrapper.findAll('.hcis-autocomplete__item').length).toBe(3);
    });

    it('emits select and update:modelValue when item is clicked', async () => {
        const wrapper = mount(HcisAutocomplete, {
            props: {
                modelValue: '',
                suggestionsList: sampleSuggestions,
                triggerOnFocus: true,
                teleportTo: false,
            },
        });

        await wrapper.find('input').trigger('focus');
        const firstItem = wrapper.findAll('.hcis-autocomplete__item')[0];
        await firstItem.trigger('click');

        expect(wrapper.emitted('select')[0]).toEqual([sampleSuggestions[0]]);
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([sampleSuggestions[0]]);
    });
});
