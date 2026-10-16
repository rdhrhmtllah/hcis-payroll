import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisHeader from './HcisHeader.vue';

describe('HcisHeader.vue', () => {
    it('renders title, subtitle, icon, and badge correctly', async () => {
        const wrapper = mount(HcisHeader, {
            props: {
                title: 'Library Konten',
                subtitle: 'Pusat pustaka berkas resmi rujukan penyusunan SOP.',
                icon: 'folder-symlink-fill',
                badge: 'Fitur Utama',
                variant: 'shero',
            },
        });

        expect(wrapper.find('.hcis-header__title').text()).toContain('Library Konten');
        expect(wrapper.find('.hcis-header__subtitle').text()).toContain('Pusat pustaka berkas resmi');
        expect(wrapper.find('.hcis-header__icon-badge').exists()).toBe(true);
        expect(wrapper.classes()).toContain('hcis-header--variant-shero');
    });

    it('renders slots for breadcrumb, actions, and console stats area', async () => {
        const wrapper = mount(HcisHeader, {
            props: {
                title: 'Manajemen Pegawai',
            },
            slots: {
                breadcrumb: '<div class="test-bc">Nav / Pegawai</div>',
                actions: '<button class="test-btn">Tambah Pegawai</button>',
                console: '<div class="test-stat">Stat Console</div>',
            },
        });

        expect(wrapper.find('.test-bc').exists()).toBe(true);
        expect(wrapper.find('.test-btn').exists()).toBe(true);
        expect(wrapper.find('.test-stat').exists()).toBe(true);
    });

    it('renders tabs slot correctly in bottom tabs bar', async () => {
        const wrapper = mount(HcisHeader, {
            props: {
                title: 'Master Dokumen',
            },
            slots: {
                tabs: '<div class="test-header-tabs">Integrated Tabs</div>',
            },
        });

        expect(wrapper.find('.hcis-header__tabs-bar').exists()).toBe(true);
        expect(wrapper.find('.test-header-tabs').text()).toBe('Integrated Tabs');
    });

    it('renders layout variations (compact, centered, minimal, stacked)', async () => {
        const compactWrapper = mount(HcisHeader, {
            props: {
                title: 'Data Log',
                layout: 'compact',
            },
        });
        expect(compactWrapper.classes()).toContain('hcis-header--layout-compact');

        const centeredWrapper = mount(HcisHeader, {
            props: {
                title: 'Selamat Datang',
                layout: 'centered',
            },
        });
        expect(centeredWrapper.classes()).toContain('hcis-header--layout-centered');

        const minimalWrapper = mount(HcisHeader, {
            props: {
                title: 'Pengaturan',
                layout: 'minimal',
            },
        });
        expect(minimalWrapper.classes()).toContain('hcis-header--layout-minimal');
        expect(minimalWrapper.find('.hcis-header__aurora').exists()).toBe(false);
    });
});
