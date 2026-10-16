import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import VisitorExportStudioPanel from './VisitorExportStudioPanel.vue';

vi.mock('@/components/ui/hcisDialog', () => ({
    hcisConfirm: vi.fn().mockResolvedValue(true),
}));

const ReportStudioStub = {
    name: 'ReportStudio',
    props: ['baseUrl', 'datasetAwal'],
    template: '<div data-test="studio" :data-base-url="baseUrl" :data-dataset="datasetAwal" />',
};

const HcisTabsStub = {
    name: 'HcisTabs',
    props: ['modelValue', 'tabs'],
    emits: ['update:modelValue'],
    template: '<div><button v-for="tab in tabs" :key="tab.key" :data-test="`source-${tab.key}`" @click="$emit(\'update:modelValue\', tab.key)">{{ tab.label }}</button></div>',
};

const HcisCardStub = {
    name: 'HcisCard',
    template: '<section><slot name="title"/><slot name="description"/><slot/></section>',
};

function mountPanel() {
    return mount(VisitorExportStudioPanel, {
        props: { baseUrl: '/api/v1/visitor/export-studio' },
        global: {
            stubs: {
                ReportStudio: ReportStudioStub,
                HcisTabs: HcisTabsStub,
                HcisCard: HcisCardStub,
            },
        },
    });
}

describe('VisitorExportStudioPanel', () => {
    it('starts with the guest dataset and scoped API base URL', () => {
        const wrapper = mountPanel();
        const studio = wrapper.get('[data-test="studio"]');

        expect(studio.attributes('data-dataset')).toBe('tamu');
        expect(studio.attributes('data-base-url')).toBe('/api/v1/visitor/export-studio/tamu');
    });

    it('switches to an independent permit studio', async () => {
        const wrapper = mountPanel();
        await wrapper.get('[data-test="source-permit"]').trigger('click');
        await wrapper.vm.$nextTick();

        const studio = wrapper.get('[data-test="studio"]');
        expect(studio.attributes('data-dataset')).toBe('permit');
        expect(studio.attributes('data-base-url')).toBe('/api/v1/visitor/export-studio/permit');
    });
});
