<template>
    <div class="app-shell">
        <AppSidebar :user="user" :brand="brand" :navigation="navigation" />

        <div class="shell-main">
            <AppTopbar :user="user" :brand="brand" :layout="layout" :navigation="navigation" />

            <main class="shell-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../components/Shell/AppSidebar.vue';
import AppTopbar from '../components/Shell/AppTopbar.vue';
import { useShellState } from '../composables/useShellState';
import { useActivityHandshake } from '../composables/useActivityHandshake';
import { cleanupLogoutTransition } from '../utils/logout-transition.js';

const shell = useShellState();
const page = usePage();

onMounted(() => {
    try {
        const splashOverlay = document.getElementById('evo-splash-overlay');
        if (splashOverlay) splashOverlay.remove();
        const splashStyles = document.getElementById('evo-splash-styles');
        if (splashStyles) splashStyles.remove();

        if (localStorage.getItem('evo_logout_transition_active') !== null) {
            const bootOverlay = document.getElementById('evo-raw-boot-overlay');
            if (bootOverlay) bootOverlay.remove();
            document.documentElement.classList.remove('evo-boot-logout-active');
            cleanupLogoutTransition();
        }
    } catch (e) {
        // ignore — best-effort rescue
    }
});

useActivityHandshake();

const pageProps = computed(() => page?.props || {});
const user = computed(() => pageProps.value.auth?.user || {});
const layout = computed(() => pageProps.value.layout || {});
const brand = computed(() => layout.value.brand || {});
const navigation = computed(() => layout.value.navigation || { home: null, modules: [] });

watch(
    () => layout.value.shell,
    (nextShell) => {
        shell.syncNavigation({
            activeModule: nextShell?.activeModule || null,
            activeSubMenu: nextShell?.activeSubMenu || null,
        });
    },
    {
        immediate: true,
        deep: true,
    },
);

watch(
    () => page.url,
    () => {
        shell.closeMobileSidebar();
        shell.resetInteractionState();
    },
);
</script>

<style scoped>
.app-shell {
    min-height: 100vh;
    background: #f8fafc;
    overflow-x: clip;
}

.shell-main {
    min-height: 100vh;
    margin-left: 4.5rem;
    overflow-x: clip;
}

.shell-content {
    min-height: calc(100vh - 4rem);
    padding: 1rem;
    overflow-x: clip;
}

:deep(.shell-topbar) {
    margin-left: 0;
    width: 100%;
}

@media (max-width: 991.98px) {
    .shell-main {
        margin-left: 0;
    }
}
</style>
