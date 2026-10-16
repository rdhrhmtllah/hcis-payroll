import HcisBadge from './HcisBadge.vue';
import HcisCard from './HcisCard.vue';
import HcisEmptyState from './HcisEmptyState.vue';
import HcisField from './HcisField.vue';

export default {
    title: 'HCIS/Primitives',
    tags: ['autodocs'],
};

export const Badge = {
    render: () => ({
        components: { HcisBadge },
        template: `
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <HcisBadge label="Neutral" />
                <HcisBadge label="Approved" variant="success" />
                <HcisBadge label="Pending" variant="warning" />
                <HcisBadge label="Rejected" variant="danger" />
                <HcisBadge label="Info" variant="info" />
            </div>
        `,
    }),
};

export const Card = {
    render: () => ({
        components: { HcisCard },
        template: `
            <HcisCard title="Employee summary" description="Reusable surface with shared spacing and hierarchy.">
                <p style="margin:0;color:var(--hcis-text-muted)">Card content stays intentionally unopinionated.</p>
            </HcisCard>
        `,
    }),
};

export const Field = {
    render: () => ({
        components: { HcisField },
        data: () => ({ value: '' }),
        template: '<HcisField id="storybook-name" v-model="value" label="Nama karyawan" placeholder="Masukkan nama" hint="Hint dan error memakai kontrak yang sama." />',
    }),
};

export const EmptyState = {
    render: () => ({
        components: { HcisEmptyState },
        template: '<HcisEmptyState icon="○" title="Belum ada data" description="Gunakan empty state untuk menjelaskan kondisi dan tindakan berikutnya." />',
    }),
};
