import { describe, expect, it } from 'vitest';
import { useRequestLifecycle } from './useRequestLifecycle';

describe('useRequestLifecycle', () => {
    it('aborts the previous request and only accepts the latest version', () => {
        const lifecycle = useRequestLifecycle();
        const first = lifecycle.start('members');
        const second = lifecycle.start('members');

        expect(first.signal.aborted).toBe(true);
        expect(second.signal.aborted).toBe(false);
        expect(lifecycle.isLatest('members', first.version)).toBe(false);
        expect(lifecycle.isLatest('members', second.version)).toBe(true);
    });

    it('keeps separate domains independent and cancels all on cleanup', () => {
        const lifecycle = useRequestLifecycle();
        const members = lifecycle.start('members');
        const shifts = lifecycle.start('shifts');

        lifecycle.cancelAll();

        expect(members.signal.aborted).toBe(true);
        expect(shifts.signal.aborted).toBe(true);
    });

    it('does not remove a newer controller when an older request finishes', () => {
        const lifecycle = useRequestLifecycle();
        const first = lifecycle.start('heatmap');
        const second = lifecycle.start('heatmap');

        lifecycle.finish('heatmap', first.version);
        lifecycle.cancel('heatmap');

        expect(second.signal.aborted).toBe(true);
    });
});
