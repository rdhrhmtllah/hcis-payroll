import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import VisitorApprovalContext from './VisitorApprovalContext.vue';

describe('VisitorApprovalContext', () => {
    it('explains automatic approval mode', () => {
        const wrapper = mount(VisitorApprovalContext, {
            props: {
                entityLabel: 'permit',
                approvalPolicy: { needs_approval: false },
                config: { feature_code: 'VISITOR_PERMIT' },
            },
        });

        expect(wrapper.text()).toContain('langsung aktif setelah dikirim');
        expect(wrapper.text()).toContain('Persetujuan otomatis');
    });

    it('shows a safe blocking state when approval is required without approvers', () => {
        const wrapper = mount(VisitorApprovalContext, {
            props: {
                entityLabel: 'tamu',
                approvalPolicy: { needs_approval: true },
                config: { approver_limit: 2 },
                approverFlows: [],
            },
        });

        expect(wrapper.text()).toContain('Approver tamu belum tersedia');
        expect(wrapper.text()).toContain('dinonaktifkan sementara');
    });
});
