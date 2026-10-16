<template>
    <div class="app-shell">
        <AppSidebar :user="user" :brand="brand" :navigation="navigation" />

        <div class="shell-main">
            <AppTopbar :user="user" :brand="brand" :layout="layout" :navigation="navigation" />

            <main class="shell-content">
                <BarPembaruanData v-if="pd.tampilkanBar.value" :status="pd.data.value.status"
                    @isi="pd.bukaFormulir" />

                <slot />
            </main>
        </div>

        <ModalPembaruanData
            v-if="pd.tampilkanModal.value"
            :pengumuman="pd.data.value.pengumuman"
            :status="pd.data.value.status"
            :maks-penundaan="maksPenundaan"
            @tampil="pd.catatTampil"
            @tunda="pd.tunda"
            @isi="pd.bukaFormulir"
        />
    </div>
</template>

<script setup>
import { computed, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../components/Shell/AppSidebar.vue';
import AppTopbar from '../components/Shell/AppTopbar.vue';
import { useShellState } from '../composables/useShellState';
import { useActivityHandshake } from '../composables/useActivityHandshake';
import { usePengumpulanDataGate } from '@/composables/ESS/Employee/DataCollection/usePengumpulanDataGate.js';
import ModalPembaruanData from '../Pages/ESS/Employee/DataCollection/components/ModalPembaruanData.vue';
import BarPembaruanData from '../Pages/ESS/Employee/DataCollection/components/BarPembaruanData.vue';
import { cleanupLogoutTransition } from '../utils/logout-transition.js';

const shell = useShellState();
const page = usePage();

// Modal & bar pembaruan data. Dipasang di sini karena AppShell adalah SATU-
// SATUNYA layout: apa pun halaman yang dibuka orang, gerbangnya ikut. Prop
// 'pengumpulanData' bernilai null saat kampanye mati, orang dikecualikan, atau
// datanya sudah terkirim — jadi keduanya tidak merender apa pun.
const pd = usePengumpulanDataGate();
const maksPenundaan = computed(() => (pd.data.value?.status?.sisa_tunda ?? 0) + (pd.data.value?.status?.jumlah_tunda ?? 0));

// Pencatatan kemunculan TIDAK dipicu dari sini. Modal sendiri yang memancarkan
// 'tampil' saat benar-benar terpasang di DOM — lihat ModalPembaruanData.vue.

// Rescue "nyangkut" users. The logout bridge (boot overlay + entrance overlay)
// only ever targets the Login page (layout: null). If this authenticated shell
// mounts while the logout flag is still set, the flag is stale/stuck — tear the
// boot overlay down and clear the flag so the user is freed on any page they reach.
onMounted(() => {
    try {
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

// Kirim fingerprint + screen + timezone sekali per tab session ke
// /api/v1/activity/handshake. Best-effort, tidak menggagalkan apa pun.
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
