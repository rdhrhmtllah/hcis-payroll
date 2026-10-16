import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopKebijakanStats from './SopKebijakanStats.vue';

describe('SopKebijakanStats.vue', () => {
    it('renders policy telemetry stats correctly', () => {
        const wrapper = mount(SopKebijakanStats, {
            props: {
                summary: {
                    total_rules: 12,
                    active_rules: 8,
                    attention_rules: 2,
                    active_runs: 1,
                },
                rulesCount: 12,
                runsCount: 35,
                loading: false,
            },
        });

        expect(wrapper.text()).toContain('Aturan Aktif');
        expect(wrapper.text()).toContain('Proses Berjalan');
        expect(wrapper.text()).toContain('Perlu Perhatian');
        expect(wrapper.text()).toContain('Riwayat Eksekusi');
    });

    it('emits filter-attention when attention card is clicked and has attention items', async () => {
        const wrapper = mount(SopKebijakanStats, {
            props: {
                summary: {
                    total_rules: 5,
                    active_rules: 3,
                    attention_rules: 2,
                    active_runs: 0,
                },
                rulesCount: 5,
                runsCount: 10,
                loading: false,
            },
        });

        const cards = wrapper.findAllComponents({ name: 'HcisStatCard' });
        expect(cards.length).toBe(4);

        // Click 3rd card (Perlu Perhatian)
        await cards[2].trigger('click');
        expect(wrapper.emitted('filter-attention')).toBeTruthy();
    });
});
