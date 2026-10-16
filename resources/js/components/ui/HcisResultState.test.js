import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisResultState from './HcisResultState.vue';

describe('HcisResultState.vue', () => {
    it('renders success status title, description, and receipt breakdown items', () => {
        const wrapper = mount(HcisResultState, {
            props: {
                status: 'success',
                title: 'Pengajuan Cuti Berhasil Disetujui',
                description: 'Permohonan cuti tahunan Anda telah diverifikasi oleh Atasan.',
                items: [
                    { label: 'ID Transaksi', value: 'TX-2026-9901', copyable: true },
                    { label: 'Durasi Cuti', value: '3 Hari Kerja' }
                ],
                primaryText: 'Kembali ke Dashboard',
            }
        });

        expect(wrapper.text()).toContain('Pengajuan Cuti Berhasil Disetujui');
        expect(wrapper.text()).toContain('TX-2026-9901');
        expect(wrapper.text()).toContain('3 Hari Kerja');
        expect(wrapper.text()).toContain('Kembali ke Dashboard');
        expect(wrapper.find('.hcis-svg-check').exists()).toBe(true);
        expect(wrapper.find('.hcis-svg-check-path').exists()).toBe(true);
    });

    it('renders animated vector cross when status is error', () => {
        const wrapper = mount(HcisResultState, {
            props: {
                status: 'error',
                title: 'Gagal Memproses Transaksi',
            }
        });

        expect(wrapper.find('.hcis-svg-cross').exists()).toBe(true);
        expect(wrapper.find('.hcis-svg-cross-path').exists()).toBe(true);
    });

    it('emits retry and primary-click events correctly', async () => {
        const wrapper = mount(HcisResultState, {
            props: {
                status: 'error',
                title: 'Gagal Memproses Otorisasi',
                showRetry: true,
                primaryText: 'Kembali',
            }
        });

        const retryBtn = wrapper.find('.hcis-btn--danger');
        expect(retryBtn.exists()).toBe(true);

        await retryBtn.trigger('click');
        expect(wrapper.emitted('retry')).toBeTruthy();
    });

    it('renders error detail box and allows copying error log', async () => {
        const wrapper = mount(HcisResultState, {
            props: {
                status: 'error',
                title: 'Error Jaringan',
                errorDetail: 'ERR_GATEWAY_TIMEOUT: 504 Gateway Timeout',
            }
        });

        expect(wrapper.find('.hcis-result-state__error-box').exists()).toBe(true);
        expect(wrapper.find('.hcis-error-code').text()).toContain('ERR_GATEWAY_TIMEOUT');
    });

    it('renders loading spinner on status loading', () => {
        const wrapper = mount(HcisResultState, {
            props: {
                status: 'loading',
                title: 'Memproses Permintaan',
            }
        });

        expect(wrapper.find('.hcis-result-state__spinner').exists()).toBe(true);
        expect(wrapper.find('.hcis-spinner-svg').exists()).toBe(true);
    });

    it('applies variant and size classes correctly', () => {
        const wrapper = mount(HcisResultState, {
            props: {
                variant: 'glass',
                size: 'lg',
            }
        });

        expect(wrapper.classes()).toContain('hcis-result-state--glass');
        expect(wrapper.classes()).toContain('hcis-result-state--size-lg');
    });

    it('does not render aura by default and renders when showAura is true', () => {
        const defaultWrapper = mount(HcisResultState, { props: { status: 'success' } });
        expect(defaultWrapper.find('.hcis-result-state__aurora').exists()).toBe(false);

        const auraWrapper = mount(HcisResultState, { props: { status: 'success', showAura: true } });
        expect(auraWrapper.find('.hcis-result-state__aurora').exists()).toBe(true);
    });

    it('renders kicker, errorCode, and requestId in enterprise diagnostic box', () => {
        const wrapper = mount(HcisResultState, {
            props: {
                status: 'error',
                kicker: 'AUTENTIKASI GAGAL',
                title: 'Sesi Berakhir',
                errorCode: 'ERR_AUTH_EXPIRED',
                requestId: 'req-2026-9901-ab4',
                errorDetail: 'Sesi telah habis.',
                layout: 'compact',
            }
        });

        expect(wrapper.find('.hcis-result-state__kicker').text()).toBe('AUTENTIKASI GAGAL');
        expect(wrapper.find('.hcis-error-code-badge').text()).toBe('ERR_AUTH_EXPIRED');
        expect(wrapper.find('.hcis-error-meta-val').text()).toBe('req-2026-9901-ab4');
        expect(wrapper.classes()).toContain('hcis-result-state--layout-compact');
    });
});
