import { describe, expect, it } from 'vitest';
import { trapFocusWithin } from './dialogFocus';

function makeTabEvent(dialog, shiftKey = false) {
    return {
        currentTarget: dialog,
        shiftKey,
        preventDefault: () => {},
    };
}

describe('trapFocusWithin', () => {
    it('wraps Tab from the last control to the first', () => {
        document.body.innerHTML = '<section tabindex="-1"><button id="first">First</button><button id="last">Last</button></section>';
        const dialog = document.querySelector('section');
        const first = document.querySelector('#first');
        const last = document.querySelector('#last');
        last.focus();

        trapFocusWithin(makeTabEvent(dialog));

        expect(document.activeElement).toBe(first);
    });

    it('wraps Shift+Tab from the first control to the last', () => {
        document.body.innerHTML = '<section tabindex="-1"><button id="first">First</button><button id="last">Last</button></section>';
        const dialog = document.querySelector('section');
        const first = document.querySelector('#first');
        const last = document.querySelector('#last');
        first.focus();

        trapFocusWithin(makeTabEvent(dialog, true));

        expect(document.activeElement).toBe(last);
    });
});
