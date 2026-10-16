import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DashboardPage from './dashboard.vue';
import paApi from '../api/paApi.js';

vi.mock('../api/paApi.js', () => ({
    default: {
        daftarSiklus: vi.fn(),
        ringkasanSiklus: vi.fn(),
        monitoringSiklus: vi.fn(),
        distribusiSiklus: vi.fn(),
        riwayatAdmin: vi.fn(),
    },
}));

const uiStubs = {
    HcisHeader: {
        props: ['title', 'description'],
        template: '<header><h1>{{ title }}</h1><p>{{ description }}</p><slot name="actions" /><slot name="console" /></header>',
    },
    HcisButton: {
        props: ['label'],
        template: '<button>{{ label }}</button>',
    },
    HcisIcon: { template: '<i />' },
    PaAdminControlTower: { template: '<div class="control-tower-stub">Control Tower Component</div>' },
    PaResultDialog: { template: '<div class="result-dialog-stub" />' },
};

describe('PerformanceAppraisalDashboardPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.daftarSiklus.mockResolvedValue({
            siklus: [
                { Id_PA_Cycle: 1, Kode_Cycle: 'PA-2026', Nama_Cycle: 'Siklus 2026', Status_Cycle: 'OPEN' },
            ],
        });
    });

    it('renders the dashboard header and mounts the PaAdminControlTower component', async () => {
        const wrapper = mount(DashboardPage, {
            global: { stubs: uiStubs },
        });

        expect(wrapper.find('h1').text()).toContain('Dashboard Administrator PA');
        expect(wrapper.text()).toContain('Control Tower Component');
    });
});
