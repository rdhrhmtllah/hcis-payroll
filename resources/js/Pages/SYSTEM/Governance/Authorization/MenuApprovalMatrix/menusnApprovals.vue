<template>
    <div class="matrix-app-container">
        <HeaderKpi
            :title="`Menu, Approval & Keanggotaan ${activeGrupCode}`"
            :subtitle="`Kelola akses, alur persetujuan, dan struktur keanggotaan ${activeGrupName}`"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
        />
        <BannerAksesPeran :modul-baru="modulBaru" :tautan="tautanAksesPeran" :modul-ini-baru="modeGrup === 'BARU'" />

        <section class="access-context" aria-label="Konteks akses halaman">
            <div class="group-context">
                <span class="group-icon"><i :class="activeGrupIcon"></i></span>
                <span>
                    <small>Grup aktif</small>
                    <strong>{{ activeGrupName }}</strong>
                    <em>{{ activeGrupCode }}</em>
                </span>
            </div>
            <div class="access-context__right">
                <span class="mode-badge">
                    <i class="bi bi-person-badge"></i>
                    {{ capabilities.isSystemAdmin ? 'System Admin' : 'Admin Module' }}
                </span>
                <span class="target-scope-badge" :class="{ 'target-scope-badge--denied': targetScope.mode === 'DENIED', 'target-scope-badge--legacy': targetScope.mode === 'LEGACY_GLOBAL' }">
                    <i class="bi bi-people"></i>
                    {{ targetScopeLabel }}
                </span>
                <div class="capability-list">
                    <span v-for="item in capabilityBadges" :key="item.id" class="capability-badge">
                        <i :class="item.icon"></i>
                        {{ item.label }}
                    </span>
                </div>
            </div>
        </section>

        <div class="main-content-wrapper">
            <div class="content-container">
                <ApprovalTabs
                    v-if="tabs.length"
                    contentPadding="0rem"
                    :headerBgColor="false"
                    :tabs="tabs"
                    :activeTab="activeTab"
                    @tab-change="handleTabChange"
                >
                    <template #menu>
                        <MenuTab
                            :grupId="activeGrupId"
                            :canAssign="capabilities.menu.canAssign"
                            :isSystemAdmin="capabilities.isSystemAdmin"
                            :activeGrup="activeGrup"
                        />
                    </template>

                    <template #approval-matrix>
                        <ApprovalMatrixTab
                            :grupId="activeGrupId"
                            :capabilities="capabilities"
                            :activeGrup="activeGrup"
                        />
                    </template>

                    <template #membership>
                        <MembershipTab
                            :grupId="activeGrupId"
                            :canAssign="capabilities.membership.canAssign"
                        />
                    </template>
                </ApprovalTabs>

                <div v-else class="stitch-card p-4 text-center text-muted">
                    <i class="bi bi-shield-lock fs-3 d-block mb-2"></i>
                    Anda tidak memiliki akses ke tab mana pun di halaman ini.
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import BannerAksesPeran from '../AksesPeran/components/BannerAksesPeran.vue';
import axios from 'axios';
import HeaderKpi from '@/components/legacy/header.vue';
import ApprovalTabs from '@/components/legacy/toggleSectionV2.vue';
import MenuTab from './components/MenuTab.vue';
import ApprovalMatrixTab from './components/ApprovalMatrixTab.vue';
import MembershipTab from './components/MembershipTab.vue';

const EMPTY_CAPABILITIES = {
    isSystemAdmin: false,
    manageableGroupIds: [],
    menu: { canView: false, canAssign: false },
    approvalMatrix: { canView: false, canAssign: false },
    membership: { canView: false, canAssign: false, canConfigure: false },
    targetScope: { mode: 'DENIED', labels: [], scope_codes: [], target_count: 0 },
};

export default {
    name: 'MenuApprovalMatrixPage',
    components: {
        BannerAksesPeran,
        HeaderKpi,
        ApprovalTabs,
        MenuTab,
        ApprovalMatrixTab,
        MembershipTab,
    },
    props: {
        modulBaru: { type: Array, default: () => [] },
        tautanAksesPeran: { type: String, default: null },
        modeGrup: { type: String, default: 'LAMA' },
        matrixCapabilities: { type: Object, default: () => EMPTY_CAPABILITIES },
        activeGrup: { type: Object, required: true },
    },
    data() {
        return {
            currentDate: new Date(),
            capabilities: { ...EMPTY_CAPABILITIES, ...(this.matrixCapabilities || {}) },
            activeTab: '',
            issueCounts: {},
        };
    },
    computed: {
        activeGrupId() {
            return Number(this.activeGrup?.Id_Grup_Fitur || 0);
        },
        activeGrupCode() {
            return this.activeGrup?.Kode_Grup_Fitur || '';
        },
        activeGrupName() {
            return this.activeGrup?.Nama_Grup_Fitur || this.activeGrupCode || 'grup aktif';
        },
        activeGrupIcon() {
            return this.activeGrup?.Icon_Grup_Fitur || 'bi bi-collection';
        },
        dayNight() {
            const hour = this.currentDate.getHours();
            return hour >= 6 && hour < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            return this.currentDate.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                year: 'numeric',
            });
        },
        tabs() {
            const tabs = [];
            if (this.capabilities.menu?.canView) {
                tabs.push({ id: 'menu', label: 'Menu', icon: 'bi bi-list-ul', count: this.issueBadge('menu') });
            }
            if (this.capabilities.approvalMatrix?.canView) {
                tabs.push({
                    id: 'approval-matrix',
                    label: 'Approval Matrix',
                    icon: 'bi bi-diagram-3',
                    count: this.issueBadge('approvalMatrix'),
                });
            }
            if (this.capabilities.membership?.canView) {
                tabs.push({
                    id: 'membership',
                    label: 'Keanggotaan',
                    icon: 'bi bi-people',
                    count: this.issueBadge('membership'),
                });
            }
            return tabs;
        },
        capabilityBadges() {
            return [
                { id: 'menu', cap: this.capabilities.menu, label: 'Kelola Menu', icon: 'bi bi-list-check' },
                {
                    id: 'approval',
                    cap: this.capabilities.approvalMatrix,
                    label: 'Kelola Approval',
                    icon: 'bi bi-diagram-3',
                },
                {
                    id: 'membership',
                    cap: this.capabilities.membership,
                    label: 'Kelola Keanggotaan',
                    icon: 'bi bi-people',
                },
            ]
                .filter((item) => item.cap?.canView)
                .map((item) => ({
                    ...item,
                    label: item.cap?.canAssign ? item.label : item.label.replace('Kelola', 'Lihat'),
                }));
        },
        targetScope() {
            return this.capabilities.targetScope || EMPTY_CAPABILITIES.targetScope;
        },
        targetScopeLabel() {
            if (this.targetScope.mode === 'SYSTEM') return `Target Kelolaan: Semua · ${this.targetScope.target_count || 0}`;
            if (this.targetScope.mode === 'DENIED') return 'Target Kelolaan: Belum dikonfigurasi';
            const label = this.targetScope.labels?.join(', ') || this.targetScope.scope_codes?.join(', ') || 'Terbatas';
            return `Target Kelolaan: ${label} · ${this.targetScope.target_count || 0}`;
        },
    },
    methods: {
        issueBadge(domain) {
            if (!Object.prototype.hasOwnProperty.call(this.issueCounts, domain)) return null;
            return Number(this.issueCounts[domain] || 0) > 0 ? Number(this.issueCounts[domain]) : '✓';
        },
        handleTabChange(tabId) {
            this.activeTab = tabId;
        },
        async refreshBootstrap() {
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/bootstrap/${this.activeGrupId}`);
                if (data?.capabilities) this.capabilities = { ...EMPTY_CAPABILITIES, ...data.capabilities };
                this.ensureDefaults();
                await this.loadIssueCounts();
            } catch (e) {
                console.error('Gagal refresh bootstrap menu-approval-matrix:', e);
            }
        },
        async loadIssueCounts() {
            if (!this.activeGrupId) return;
            try {
                const { data } = await axios.get(`/api/v1/menu-approval-matrix/issue-counts/${this.activeGrupId}`);
                this.issueCounts = data?.issues || {};
            } catch (e) {
                console.error('Gagal memuat badge masalah menu-approval-matrix:', e);
            }
        },
        ensureDefaults() {
            if (!this.tabs.find((tab) => tab.id === this.activeTab)) {
                this.activeTab = this.tabs[0]?.id || '';
            }
        },
    },
    mounted() {
        this.ensureDefaults();
        this.refreshBootstrap();
    },
};
</script>

<style scoped>
.bi {
    display: contents !important;
}
.matrix-app-container {
    padding: 1rem;
}
.access-context {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin: 1rem 0;
    padding: 0.8rem 1rem;
    border: 1px solid #dfe7f3;
    border-radius: 14px;
    background: #fff;
}
.group-context,
.access-context__right,
.capability-list,
.mode-badge,
.target-scope-badge,
.capability-badge {
    display: flex;
    align-items: center;
}
.group-context {
    min-width: 0;
    gap: 0.75rem;
}
.group-context > span:last-child {
    display: flex;
    min-width: 0;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.2rem 0.55rem;
}
.group-context small {
    width: 100%;
    color: var(--hcis-slate-500);
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.group-context strong {
    color: #172554;
    overflow-wrap: anywhere;
}
.group-context em {
    color: var(--hcis-indigo-500);
    font-size: 0.7rem;
    font-style: normal;
    font-weight: 800;
}
.group-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    flex: none;
    border-radius: 11px;
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-600);
}
.access-context__right {
    justify-content: flex-end;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.capability-list {
    gap: 0.35rem;
    flex-wrap: wrap;
}
.mode-badge,
.target-scope-badge,
.capability-badge {
    min-height: 30px;
    gap: 0.35rem;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
}
.mode-badge {
    background: #172554;
    color: #fff;
}
.target-scope-badge {
    border: 1px solid var(--hcis-sky-200);
    background: var(--hcis-sky-50);
    color: var(--hcis-sky-700);
}
.target-scope-badge--denied {
    border-color: #fed7aa;
    background: #fff7ed;
    color: #c2410c;
}
.target-scope-badge--legacy {
    border-color: var(--hcis-amber-200);
    background: var(--hcis-amber-50);
    color: var(--hcis-yellow-700);
}
.capability-badge {
    border: 1px solid var(--hcis-indigo-200);
    background: var(--hcis-indigo-50);
    color: var(--hcis-indigo-700);
}
.main-content-wrapper {
    display: flex;
    justify-content: center;
}
.content-container {
    width: 100%;
}
.stitch-card {
    background: #fff;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
}
.stitch-card.text-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.stitch-card.text-center i.bi {
    display: inline-flex !important;
    margin-bottom: 0.5rem;
}
@media (max-width: 768px) {
    .matrix-app-container {
        padding: 0.65rem;
    }
    .access-context {
        align-items: stretch;
        flex-direction: column;
    }
    .access-context__right {
        justify-content: flex-start;
    }
}
</style>
