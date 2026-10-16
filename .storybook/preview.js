import '../../resources/css/app.css';
import '../../resources/css/design-system.css';

/** @type { import('@storybook/vue3-vite').Preview } */
const preview = {
    parameters: {
        layout: 'padded',
        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },
        a11y: {
            test: 'todo',
        },
    },
};

export default preview;
