import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HcisTimeline from './HcisTimeline.vue';

describe('HcisTimeline.vue', () => {
    const mockTimelineItems = [
        {
            id: 1,
            title: 'Pengajuan Cuti Disampaikan',
            name: 'Rian Hidayat',
            role: 'Staff Requester',
            time: '10 Agu 2026, 08:30',
            status: 'approved',
            content: 'Pengajuan cuti tahunan 3 hari untuk keperluan keluarga.'
        },
        {
            id: 2,
            title: 'Persetujuan Atasan Langsung',
            name: 'Ahmad Fauzi',
            role: 'Direct Manager',
            time: '10 Agu 2026, 10:15',
            status: 'approved',
            content: 'Disetujui. Tugas operasional sudah di-handover ke rekan kerja.'
        },
        {
            id: 3,
            title: 'Verifikasi HR Operations',
            name: 'Siti Rahma',
            role: 'HR Admin',
            status: 'pending',
            content: 'Sedang memeriksa sisa kuota cuti tahunan karyawan.'
        }
    ];

    it('renders timeline title and item entries correctly', () => {
        const wrapper = mount(HcisTimeline, {
            props: {
                // Judul hanya tampil pada mode kartu. Mode standalone (baku
                // sejak mode itu ditambahkan) tidak punya kepala kartu.
                card: true,
                title: 'Audit Trail Cuti Karyawan',
                items: mockTimelineItems,
            }
        });

        expect(wrapper.text()).toContain('Audit Trail Cuti Karyawan');
        expect(wrapper.text()).toContain('Pengajuan Cuti Disampaikan');
        expect(wrapper.text()).toContain('Rian Hidayat');
        expect(wrapper.text()).toContain('Persetujuan Atasan Langsung');
        expect(wrapper.text()).toContain('Ahmad Fauzi');
        expect(wrapper.text()).toContain('Verifikasi HR Operations');
    });

    it('emits item-click event when a timeline item is clicked', async () => {
        const wrapper = mount(HcisTimeline, {
            props: {
                items: mockTimelineItems,
            }
        });

        const itemEls = wrapper.findAll('.hcis-timeline-node');
        expect(itemEls.length).toBe(3);

        await itemEls[1].trigger('click');
        expect(wrapper.emitted('item-click')).toBeTruthy();
        expect(wrapper.emitted('item-click')[0][0].item.name).toBe('Ahmad Fauzi');
    });

    it('applies pulsing animation on active step item', () => {
        const wrapper = mount(HcisTimeline, {
            props: {
                items: mockTimelineItems,
                activeStep: 2,
            }
        });

        const activePulse = wrapper.find('.hcis-timeline-beacon-halo');
        expect(activePulse.exists()).toBe(true);
    });
});
