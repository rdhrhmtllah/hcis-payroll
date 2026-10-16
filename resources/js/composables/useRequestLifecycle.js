import { shallowRef } from 'vue';

export function useRequestLifecycle() {
    const controllers = shallowRef({});
    const versions = shallowRef({});

    function start(key) {
        controllers.value[key]?.abort?.();

        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const version = Number(versions.value[key] || 0) + 1;

        controllers.value = { ...controllers.value, [key]: controller };
        versions.value = { ...versions.value, [key]: version };

        return { controller, signal: controller?.signal, version };
    }

    function isLatest(key, version) {
        return Number(versions.value[key] || 0) === Number(version || 0);
    }

    function finish(key, version) {
        if (!isLatest(key, version)) return;

        const next = { ...controllers.value };
        delete next[key];
        controllers.value = next;
    }

    function cancel(key) {
        controllers.value[key]?.abort?.();
        const next = { ...controllers.value };
        delete next[key];
        controllers.value = next;
    }

    function cancelAll() {
        Object.values(controllers.value).forEach((controller) => controller?.abort?.());
        controllers.value = {};
    }

    return { start, isLatest, finish, cancel, cancelAll };
}
