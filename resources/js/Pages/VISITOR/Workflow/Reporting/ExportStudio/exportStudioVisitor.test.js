import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ExportStudioVisitor from './exportStudioVisitor.vue';

vi.mock('@/components/SYSTEM/ReportStudio/ReportStudio.vue', () => ({
    default: {
        name: 'ReportStudio',
        props: ['baseUrl', 'datasetAwal', 'tabUtama', 'tanpaTopBar'],
        template: '<div data-test="studio" :data-base-url="baseUrl" :data-dataset="datasetAwal" />',
    },
}));

vi.mock('@/components/ui/hcisDialog', () => ({
    hcisConfirm: vi.fn().mockResolvedValue(true),
}));

describe('ExportStudioVisitor', () => {
    const mountPage = (props = {}) =>
        mount(ExportStudioVisitor, {
            props: { baseUrl: '/api/v1/visitor/export-studio', ...props },
            global: {
                stubs: {
                    HcisHeader: {
                        name: 'HcisHeader',
                        template: `
                            <header class="hcis-header-stub">
                                <slot name="actions" />
                                <slot name="tabs" />
                            </header>
                        `,
                    },
                    HcisTabs: true,
                },
            },
        });

    it('memakai HcisHeader design system dengan scope styling sopTheme', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent({ name: 'HcisHeader' }).exists()).toBe(true);
        expect(wrapper.find('.sop-scope').exists()).toBe(true);
    });

    it('memulai dengan dataset awal Buku Tamu (tamu)', () => {
        const wrapper = mountPage();
        const studio = wrapper.get('[data-test="studio"]');

        expect(studio.attributes('data-dataset')).toBe('tamu');
        expect(studio.attributes('data-base-url')).toBe('/api/v1/visitor/export-studio/tamu');
    });

    it('dapat beralih ke dataset Permit Kendaraan (permit)', async () => {
        const wrapper = mountPage();
        const permitBtn = wrapper.findAll('.ses-dataset-btn').find((btn) => btn.text().includes('Permit'));

        expect(permitBtn).toBeTruthy();
        await permitBtn.trigger('click');
        await wrapper.vm.$nextTick();

        const studio = wrapper.get('[data-test="studio"]');
        expect(studio.attributes('data-dataset')).toBe('permit');
        expect(studio.attributes('data-base-url')).toBe('/api/v1/visitor/export-studio/permit');
    });

    it('chip baris menulis "—" selama pratinjau belum ada', () => {
        const wrapper = mountPage();
        const chip = wrapper.findAll('.ses-chip').find((c) => /baris/i.test(c.text()));

        expect(chip).toBeTruthy();
        expect(chip.text()).toContain('—');
    });
});
