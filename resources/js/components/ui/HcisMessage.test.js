import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HcisMessage from './HcisMessage.vue';
import { hcisMessage } from './hcisMessage.js';

describe('hcisMessage and HcisMessage toast notification engine', () => {
    it('creates a toast notification element on body when called functionally', async () => {
        hcisMessage.success('Toast Sukses Ditampilkan', 'Judul Tes');

        await new Promise((resolve) => setTimeout(resolve, 50));

        const container = document.getElementById('hcis-toast-container');
        expect(container).not.toBeNull();
        expect(container.textContent).toContain('Toast Sukses Ditampilkan');
        expect(container.textContent).toContain('Judul Tes');
    });

    it('clears all toasts when closeAll is called', async () => {
        hcisMessage.info('Pesan 1');
        hcisMessage.warning('Pesan 2');

        await new Promise((resolve) => setTimeout(resolve, 50));
        hcisMessage.closeAll();
        await new Promise((resolve) => setTimeout(resolve, 50));

        const container = document.getElementById('hcis-toast-container');
        expect(container.querySelectorAll('.hcis-toast').length).toBe(0);
    });

    it('renders inline HcisMessage component correctly', () => {
        const wrapper = mount(HcisMessage, {
            props: {
                inline: true,
                type: 'error',
                title: 'Galat Validasi',
                message: 'Isian wajib dilengkapi',
            },
        });

        // Mode inline kini memakai kelas .hcis-message, bukan .hcis-toast-inline.
        expect(wrapper.find('.hcis-message').exists()).toBe(true);
        expect(wrapper.text()).toContain('Galat Validasi');
        expect(wrapper.text()).toContain('Isian wajib dilengkapi');
    });
});
