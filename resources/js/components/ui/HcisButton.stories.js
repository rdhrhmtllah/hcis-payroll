import HcisButton from './HcisButton.vue';

export default {
    title: 'HCIS/Button',
    component: HcisButton,
    tags: ['autodocs'],
    argTypes: {
        variant: { control: 'select', options: ['primary', 'secondary', 'ghost', 'danger'] },
        size: { control: 'select', options: ['sm', 'md', 'lg'] },
        loading: { control: 'boolean' },
        disabled: { control: 'boolean' },
        block: { control: 'boolean' },
    },
};

export const Primary = {
    args: { label: 'Simpan perubahan', variant: 'primary' },
};

export const Variants = {
    render: () => ({
        components: { HcisButton },
        template: `
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <HcisButton label="Primary" />
                <HcisButton label="Secondary" variant="secondary" />
                <HcisButton label="Ghost" variant="ghost" />
                <HcisButton label="Danger" variant="danger" />
            </div>
        `,
    }),
};

export const States = {
    render: () => ({
        components: { HcisButton },
        template: `
            <div style="display:flex;gap:12px;flex-wrap:wrap">
                <HcisButton label="Loading" :loading="true" />
                <HcisButton label="Disabled" disabled />
                <HcisButton label="Full width" block />
            </div>
        `,
    }),
};
