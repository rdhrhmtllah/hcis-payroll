import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ContentDashboard from './contentDashboard.vue';

// Mock subcomponents
vi.mock('./components/ui/DetailsPanel.vue', () => ({
    default: { name: 'DetailsPanel', template: '<div class="mock-details-panel" />' },
}));
vi.mock('./components/ui/MediaViewerPanel.vue', () => ({
    default: { name: 'MediaViewerPanel', template: '<div class="mock-media-viewer" />' },
}));
vi.mock('./components/ui/ContentContainer.vue', () => ({
    default: { name: 'ContentContainer', template: '<div class="mock-content-container" />' },
}));
vi.mock('./components/ui/breadcrumb.vue', () => ({
    default: { name: 'MediaBreadcrumbs', template: '<div class="mock-breadcrumb" />' },
}));
vi.mock('./components/ui/CreateCategoryModal.vue', () => ({
    default: { name: 'CreateCategoryModal', template: '<div class="mock-create-cat" />' },
}));
vi.mock('./components/ui/UploadKontenModal.vue', () => ({
    default: { name: 'UploadKontenModal', template: '<div class="mock-upload-modal" />' },
}));
vi.mock('@/components/legacy/header.vue', () => ({
    default: { name: 'HeaderKpi', template: '<div class="mock-header-kpi" />' },
}));
vi.mock('@/components/legacy/quickStat.vue', () => ({
    default: { name: 'QuickStat', template: '<div class="mock-quick-stat" />' },
}));
vi.mock('@/components/legacy/toggleSectionV2.vue', () => ({
    default: { name: 'ApprovalTabs', template: '<div><slot name="konten" /></div>' },
}));
vi.mock('@/components/legacy/Filter/modernFilter.vue', () => ({
    default: { name: 'ModernFilter', template: '<div class="mock-modern-filter" />' },
}));
vi.mock('@/components/legacy/modalV2-noir.vue', () => ({
    default: { name: 'Modal', template: '<div class="mock-modal"><slot /><slot name="footer" /></div>' },
}));
vi.mock('axios', () => {
    const mockAxios = {
        get: vi.fn(() => Promise.resolve({ data: { success: true, result: { data: [] } } })),
        post: vi.fn(() => Promise.resolve({ data: { success: true } })),
        delete: vi.fn(() => Promise.resolve({ data: { success: true } })),
    };
    return {
        default: mockAxios,
        ...mockAxios,
    };
});

describe('ContentDashboard.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders HcisHeader and HcisStatGroup when scope is SOP', () => {
        const wrapper = mount(ContentDashboard, {
            props: {
                scope: 'SOP',
                basePath: '/sop/admin/konten',
            },
        });

        const header = wrapper.findComponent({ name: 'HcisHeader' });
        expect(header.exists()).toBe(true);
        expect(header.props('title')).toBe('Library Konten');
        expect(header.props('kicker')).toBe('Tata Kelola · Media & Berkas');
        expect(header.props('sentiment')).toBe('brand');

        const statGroup = wrapper.findComponent({ name: 'HcisStatGroup' });
        expect(statGroup.exists()).toBe(true);

        const statCards = wrapper.findAllComponents({ name: 'HcisStatCard' });
        expect(statCards.length).toBe(3);

        // Does not render legacy .shero markup
        expect(wrapper.find('.shero').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'HeaderKpi' }).exists()).toBe(false);
    });

    it('renders HeaderKpi and QuickStat when scope is LMS', () => {
        const wrapper = mount(ContentDashboard, {
            props: {
                scope: 'LMS',
                basePath: '/lms/konten-kategori',
            },
        });

        expect(wrapper.findComponent({ name: 'HcisHeader' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'HeaderKpi' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'QuickStat' }).exists()).toBe(true);
    });
});
