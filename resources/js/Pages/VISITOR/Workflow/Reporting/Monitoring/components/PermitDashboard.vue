<template>
    <div class="domain-dashboard">
        <!--
            SATU header untuk seluruh tab:
            Single-row header presisi (Strict no-wrap policy) dengan stempel
            tanggal dan tombol aksi segarkan yang kompak.
        -->
        <header class="domain-dashboard__kepala">
            <div class="domain-dashboard__stempel">
                <span class="domain-dashboard__stempel-icon">
                    <HcisIcon name="calendar2-check-fill" size="xs" />
                </span>
                <span class="domain-dashboard__stempel-text">{{ label }}</span>
            </div>
            <HcisButton
                size="sm"
                variant="ghost"
                leading-icon="arrow-clockwise"
                :loading="menyegarkan"
                class="domain-dashboard__btn-refresh"
                @click="segarkan"
            >
                <span class="domain-dashboard__btn-label">Segarkan</span>
            </HcisButton>
        </header>

        <!--
            Urutan: kartu ringkas -> analisis -> tabel keadaan.
            Analisis disisipkan lewat slot `antara` supaya berada DI TENGAH
            tanpa memaksa papan mengambil datanya dua kali.
        -->
        <PapanHarian ref="papanRef" domain="permit" :needs-approval="needsApproval">
            <template #antara>
                <VisitorDashboardPanel ref="panelRef" domain="permit" :needs-approval="needsApproval" />
            </template>
        </PapanHarian>
    </div>
</template>

<script>
import { HcisButton, HcisIcon } from '@/components/ui';
import PapanHarian from './PapanHarian.vue';
import VisitorDashboardPanel from './VisitorDashboardPanel.vue';

export default {
    name: 'PermitDashboard',
    components: { HcisButton, HcisIcon, PapanHarian, VisitorDashboardPanel },
    props: {
        needsApproval: { type: Boolean, default: true },
    },
    data() {
        return { menyegarkan: false };
    },
    computed: {
        label() {
            return new Date().toLocaleDateString('id-ID', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        },
    },
    methods: {
        async segarkan() {
            this.menyegarkan = true;
            try {
                // Keduanya disegarkan bersama: satu tombol, satu makna.
                await Promise.all([
                    this.$refs.papanRef?.muat(),
                    this.$refs.papanRef?.muatTamu(),
                    this.$refs.panelRef?.segarkan(),
                ]);
            } finally {
                this.menyegarkan = false;
            }
        },
    },
};
</script>

<style scoped>
.domain-dashboard {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.domain-dashboard__kepala {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding: 0.35rem 0.15rem;
    min-width: 0;
}

.domain-dashboard__stempel {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-700, #334155);
    background: rgba(241, 245, 249, 0.85);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    min-width: 0;
    overflow: hidden;
}

.domain-dashboard__stempel-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-brand-600, #6366f1);
    flex-shrink: 0;
}

.domain-dashboard__stempel-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.domain-dashboard__btn-refresh {
    flex-shrink: 0;
    border-radius: 8px;
}

@media (max-width: 640px) {
    .domain-dashboard__stempel {
        font-size: 0.71875rem;
        padding: 0.2rem 0.5rem;
    }

    .domain-dashboard__btn-label {
        display: none !important;
    }

    .domain-dashboard__btn-refresh {
        width: 2rem;
        height: 2rem;
        padding: 0 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
}
</style>
