import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisAvatarGroup from './HcisAvatarGroup.vue';

describe('HcisAvatarGroup.vue', () => {
    const mockUsers = [
        { name: 'Dr. Ir. Hendra Wijaya', role: 'CEO' },
        { name: 'Budi Santoso', role: 'Direktur HC' },
        { name: 'Ahmad Fauzi', role: 'Manager HCIS' },
        { name: 'Rian Hidayat', role: 'Team Lead' },
        { name: 'Siti Rahma', role: 'Software Engineer' },
        { name: 'Dewi Lestari', role: 'HR Officer' }
    ];

    it('renders stacked avatars up to max limit and displays overflow counter badge', () => {
        const wrapper = mount(HcisAvatarGroup, {
            props: {
                users: mockUsers,
                max: 4,
            }
        });

        const avatarContainers = wrapper.findAll('.hcis-avatar-container');
        expect(avatarContainers.length).toBe(4);

        const overflowBadge = wrapper.find('.hcis-avatar-overflow-badge');
        expect(overflowBadge.exists()).toBe(true);
        expect(overflowBadge.text()).toContain('+2');
    });

    it('opens roster popover when group is clicked', async () => {
        const wrapper = mount(HcisAvatarGroup, {
            props: {
                users: mockUsers,
                interactive: true,
            }
        });

        expect(wrapper.find('.hcis-avatar-roster-popover').exists()).toBe(false);

        await wrapper.find('.hcis-avatar-group').trigger('click');

        expect(wrapper.find('.hcis-avatar-roster-popover').exists()).toBe(true);
        expect(wrapper.text()).toContain('Tim Anggota & Pejabat (6)');
        expect(wrapper.text()).toContain('Siti Rahma');
    });
});
