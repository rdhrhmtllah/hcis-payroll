/** @type { import('@storybook/vue3-vite').StorybookConfig } */
const config = {
    stories: ['../resources/js/components/ui/**/*.stories.@(js|jsx|mjs|ts|tsx)'],
    addons: ['@storybook/addon-a11y'],
    framework: {
        name: '@storybook/vue3-vite',
        options: {},
    },
    docs: {
        autodocs: 'tag',
    },
};

export default config;
