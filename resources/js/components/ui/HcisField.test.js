import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisField from './HcisField.vue';

describe('HcisField.vue', () => {
    it('renders label and updates modelValue on input', async () => {
        const wrapper = mount(HcisField, {
            props: {
                id: 'nama-field',
                label: 'Nama Lengkap',
                modelValue: 'Budi',
            },
        });

        expect(wrapper.find('label').text()).toContain('Nama Lengkap');
        expect(wrapper.find('input').element.value).toBe('Budi');

        await wrapper.find('input').setValue('Budi Santoso');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['Budi Santoso']);
    });

    it('renders textarea when type="textarea"', async () => {
        const wrapper = mount(HcisField, {
            props: {
                id: 'catatan-field',
                label: 'Catatan Alasan Cuti',
                type: 'textarea',
                modelValue: 'Alasan pribadi',
            },
        });

        expect(wrapper.find('textarea').exists()).toBe(true);
        expect(wrapper.find('textarea').element.value).toBe('Alasan pribadi');
    });

    it('toggles password visibility when showPasswordToggle is active', async () => {
        const wrapper = mount(HcisField, {
            props: {
                id: 'password-field',
                label: 'Kata Sandi',
                type: 'password',
                showPasswordToggle: true,
                modelValue: 'secret123',
            },
        });

        expect(wrapper.find('input').attributes('type')).toBe('password');

        await wrapper.find('.hcis-field__toggle-btn').trigger('click');
        expect(wrapper.find('input').attributes('type')).toBe('text');
    });

    it('renders error message and applies error state class', async () => {
        const wrapper = mount(HcisField, {
            props: {
                id: 'email-field',
                label: 'Alamat Email',
                error: 'Email tidak valid',
            },
        });

        expect(wrapper.classes()).toContain('hcis-field--error');
        expect(wrapper.find('.hcis-field__error').text()).toContain('Email tidak valid');
    });
});
