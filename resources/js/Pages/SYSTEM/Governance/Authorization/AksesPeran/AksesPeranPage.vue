<template>
    <div class="container-fluid px-0 ap-halaman ap-studio-layout">
        <HcisHeader
            variant="glass"
            sentiment="brand"
            size="md"
            icon="shield-lock-fill"
            :kicker="lingkup.modul ? `Administrator ${lingkup.modul.label}` : 'System · Semua Modul'"
            :title="lingkup.modul ? `Akses & Peran ${lingkup.modul.label}` : 'Akses & Peran'"
            :subtitle="
                lingkup.modul
                    ? `Kelola peran, akses halaman, dan administrator ${lingkup.modul.nama || lingkup.modul.label}.`
                    : 'Peran, penugasan, dan hak akses seluruh modul. Setiap perubahan tercatat di Audit.'
            "
            class="mb-4"
        >
            <template #badge>
                <HcisTooltip v-if="lingkup.anda" :content="teksAnda" placement="bottom" max-width="22rem">
                    <HcisBadge
                        :label="`Anda: ${lingkup.anda.label || '—'}`"
                        variant="brand"
                        appearance="subtle"
                        size="sm"
                        dot
                    />
                </HcisTooltip>
            </template>
            <template #actions>
                <div class="ses-header-actions">
                    <!-- Module Switcher jika berhak kelola lebih dari 1 modul -->
                    <div v-if="lingkup.daftar_modul && lingkup.daftar_modul.length > 1" class="ap-header-switcher-wrap">
                        <HcisSelect
                            :model-value="modulAktifKey"
                            :clearable="false"
                            size="sm"
                            style="min-width: 13.5rem"
                            aria-label="Pindah Modul"
                            @update:model-value="pindahModul"
                        >
                            <HcisSelectOption
                                v-for="m in lingkup.daftar_modul"
                                :key="m.kode"
                                :value="m.kode"
                                :label="m.is_system ? m.label : `${m.label} — ${m.nama}`"
                            />
                        </HcisSelect>
                    </div>

                    <HcisButton
                        variant="glass"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        :loading="memuat"
                        label="Segarkan"
                        title="Muat ulang data otorisasi"
                        @click="muatSemua"
                    />

                    <HcisButton
                        v-if="lingkup.anda?.boleh_tugaskan"
                        variant="primary"
                        size="sm"
                        leading-icon="person-plus"
                        label="Tugaskan Peran"
                        @click="tugaskan({})"
                    />
                </div>
            </template>

            <template #tabs>
                <div class="ses-header-tabs-wrap">
                    <HcisTabs v-model="tab" variant="header" size="sm" :tabs="daftarTab" />
                </div>
            </template>
        </HcisHeader>

        <!-- ══ 2. TAB CONTENT AREA ══ -->
        <div class="ap-studio-content">
            <HcisSkeleton v-if="memuat && !ringkasan" :lines="8" />
            <HcisResultState
                v-else-if="galatMuat && !ringkasan"
                status="error"
                variant="flat"
                size="md"
                title="Data akses belum dapat dimuat"
                description="Periksa koneksi Anda, lalu coba muat ulang halaman ini."
            >
                <template #actions>
                    <HcisButton
                        variant="primary"
                        size="sm"
                        leading-icon="arrow-clockwise"
                        label="Coba Lagi"
                        :loading="memuat"
                        @click="muatSemua"
                    />
                </template>
            </HcisResultState>

            <!-- Tab 1: Ringkasan -->
            <TabRingkasan
                v-else-if="tab === 'ringkasan' && ringkasan"
                :data="ringkasan"
                :anda="lingkup.anda"
                @buka-tab="gantiTab"
                @berubah="muatSemua"
            />

            <!-- Tab 2: Peran & Hak Matriks -->
            <TabPeran
                v-else-if="tab === 'peran'"
                :peran="peran"
                :system-admin="lingkup.system_admin"
                :satu-modul="!!lingkup.modul"
                @berubah="muatSemua"
            />

            <!-- Peta balik: halaman → peran & orang -->
            <TabHalaman v-else-if="tab === 'halaman'" :key="`halaman-${versi}`" />

            <!-- Template cakupan bernama (DDL-2026-184) -->
            <TabCakupan v-else-if="tab === 'cakupan'" :key="`cakupan-${versi}`" />

            <!-- Tab 3: Pengguna & Anggota -->
            <TabPengguna
                v-else-if="tab === 'pengguna'"
                :key="`pengguna-${versi}`"
                :peran="peran"
                :filter-awal="filterPengguna"
            />

            <!-- Tab 4: Administrator Modul -->
            <TabAdmin v-else-if="tab === 'admin'" :admin="admin" :anda="lingkup.anda" @berubah="muatSemua" />

            <!-- Tab 5: Audit Trail -->
            <TabAudit v-else-if="tab === 'audit'" :key="`audit-${versi}`" :peran="peran" />

            <!-- Tab 6: Katalog (System Admin Khusus) -->
            <TabKatalog v-else-if="tab === 'katalog' && lingkup.system_admin" />
        </div>

        <!-- Aksi utama selalu terjangkau jempol di layar kecil. -->
        <div v-if="lingkup.anda?.boleh_tugaskan" class="ap-bilah d-md-none">
            <HcisButton
                variant="primary"
                block
                leading-icon="person-plus"
                label="Tugaskan Peran"
                @click="tugaskan({})"
            />
        </div>

        <!-- Wizard Penugasan Peran Modal -->
        <WizardPenugasan
            v-model="wizard.buka"
            :id-user="wizard.idUser"
            :hanya-admin="wizard.hanyaAdmin"
            :id-role="wizard.idRole"
            :peran-info="wizard.peranInfo"
            :daftar-peran="peran"
            @selesai="selesaiWizard"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, provide, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    HcisBadge,
    HcisButton,
    HcisHeader,
    HcisResultState,
    HcisSelect,
    HcisSelectOption,
    HcisSkeleton,
    HcisTabs,
    HcisTooltip,
    hcisMessage,
} from '@/components/ui';
import TabRingkasan from './components/TabRingkasan.vue';
import TabPeran from './components/TabPeran.vue';
import TabPengguna from './components/TabPengguna.vue';
import TabHalaman from './components/TabHalaman.vue';
import TabCakupan from './components/TabCakupan.vue';
import TabAdmin from './components/TabAdmin.vue';
import TabAudit from './components/TabAudit.vue';
import TabKatalog from './components/TabKatalog.vue';
import WizardPenugasan from './components/WizardPenugasan.vue';
import { aksesPeranApi, aturKonteksModul } from './api/aksesPeranApi.js';

defineOptions({ name: 'AksesPeranPage' });

const props = defineProps({
    lingkup: { type: Object, default: () => ({ semua_modul: false, system_admin: false, modul: null, anda: null }) },
});
// Halaman per modul: semua permintaan API terkunci ke modul ini.
aturKonteksModul(props.lingkup.modul?.kode);

const modulAktifKey = computed(() => props.lingkup.modul?.kode || 'system');

function pindahModul(kode) {
    const target = (props.lingkup.daftar_modul || []).find((m) => m.kode === kode);
    if (target && target.url && target.kode !== modulAktifKey.value) {
        router.visit(target.url);
    }
}

const tab = ref('ringkasan');
const memuat = ref(false);
const ringkasan = ref(null);
const peran = ref([]);
const admin = ref([]);
const versi = ref(0);
const filterPengguna = ref({});
const galatMuat = ref(false);
const wizard = reactive({ buka: false, idUser: null, hanyaAdmin: false, idRole: null, peranInfo: null, setelah: null });

const teksAnda = computed(() => {
    const a = props.lingkup.anda;
    if (!a) return '';
    const bisa = [
        a.boleh_angkat && 'mengangkat admin',
        a.boleh_ubah_matriks && 'mengatur hak peran',
        a.boleh_tugaskan && 'menugaskan peran & memberi akses',
    ].filter(Boolean);
    return bisa.length
        ? `Sebagai ${a.label} Anda bisa ${bisa.join(', ')} — hanya untuk tingkat di bawah Anda.`
        : `Sebagai ${a.label} Anda hanya bisa melihat.`;
});

const daftarTab = computed(() => [
    { key: 'ringkasan', label: 'Ringkasan', icon: 'speedometer2' },
    { key: 'peran', label: 'Peran & Hak', icon: 'sliders', badge: peran.value.length || 0 },
    { key: 'halaman', label: 'Halaman', icon: 'window-stack' },
    { key: 'cakupan', label: 'Template Cakupan', icon: 'funnel' },
    { key: 'pengguna', label: 'Pengguna', icon: 'people-fill' },
    { key: 'admin', label: 'Administrator', icon: 'shield-check', badge: admin.value.length || 0 },
    { key: 'audit', label: 'Audit', icon: 'clock-history' },
    ...(props.lingkup.system_admin ? [{ key: 'katalog', label: 'Katalog', icon: 'toggles' }] : []),
]);

function gantiTab(t) {
    if (typeof t === 'string') {
        filterPengguna.value = {};
        tab.value = t;
    } else if (t && typeof t === 'object') {
        tab.value = t.tab || 'ringkasan';
        if (t.tab === 'pengguna' && t.hanyaSegeraBerakhir) {
            filterPengguna.value = { hanyaSegeraBerakhir: true };
        } else {
            filterPengguna.value = {};
        }
    }
}

/** Dibagikan ke semua tab: satu wizard penugasan untuk seluruh halaman. */
function tugaskan({ idUser = null, hanyaAdmin = false, idRole = null, peranInfo = null, setelah = null } = {}) {
    Object.assign(wizard, { buka: true, idUser, hanyaAdmin, idRole, peranInfo, setelah });
}
provide('aksiAkses', { tugaskan, segarkan: () => muatSemua() });

async function selesaiWizard() {
    if (wizard.setelah) await wizard.setelah();
    versi.value++;
    await muatSemua();
}

async function muatSemua() {
    memuat.value = true;
    try {
        [ringkasan.value, peran.value, admin.value] = await Promise.all([
            aksesPeranApi.ringkasan(),
            aksesPeranApi.peran().then((r) => r || []),
            aksesPeranApi.admin().then((r) => r || []),
        ]);
        galatMuat.value = false;
    } catch {
        galatMuat.value = true;
        hcisMessage.error('Data akses gagal dimuat. Silakan coba lagi.');
    } finally {
        memuat.value = false;
    }
}

onMounted(muatSemua);
</script>

<style scoped>
.ses-header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.ses-header-tabs-wrap {
    display: flex;
    align-items: center;
    width: 100%;
}

.ap-studio-content {
    margin-bottom: var(--hcis-space-5, 2rem);
}

.ap-bilah {
    position: sticky;
    bottom: 0;
    z-index: 5;
    padding: var(--hcis-space-2) 0;
    background: var(--hcis-surface);
    border-top: 1px solid var(--hcis-border-subtle);
}

@media (max-width: 767.98px) {
    .ses-header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    .ap-halaman {
        padding-bottom: var(--hcis-space-2);
    }
}
</style>
