<template>
    <div class="domain-dashboard">
        <!--
            SATU header untuk seluruh tab. Terkunci dalam satu baris horizontal
            presisi tanpa wrap, dengan tombol segarkan responsif.
        -->
        <header class="domain-dashboard__kepala">
            <div class="domain-dashboard__stempel">
                <div class="domain-dashboard__live-dot" />
                <HcisIcon name="calendar2-check-fill" size="xs" class="text-indigo-600 flex-shrink-0" />
                <span class="domain-dashboard__date-text">{{ label }}</span>
            </div>
            <HcisButton
                size="sm"
                variant="ghost"
                leading-icon="arrow-clockwise"
                :loading="menyegarkan"
                class="domain-dashboard__refresh-btn"
                title="Segarkan data monitoring tamu"
                @click="segarkan"
            >
                <span class="domain-dashboard__btn-text">Segarkan</span>
            </HcisButton>
        </header>

        <!--
            Urutan: kartu ringkas -> analisis -> tabel keadaan.
            Analisis disisipkan lewat slot `antara` supaya berada DI TENGAH
            tanpa memaksa papan mengambil datanya dua kali.
        -->
        <PapanHarian
            ref="papanRef"
            domain="tamu"
            :needs-approval="needsApproval"
            :boleh-buka-permit="bolehBukaPermit"
        >
            <template #antara>
                <VisitorDashboardPanel ref="panelRef" domain="tamu" :needs-approval="needsApproval" />
            </template>
        </PapanHarian>
    </div>
</template>

<script>
import { HcisButton, HcisIcon } from '@/components/ui';
import PapanHarian from './PapanHarian.vue';
import VisitorDashboardPanel from './VisitorDashboardPanel.vue';

export default {
    name: 'TamuDashboard',
    components: { HcisButton, HcisIcon, PapanHarian, VisitorDashboardPanel },
    props: {
        needsApproval: { type: Boolean, default: false },
        bolehBukaPermit: { type: Boolean, default: false },
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
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    padding: 0.35rem 0;
}

.domain-dashboard__stempel {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.78125rem;
    font-weight: 650;
    color: var(--hcis-slate-700, #334155);
    background: rgba(241, 245, 249, 0.85);
    border: 1px solid var(--hcis-slate-200, #e2e8f0);
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    min-width: 0;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.domain-dashboard__live-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--hcis-success-500, #10b981);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
    flex-shrink: 0;
    animation: livePulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.domain-dashboard__date-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.domain-dashboard__refresh-btn {
    flex-shrink: 0;
}

@keyframes livePulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.6;
        transform: scale(0.9);
    }
}

@media (max-width: 640px) {
    .domain-dashboard__stempel {
        font-size: 0.71875rem;
        padding: 0.2rem 0.5rem;
    }

    .domain-dashboard__btn-text {
        display: none !important;
    }

    .domain-dashboard__refresh-btn {
        padding: 0.35rem 0.5rem !important;
        min-width: 2rem !important;
        height: 2rem !important;
    }
}
</style>
